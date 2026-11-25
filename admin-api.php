<?php
// ============================================================
// Wolf Friseur – Buchungssystem – Admin-API (Dashboard)
// Wird von admin-dashboard.php per fetch() aufgerufen.
// ============================================================

session_start();
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/app-lib.php'; // lädt functions.php mit
$config = wf_load_config();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

function wf_require_login() {
    if (empty($_SESSION['wf_admin'])) {
        http_response_code(401);
        echo json_encode(['error' => 'unauthorized']);
        exit;
    }
}

// ---- Login ----
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    $creds = wf_load_credentials($config);
    if ($user === $creds['username'] && password_verify($pass, $creds['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['wf_admin'] = true;
        // Alles, was VOR dem Login schon gebucht war, soll den Alarm nicht
        // auslösen – erst Buchungen ab jetzt zählen als "neu".
        if (empty($_SESSION['wf_last_ack'])) {
            $_SESSION['wf_last_ack'] = time();
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Benutzername oder Passwort falsch.']);
    }
    exit;
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'check') {
    echo json_encode(['logged_in' => !empty($_SESSION['wf_admin'])]);
    exit;
}

// ---- Alle Termine auflisten ----
if ($action === 'list') {
    wf_require_login();
    // Aufbewahrungsfrist (1 Jahr ab Erstellung): höchstens einmal täglich prüfen,
    // zusätzlich zum optionalen Cronjob cron-cleanup.php.
    try { wf_purge_daily($config); } catch (Throwable $e) { error_log('Wolf Friseur Bereinigung: ' . $e->getMessage()); }
    $appointments = wf_read_appointments($config);
    usort($appointments, function ($a, $b) {
        return [$a['date'], $a['time']] <=> [$b['date'], $b['time']];
    });
    clearstatcache(true, $config['data_file']);
    $mtime = file_exists($config['data_file']) ? filemtime($config['data_file']) : time();

    // ---- Alarm für neue Kundenbuchungen seit dem letzten "Erhalten"-Klick ----
    $lastAck = (int)($_SESSION['wf_last_ack'] ?? time());
    $pending = array_values(array_filter($appointments, function ($a) use ($lastAck) {
        return ($a['source'] ?? '') === 'customer'
            && in_array($a['status'] ?? '', ['pending', 'confirmed'], true)
            && strtotime($a['created_at'] ?? 'now') > $lastAck;
    }));

    $openMessages = count(array_filter(wf_load_messages($config), fn($m) => ($m['status'] ?? '') === 'open'));
    $appUnread = 0;
    try { $appUnread = wf_app_staff_unread_total($config); } catch (Throwable $e) { error_log('Wolf Friseur App-Chats: ' . $e->getMessage()); }

    echo json_encode([
        'appointments' => $appointments,
        'services' => wf_load_services($config),
        'mtime' => max($mtime, wf_app_mtime($config, 'conversations.json')),
        'app_unread' => $appUnread,
        'alert_pending' => count($pending) > 0,
        'alert_count' => count($pending),
        'alert_items' => $pending,
        'open_messages' => $openMessages,
        'today' => date('Y-m-d'),
        'now_time' => date('H:i'),
        'business_hours' => $config['business_hours'],
        'booking_window_days' => (int)$config['booking_window_days'],
    ]);
    exit;
}

// ---- Admin bestätigt: "Ich habe die Benachrichtigung erhalten" ----
if ($action === 'ack_alert' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $_SESSION['wf_last_ack'] = time();
    echo json_encode(['success' => true]);
    exit;
}

// ---- Live-Update: wartet (long-polling), bis sich appointments.json ändert ----
// Der Browser ruft das dauerhaft in einer Schleife auf. Sobald sich die Datei
// ändert (neue Buchung, Stornierung, ...), antwortet der Server SOFORT.
// Ändert sich nichts, antwortet der Server spätestens nach $maxWait Sekunden
// und der Browser fragt direkt erneut an – dadurch wirkt es wie Echtzeit,
// ohne eine dauerhafte Verbindung offen zu halten (funktioniert auf jedem
// Shared-Hosting, auch ohne WebSockets).
if ($action === 'poll') {
    wf_require_login();
    session_write_close(); // Session-Lock freigeben, damit andere Aktionen nicht blockiert werden
    @set_time_limit(30);

    $since = (float)($_GET['since'] ?? 0);
    $file = $config['data_file'];
    $maxWait = 25;
    $start = time();

    while (true) {
        clearstatcache(true, $file);
        $mtime = file_exists($file) ? filemtime($file) : time();
        // App-Chats zählen ebenfalls als Änderung (neue Kundennachricht)
        $mtime = max($mtime, wf_app_mtime($config, 'conversations.json'));
        if ($mtime > $since) {
            echo json_encode(['changed' => true, 'mtime' => $mtime]);
            exit;
        }
        if (time() - $start >= $maxWait) {
            echo json_encode(['changed' => false, 'mtime' => $mtime]);
            exit;
        }
        sleep(1);
    }
}

// ---- Termin bestätigen = Kunde war da -> Besuch auf der Treuekarte ----
// Wirkt genau wie ein QR-Scan in scan.html: Häkchen +1, nach 10 Besuchen
// 7 € Rabatt verdient, beim nächsten Besuch wird der Rabatt eingelöst.
// Jeder Termin kann nur EINMAL bestätigt werden.
if ($action === 'checkin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    session_write_close();
    $id = $_POST['id'] ?? '';
    $appt = null;
    $failReason = null;

    // 1) Termin im exklusiven Lock "reservieren", damit ein Doppelklick
    //    niemals zwei Häkchen erzeugt.
    $ok = wf_write_appointments($config, function ($data) use ($id, &$appt, &$failReason) {
        foreach ($data as &$a) {
            if ($a['id'] !== $id) continue;
            if (($a['type'] ?? '') === 'block') { $failReason = 'Blockierte Zeiten können nicht bestätigt werden.'; return false; }
            if (($a['status'] ?? '') === 'cancelled') { $failReason = 'Abgesagte Termine können nicht bestätigt werden.'; return false; }
            if (($a['status'] ?? '') === 'pending') { $failReason = 'Bitte den Termin zuerst annehmen.'; return false; }
            if (($a['status'] ?? '') === 'no_show') { $failReason = 'Dieser Termin ist als „Nicht erschienen“ markiert.'; return false; }
            if (!empty($a['checked_in'])) { $failReason = 'Dieser Termin wurde bereits bestätigt.'; return false; }
            if (trim($a['telefon'] ?? '') === '') { $failReason = 'Für diesen Termin ist keine Telefonnummer hinterlegt.'; return false; }
            $a['checked_in'] = true;
            $a['checked_in_at'] = date('c');
            $appt = $a;
            unset($a);
            return $data;
        }
        unset($a);
        $failReason = 'Termin nicht gefunden.';
        return false;
    });

    if (!$ok || !$appt) {
        echo json_encode(['success' => false, 'message' => $failReason ?: 'Aktion fehlgeschlagen.']);
        exit;
    }

    // 2) Besuch auf der Treuekarte eintragen (Firebase)
    $res = ['ok' => false];
    try {
        $res = wf_loyalty_add_visit(trim($appt['vorname'] . ' ' . $appt['nachname']), $appt['telefon']);
    } catch (Throwable $e) {
        $res = ['ok' => false];
    }

    if (empty($res['ok'])) {
        // Reservierung zurücknehmen, damit der Friseur es erneut versuchen kann
        wf_write_appointments($config, function ($data) use ($id) {
            foreach ($data as &$a) {
                if ($a['id'] === $id) { unset($a['checked_in'], $a['checked_in_at']); }
            }
            unset($a);
            return $data;
        });
        echo json_encode(['success' => false, 'message' => 'Treuekarte konnte nicht erreicht werden. Bitte erneut versuchen.']);
        exit;
    }

    // 3) Ergebnis am Termin speichern (wird in der Tabelle angezeigt)
    wf_write_appointments($config, function ($data) use ($id, $res) {
        foreach ($data as &$a) {
            if ($a['id'] === $id) {
                $a['loyalty_event'] = $res['event'];
                $a['loyalty_visits'] = $res['visits'];
            }
        }
        unset($a);
        return $data;
    });

    echo json_encode([
        'success' => true,
        'event' => $res['event'],
        'visits' => $res['visits'],
        'free_ready' => !empty($res['free_ready']),
        'account' => $res['account'] ?? 'exists',
        'reward_eur' => WF_LOYALTY_REWARD_EUR,
    ]);
    exit;
}

// ---- Neue Kundenbuchung annehmen oder ablehnen -> Kunde bekommt eine SMS ----
// Annehmen: status pending -> confirmed. Ablehnen: status -> cancelled (Slot wird frei).
if (($action === 'accept' || $action === 'reject') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    session_write_close();
    $id = $_POST['id'] ?? '';
    $accept = $action === 'accept';
    $appt = null;
    $replacedOld = null;
    $failReason = null;

    $ok = wf_write_appointments($config, function ($data) use ($id, $accept, &$appt, &$replacedOld, &$failReason) {
        foreach ($data as &$a) {
            if ($a['id'] !== $id) continue;
            if (($a['status'] ?? '') !== 'pending') { $failReason = 'Dieser Termin wurde bereits bearbeitet.'; return false; }
            if ($accept) {
                $a['status'] = 'confirmed';
                $a['accepted_at'] = date('c');
            } else {
                $a['status'] = 'cancelled';
                $a['cancelled_by'] = 'admin';
                $a['rejected'] = true;
            }
            $appt = $a;
            unset($a);
            // Verschiebungswunsch aus der App: alten Termin erst jetzt freigeben
            $replacedOld = wf_app_apply_change_decision($data, $appt, $accept);
            return $data;
        }
        unset($a);
        $failReason = 'Termin nicht gefunden.';
        return false;
    });

    if (!$ok || !$appt) {
        echo json_encode(['success' => false, 'message' => $failReason ?: 'Aktion fehlgeschlagen.']);
        exit;
    }

    // App-Push (falls der Kunde die App nutzt). Optional ersetzt eine
    // erfolgreiche Push-Zustellung die SMS (config.php -> app -> push_replaces_sms).
    $push = 'no_device';
    try {
        $event = $accept ? (!empty($appt['replaces']) ? 'rescheduled' : 'accepted') : 'rejected';
        $push = wf_app_notify_appointment($config, $appt, $event);
    } catch (Throwable $e) { error_log('Wolf Friseur Push: ' . $e->getMessage()); }
    $skipSms = $push === 'sent' && !empty(wf_app_cfg($config)['push_replaces_sms']);
    $smsSent = $skipSms ? false : wf_sms_customer_decision($config, $appt, $accept);
    echo json_encode(['success' => true, 'sms_sent' => $smsSent, 'sms_enabled' => ($config['sms']['provider'] ?? '') !== '',
        'push' => $push, 'sms_skipped_for_push' => $skipSms, 'replaced' => $replacedOld ? $replacedOld['id'] : null]);
    exit;
}

// ---- SMS-Protokoll anzeigen (Fehlersuche), neueste zuerst ----
if ($action === 'sms_log') {
    wf_require_login();
    header('Content-Type: text/plain; charset=utf-8');
    $sms = $config['sms'] ?? [];
    echo "Anbieter: " . (($sms['provider'] ?? '') ?: '(leer = SMS aus)') . "\n";
    echo "Friseur-Handy: " . (($sms['owner_phone'] ?? '') ?: '(leer)') . "\n";
    echo "API-Key gesetzt: " . (($sms['seven_api_key'] ?? '') !== '' ? 'ja' : 'NEIN') . "\n\n";
    $lines = wf_sms_log_read();
    echo $lines ? implode("\n", $lines) : 'Noch keine SMS-Versuche protokolliert.';
    exit;
}

// ---- Termin absagen (bleibt sichtbar, Slot wird wieder frei) ----
if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $id = $_POST['id'] ?? '';
    $wasActive = null;
    $ok = wf_write_appointments($config, function ($data) use ($id, &$wasActive) {
        $found = false;
        foreach ($data as &$a) {
            if ($a['id'] === $id) {
                if (in_array($a['status'] ?? '', ['pending', 'confirmed'], true)) $wasActive = $a;
                $a['status'] = 'cancelled'; $found = true;
            }
        }
        unset($a);
        return $found ? $data : false;
    });
    if ($ok && $wasActive && wf_appointment_timestamp($wasActive) > time()) {
        try { wf_app_notify_appointment($config, $wasActive, 'cancelled_by_salon'); } catch (Throwable $e) { error_log('Wolf Friseur Push: ' . $e->getMessage()); }
    }
    echo json_encode(['success' => $ok]);
    exit;
}

// ---- Termin endgültig löschen (Slot wird wieder frei) ----
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $id = $_POST['id'] ?? '';
    $ok = wf_write_appointments($config, function ($data) use ($id) {
        $newData = array_values(array_filter($data, fn($a) => $a['id'] !== $id));
        return count($newData) < count($data) ? $newData : false;
    });
    echo json_encode(['success' => $ok]);
    exit;
}

// ---- Termin bearbeiten ----
// Bestehende Daten (auch alte Termine) bleiben erhalten. Dauer/Leistung/Preis
// ändern sich NUR, wenn eine andere Leistung gewählt wird. Wird Zeitpunkt oder
// Dauer geändert, gelten Öffnungszeiten und Überschneidungsschutz (im Lock).
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $id = $_POST['id'] ?? '';
    $serviceKey = $_POST['service'] ?? null;
    $services = wf_services_keyed($config);
    $failMsg = null;

    if (isset($_POST['date']) && !wf_valid_date($_POST['date'])) $failMsg = 'Ungültiges Datum.';
    if (isset($_POST['time']) && !wf_valid_time($_POST['time'])) $failMsg = 'Ungültige Uhrzeit.';
    if (isset($_POST['status']) && !in_array($_POST['status'], ['pending', 'confirmed', 'cancelled', 'no_show'], true)) $failMsg = 'Ungültiger Status.';
    // Optional: Dauer dieses einen Termins anpassen (z.B. +15 Min). Gilt nur für diesen Termin.
    $newDuration = null;
    if (isset($_POST['duration']) && $_POST['duration'] !== '') {
        if (!preg_match('/^\d+$/', (string)$_POST['duration']) || (int)$_POST['duration'] < 1 || (int)$_POST['duration'] > 600) {
            $failMsg = 'Ungültige Dauer (1–600 Minuten).';
        } else {
            $newDuration = (int)$_POST['duration'];
        }
    }
    if ($failMsg) {
        echo json_encode(['success' => false, 'message' => $failMsg]);
        exit;
    }

    $pushEvent = null;
    $pushAppt = null;
    $ok = wf_write_appointments($config, function ($data) use ($id, $serviceKey, $services, $config, $newDuration, &$failMsg, &$pushEvent, &$pushAppt) {
        foreach ($data as $i => $a) {
            if ($a['id'] !== $id) continue;
            $new = $a;
            foreach (['date', 'time', 'vorname', 'nachname', 'telefon', 'status'] as $f) {
                if (isset($_POST[$f])) $new[$f] = trim($_POST[$f]);
            }
            if ($serviceKey && isset($services[$serviceKey]) && $serviceKey !== ($a['service'] ?? null)) {
                $new['service'] = $serviceKey;
                $new['service_label'] = $services[$serviceKey]['label'];
                $new['duration'] = (int)$services[$serviceKey]['duration'];
                $new['price'] = $services[$serviceKey]['price'] ?? null;
            }
            if ($newDuration !== null) $new['duration'] = $newDuration;
            $timeChanged = $new['date'] !== $a['date'] || $new['time'] !== $a['time'] || (int)$new['duration'] !== (int)$a['duration'];
            $reactivated = ($a['status'] ?? '') === 'cancelled' && ($new['status'] ?? '') !== 'cancelled';
            if (($timeChanged || $reactivated) && ($new['status'] ?? '') !== 'cancelled') {
                $err = wf_booking_error($new['date'], $new['time'], $new['duration'], $config, $data, ['exclude_id' => $id]);
                if ($err !== null) { $failMsg = wf_booking_error_message($err, $config); return false; }
            }
            if ($timeChanged || isset($a['end'])) $new['end'] = wf_appointment_end($new);
            $data[$i] = $new;
            // Kunde in der App informieren: Zeit geändert oder vom Salon abgesagt
            $wasActive = in_array($a['status'] ?? '', ['pending', 'confirmed'], true);
            if ($wasActive && ($new['status'] ?? '') === 'cancelled') { $pushEvent = 'cancelled_by_salon'; $pushAppt = $a; }
            elseif ($wasActive && ($new['date'] !== $a['date'] || $new['time'] !== $a['time'])) { $pushEvent = 'changed'; $pushAppt = $new; }
            return $data;
        }
        $failMsg = 'Termin nicht gefunden.';
        return false;
    });
    if ($ok && $pushEvent) {
        try { wf_app_notify_appointment($config, $pushAppt, $pushEvent); } catch (Throwable $e) { error_log('Wolf Friseur Push: ' . $e->getMessage()); }
    }
    echo json_encode(['success' => $ok, 'message' => $failMsg]);
    exit;
}

// ---- Termin als "Nicht erschienen" markieren (oder rückgängig) ----
if ($action === 'noshow' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $id = $_POST['id'] ?? '';
    $undo = !empty($_POST['undo']);
    $failMsg = null;
    $ok = wf_write_appointments($config, function ($data) use ($id, $undo, &$failMsg) {
        foreach ($data as &$a) {
            if ($a['id'] !== $id) continue;
            if ($undo) {
                if (($a['status'] ?? '') !== 'no_show') { $failMsg = 'Termin ist nicht als „Nicht erschienen“ markiert.'; return false; }
                $a['status'] = 'confirmed';
                unset($a['no_show_at']);
            } else {
                if (($a['type'] ?? '') === 'block' || ($a['status'] ?? '') !== 'confirmed' || !empty($a['checked_in'])) {
                    $failMsg = 'Nur angenommene, noch nicht bestätigte Termine können als „Nicht erschienen“ markiert werden.';
                    return false;
                }
                if (wf_appointment_timestamp($a) > time()) { $failMsg = 'Der Termin hat noch nicht begonnen.'; return false; }
                $a['status'] = 'no_show';
                $a['no_show_at'] = date('c');
            }
            unset($a);
            return $data;
        }
        unset($a);
        $failMsg = 'Termin nicht gefunden.';
        return false;
    });
    echo json_encode(['success' => $ok, 'message' => $failMsg]);
    exit;
}

// ---- Manueller Termin durch den Friseur ----
// Ohne Mindestvorlauf der Online-Buchung, aber mit Öffnungszeiten,
// Leistungsdauer und Überschneidungsschutz (gleiche Prüfung wie online, im Lock).
if ($action === 'manual_book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    session_write_close();
    $vorname  = trim($_POST['vorname'] ?? '');
    $nachname = trim($_POST['nachname'] ?? '');
    $telefon  = trim($_POST['telefon'] ?? '');
    $type     = ($_POST['type'] ?? 'mann') === 'kind' ? 'kind' : 'mann';
    $serviceKey = $type === 'kind' ? 'kind' : ($_POST['service'] ?? '');
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $services = wf_services_keyed($config);

    $fail = function ($msg) { echo json_encode(['success' => false, 'message' => $msg]); exit; };
    if ($vorname === '') $fail('Bitte mindestens den Vornamen eingeben.');
    if (!isset($services[$serviceKey])) $fail('Bitte eine Leistung auswählen.');
    if (!wf_valid_date($date) || !wf_valid_time($time)) $fail('Bitte Datum und Uhrzeit angeben.');
    $maxDate = date('Y-m-d', strtotime("+{$config['booking_window_days']} day"));
    if ($date > $maxDate) $fail('Termine können bis maximal ' . (int)$config['booking_window_days'] . ' Tage im Voraus eingetragen werden.');

    $service = $services[$serviceKey];
    $duration = (int)$service['duration'];
    $newAppointment = null;
    $err = null;
    $ok = wf_write_appointments($config, function ($data) use ($config, $date, $time, $duration, $serviceKey, $service, $type, $vorname, $nachname, $telefon, &$newAppointment, &$err) {
        $err = wf_booking_error($date, $time, $duration, $config, $data, ['earliest_ts' => wf_manual_earliest_ts()]);
        if ($err !== null) return false;
        $newAppointment = [
            'id' => wf_generate_id(),
            'date' => $date,
            'time' => $time,
            'duration' => $duration,
            'end' => wf_minutes_to_time(wf_time_to_minutes($time) + $duration),
            'price' => $service['price'] ?? null,
            'service' => $serviceKey,
            'service_label' => $service['label'],
            'type' => $type,
            'type_label' => $type === 'mann' ? 'Herr' : 'Kind',
            'vorname' => $vorname,
            'nachname' => $nachname,
            'telefon' => $telefon,
            'status' => 'confirmed',
            'source' => 'admin',
            'created_at' => date('c'),
        ];
        $data[] = $newAppointment;
        return $data;
    });
    if (!$ok || !$newAppointment) $fail($err ? wf_booking_error_message($err, $config) : 'Speichern fehlgeschlagen.');

    // Nur Telegram (keine SMS – das SMS-Verhalten bleibt unverändert)
    $telegram = wf_telegram_notify_new_booking($config, $newAppointment);
    echo json_encode(['success' => true, 'appointment' => $newAppointment, 'telegram' => $telegram]);
    exit;
}

// ---- Zeitfenster manuell blockieren (z.B. Pause, Urlaub) ----
if ($action === 'block' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $duration = (int)($_POST['duration'] ?? 30);
    $note = trim($_POST['note'] ?? 'Blockiert');

    if (!wf_valid_date($date) || !wf_valid_time($time) || $duration < 1 || $duration > 24 * 60) {
        echo json_encode(['success' => false, 'message' => 'Ungültiges Datum/Uhrzeit/Dauer.']);
        exit;
    }

    $err = null;
    $ok = wf_write_appointments($config, function ($data) use ($config, $date, $time, $duration, $note, &$err) {
        $err = wf_booking_error($date, $time, $duration, $config, $data, ['earliest_ts' => wf_manual_earliest_ts()]);
        if ($err !== null) return false;
        $data[] = [
            'id' => wf_generate_id(),
            'date' => $date,
            'time' => $time,
            'duration' => $duration,
            'end' => wf_minutes_to_time(wf_time_to_minutes($time) + $duration),
            'service' => 'blocked',
            'service_label' => $note,
            'type' => 'block',
            'type_label' => 'Blockiert',
            'vorname' => '', 'nachname' => '', 'telefon' => '',
            'status' => 'confirmed',
            'source' => 'admin',
            'created_at' => date('c'),
        ];
        return $data;
    });
    echo json_encode(['success' => $ok, 'message' => $ok ? null : ($err ? wf_booking_error_message($err, $config) : 'Speichern fehlgeschlagen.')]);
    exit;
}

// ============================================================
// Leistungen verwalten (Tab "Leistungen" im Dashboard)
// ============================================================

if ($action === 'services_list') {
    wf_require_login();
    echo json_encode(['success' => true, 'services' => wf_load_services($config)]);
    exit;
}

// Anlegen (key leer) ODER Bearbeiten (key gesetzt) einer Leistung
if ($action === 'services_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $key = trim($_POST['key'] ?? '');
    $label = trim($_POST['label'] ?? '');
    $priceRaw = $_POST['price'] ?? '';
    $duration = (int)($_POST['duration'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    $durationRaw = trim((string)($_POST['duration'] ?? ''));
    if (!preg_match('/^\d+$/', $durationRaw) || $duration < 1 || $duration > 600) {
        echo json_encode(['success' => false, 'message' => 'Die Dauer muss eine ganze Zahl an Minuten sein (1–600).']);
        exit;
    }
    // Kinderhaarschnitt ist ein fester Systemeintrag: nur die Dauer ist änderbar.
    // Die neue Dauer gilt nur für neue Buchungen (bestehende behalten ihre Dauer).
    if ($key === 'kind') {
        $ok = wf_save_services($config, function ($data) use ($duration) {
            foreach ($data as &$s) {
                if ($s['key'] === 'kind') $s['duration'] = $duration;
            }
            unset($s);
            return $data;
        });
        echo json_encode(['success' => $ok, 'key' => 'kind']);
        exit;
    }
    if ($label === '') {
        echo json_encode(['success' => false, 'message' => 'Bitte einen Namen für die Leistung eingeben.']);
        exit;
    }
    if ($priceRaw === '' || !is_numeric($priceRaw) || (float)$priceRaw < 0) {
        echo json_encode(['success' => false, 'message' => 'Bitte einen gültigen Preis eingeben.']);
        exit;
    }
    $price = round((float)$priceRaw, 2);

    $ok = wf_save_services($config, function ($data) use (&$key, $label, $price, $duration, $description) {
        if ($key === '') {
            // Neue Leistung: eindeutigen Schlüssel aus dem Namen ableiten
            $base = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($label, 'UTF-8'));
            $base = trim($base, '_');
            if ($base === '') $base = 'leistung';
            $existing = array_column($data, 'key');
            $candidate = $base;
            $i = 2;
            while (in_array($candidate, $existing, true)) {
                $candidate = $base . '_' . $i;
                $i++;
            }
            $key = $candidate;
            $maxOrder = 0;
            foreach ($data as $s) {
                if (($s['category'] ?? '') === 'herr') $maxOrder = max($maxOrder, (int)($s['order'] ?? 0));
            }
            $data[] = [
                'key' => $key, 'label' => $label, 'duration' => $duration, 'price' => $price,
                'description' => $description, 'category' => 'herr', 'order' => $maxOrder + 1,
            ];
        } else {
            $found = false;
            foreach ($data as &$s) {
                if ($s['key'] === $key) {
                    $s['label'] = $label;
                    $s['duration'] = $duration;
                    $s['price'] = $price;
                    $s['description'] = $description;
                    $found = true;
                }
            }
            unset($s);
            if (!$found) return false;
        }
        return $data;
    });

    echo json_encode(['success' => $ok, 'key' => $key]);
    exit;
}

if ($action === 'services_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $key = trim($_POST['key'] ?? '');

    $ok = wf_save_services($config, function ($data) use ($key) {
        $target = null;
        foreach ($data as $s) {
            if ($s['key'] === $key) $target = $s;
        }
        // "kind" bleibt fest und lässt sich nicht löschen; mindestens eine
        // Herren-Leistung muss immer übrig bleiben.
        if (!$target || ($target['category'] ?? '') !== 'herr') return false;
        $herrCount = count(array_filter($data, fn($s) => ($s['category'] ?? '') === 'herr'));
        if ($herrCount <= 1) return false;
        return array_values(array_filter($data, fn($s) => $s['key'] !== $key));
    });

    echo json_encode(['success' => $ok]);
    exit;
}

// ============================================================
// Häufige Fragen verwalten (Tab "FAQ" im Dashboard)
// ============================================================

if ($action === 'faqs_list') {
    wf_require_login();
    echo json_encode(['success' => true, 'faqs' => wf_load_faqs($config)]);
    exit;
}

if ($action === 'faqs_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $id = trim($_POST['id'] ?? '');
    $question = trim($_POST['question'] ?? '');
    $answer = trim($_POST['answer'] ?? '');

    if ($question === '' || $answer === '') {
        echo json_encode(['success' => false, 'message' => 'Bitte Frage und Antwort ausfüllen.']);
        exit;
    }

    $ok = wf_save_faqs($config, function ($data) use (&$id, $question, $answer) {
        if ($id === '') {
            $id = wf_generate_id();
            $maxOrder = 0;
            foreach ($data as $f) $maxOrder = max($maxOrder, (int)($f['order'] ?? 0));
            $data[] = ['id' => $id, 'question' => $question, 'answer' => $answer, 'order' => $maxOrder + 1];
        } else {
            $found = false;
            foreach ($data as &$f) {
                if ($f['id'] === $id) {
                    $f['question'] = $question;
                    $f['answer'] = $answer;
                    $found = true;
                }
            }
            unset($f);
            if (!$found) return false;
        }
        return $data;
    });

    echo json_encode(['success' => $ok, 'id' => $id]);
    exit;
}

if ($action === 'faqs_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $id = trim($_POST['id'] ?? '');

    $ok = wf_save_faqs($config, function ($data) use ($id) {
        $new = array_values(array_filter($data, fn($f) => $f['id'] !== $id));
        return count($new) < count($data) ? $new : false;
    });

    echo json_encode(['success' => $ok]);
    exit;
}

// ============================================================
// Kundennachrichten verwalten (Tab "Nachrichten" im Dashboard)
// ============================================================

if ($action === 'messages_list') {
    wf_require_login();
    $messages = wf_load_messages($config);
    usort($messages, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

// Nachricht als "gesehen" markieren, sobald der Admin sie öffnet
if ($action === 'messages_seen' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $id = trim($_POST['id'] ?? '');
    $ok = wf_save_messages($config, function ($data) use ($id) {
        foreach ($data as &$m) {
            if ($m['id'] === $id && ($m['status'] ?? '') === 'open') $m['status'] = 'seen';
        }
        unset($m);
        return $data;
    });
    echo json_encode(['success' => $ok]);
    exit;
}

if ($action === 'messages_reply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $id = trim($_POST['id'] ?? '');
    $reply = trim($_POST['reply'] ?? '');

    if ($reply === '') {
        echo json_encode(['success' => false, 'message' => 'Bitte eine Antwort eingeben.']);
        exit;
    }

    $ok = wf_save_messages($config, function ($data) use ($id, $reply) {
        $found = false;
        foreach ($data as &$m) {
            if ($m['id'] === $id) {
                $m['admin_reply'] = $reply;
                $m['status'] = 'replied';
                $m['replied_at'] = date('c');
                $found = true;
            }
        }
        unset($m);
        return $found ? $data : false;
    });

    echo json_encode(['success' => $ok]);
    exit;
}

// ============================================================
// App-Chats (Tab "App-Chats" im Dashboard) – Konversationen mit
// Kunden aus der Android-/iOS-App. Antworten erscheinen in der App.
// ============================================================

if ($action === 'conv_list') {
    wf_require_login();
    session_write_close();
    echo json_encode(['success' => true, 'conversations' => wf_app_staff_conversations($config), 'unread' => wf_app_staff_unread_total($config)]);
    exit;
}

if ($action === 'conv_get') {
    wf_require_login();
    session_write_close();
    $conv = wf_app_conv_get($config, (string)($_GET['id'] ?? ''));
    if (!$conv) { echo json_encode(['success' => false, 'message' => 'Konversation nicht gefunden.']); exit; }
    $cust = wf_app_customer_by_id($config, $conv['customer_id']);
    $appt = null;
    if ($conv['appointment_id']) {
        foreach (wf_read_appointments($config) as $a) if ($a['id'] === $conv['appointment_id']) $appt = wf_app_staff_appt_summary($a);
    }
    $page = wf_app_conv_messages_page($conv, 'staff', isset($_GET['before_seq']) ? (int)$_GET['before_seq'] : null, null, 200);
    echo json_encode(['success' => true, 'conversation' => [
        'id' => $conv['id'], 'subject' => $conv['subject'], 'created_at' => $conv['created_at'],
        'customer' => $cust ? ['name' => trim(($cust['vorname'] ?? '') . ' ' . ($cust['nachname'] ?? '')), 'phone' => $cust['phone'],
            'has_device' => count(wf_app_customer_devices($config, $cust['id'])) > 0] : null,
        'appointment' => $appt,
        'unread' => wf_app_conv_unread_for($conv, 'staff'),
    ]] + $page);
    exit;
}

if ($action === 'conv_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    session_write_close();
    $ok = wf_app_conv_mark_read($config, (string)($_POST['id'] ?? ''), 'staff');
    echo json_encode(['success' => $ok]);
    exit;
}

if ($action === 'conv_reply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    session_write_close();
    $convId = (string)($_POST['id'] ?? '');
    $clientMsgId = trim((string)($_POST['client_msg_id'] ?? ''));
    $r = wf_app_conv_append($config, $convId, 'staff', (string)($_POST['text'] ?? ''), $clientMsgId === '' ? null : $clientMsgId, null,
        wf_load_credentials($config)['username']);
    if (!$r['ok']) {
        $msg = $r['error'] === 'empty_message' ? 'Bitte eine Nachricht eingeben.' : 'Konversation nicht gefunden.';
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
    $push = 'duplicate';
    if (!$r['replayed']) {
        $cust = wf_app_customer_by_id($config, $r['conversation']['customer_id']);
        $push = $cust ? wf_app_push_to_customer($config, $cust, 'message', 'Wolf Friseur', 'Sie haben eine neue Nachricht vom Salon.',
            ['type' => 'message', 'conversation_id' => $convId]) : 'no_device';
        wf_app_conv_set_push_status($config, $convId, $r['message']['id'], $push);
    }
    echo json_encode(['success' => true, 'message' => wf_app_message_public($r['message'] + ['push_status' => $push], $r['conversation'], 'staff'), 'push' => $push]);
    exit;
}

// ============================================================
// Dashboard-Passwort ändern (Tab "Einstellungen")
// ============================================================

if ($action === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $newPasswordConfirm = (string)($_POST['new_password_confirm'] ?? '');

    $creds = wf_load_credentials($config);

    if (!password_verify($currentPassword, $creds['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Das aktuelle Passwort ist nicht korrekt.']);
        exit;
    }
    if (mb_strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'Das neue Passwort muss mindestens 8 Zeichen lang sein.']);
        exit;
    }
    if ($newPassword !== $newPasswordConfirm) {
        echo json_encode(['success' => false, 'message' => 'Die neuen Passwörter stimmen nicht überein.']);
        exit;
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $ok = wf_save_credentials($config, function ($data) use ($newHash) {
        $data['password_hash'] = $newHash;
        return $data;
    });

    echo json_encode(['success' => $ok, 'message' => $ok ? 'Passwort erfolgreich geändert.' : 'Speichern fehlgeschlagen.']);
    exit;
}

// ============================================================
// Archiv (Tab "Archiv") – Suche & Filter serverseitig, seitenweise
// ============================================================

if ($action === 'archive') {
    wf_require_login();
    session_write_close();
    $f = [
        'name' => mb_substr(trim((string)($_GET['name'] ?? '')), 0, 100),
        'phone' => mb_substr(trim((string)($_GET['phone'] ?? '')), 0, 30),
        'date' => (string)($_GET['date'] ?? ''),
        'from' => (string)($_GET['from'] ?? ''),
        'to' => (string)($_GET['to'] ?? ''),
        'status' => (string)($_GET['status'] ?? ''),
        'page' => (int)($_GET['page'] ?? 1),
        'per_page' => 50,
    ];
    foreach (['date', 'from', 'to'] as $k) {
        if ($f[$k] !== '' && !wf_valid_date($f[$k])) {
            echo json_encode(['success' => false, 'message' => 'Ungültiges Datum.']);
            exit;
        }
    }
    if (!in_array($f['status'], ['', 'completed', 'cancelled', 'no_show', 'past_open'], true)) $f['status'] = '';
    $result = wf_archive_search(wf_read_appointments($config), $f);
    echo json_encode(['success' => true] + $result);
    exit;
}

// ============================================================
// Einstellungen: Mindestvorlauf für Online-Buchungen
// ============================================================

if ($action === 'settings_get') {
    wf_require_login();
    $s = wf_load_settings($config);
    echo json_encode(['success' => true, 'booking_lead_minutes' => (int)$s['booking_lead_minutes']]);
    exit;
}

if ($action === 'settings_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $raw = trim((string)($_POST['booking_lead_minutes'] ?? ''));
    if (!preg_match('/^\d+$/', $raw) || (int)$raw > 7 * 24 * 60) {
        echo json_encode(['success' => false, 'message' => 'Bitte eine ganze Zahl an Minuten eingeben (0 bis 10080).']);
        exit;
    }
    $lead = (int)$raw;
    $ok = wf_save_settings($config, function ($s) use ($lead) { $s['booking_lead_minutes'] = $lead; return $s; });
    echo json_encode(['success' => $ok, 'booking_lead_minutes' => $lead, 'message' => $ok ? 'Gespeichert.' : 'Speichern fehlgeschlagen.']);
    exit;
}

// ============================================================
// Telegram verbinden / trennen (Tab "Einstellungen")
// ============================================================

function wf_telegram_status_payload($config, $extra = []) {
    $state = wf_telegram_state($config);
    $pending = $state['pending'] && hash_equals((string)$state['pending']['session'], wf_telegram_session_hash())
        && time() <= (int)$state['pending']['expires'];
    return array_merge([
        'success' => true,
        'configured' => wf_telegram_token($config) !== '',
        'linked' => !empty($state['chat_id']),
        'chat_name' => $state['chat_name'],
        'linked_at' => $state['linked_at'],
        'pending' => $pending,
    ], $extra);
}

if ($action === 'telegram_status') {
    wf_require_login();
    // Wartet diese Sitzung auf die Verknüpfung? Dann neue Bot-Nachrichten abholen.
    $poll = wf_telegram_poll_link($config);
    echo json_encode(wf_telegram_status_payload($config, ['poll' => $poll]));
    exit;
}

if ($action === 'telegram_connect' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $res = wf_telegram_start_link($config, wf_load_credentials($config)['username']);
    echo json_encode($res);
    exit;
}

if ($action === 'telegram_disconnect' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $ok = wf_telegram_unlink($config);
    echo json_encode(wf_telegram_status_payload($config, ['success' => $ok]));
    exit;
}

if ($action === 'telegram_test' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    wf_require_login();
    $state = wf_telegram_state($config);
    if (empty($state['chat_id'])) {
        echo json_encode(['success' => false, 'message' => 'Telegram ist noch nicht verbunden.']);
        exit;
    }
    $ok = wf_telegram_send_to($config, $state['chat_id'], 'Wolf Friseur: Testnachricht aus dem Dashboard ✅');
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Testnachricht gesendet.' : 'Senden fehlgeschlagen. Details im SMS-/Telegram-Protokoll.']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'invalid_action']);
