<?php
// ============================================================
// Wolf Friseur – Buchungssystem – Öffentliche Buchungs-API
// Wird von termin.php per fetch() aufgerufen.
// ============================================================

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/functions.php';
$config = wf_load_config();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ---- Verfügbare Slots für ein Datum + eine Dauer abfragen ----
if ($action === 'slots') {
    $date = $_GET['date'] ?? '';
    // Dauer immer aus der gewählten Leistung (serverseitig), nicht vom Browser
    $services = wf_services_keyed($config);
    $serviceKey = ($_GET['type'] ?? '') === 'kind' ? 'kind' : ($_GET['service'] ?? '');
    $duration = isset($services[$serviceKey]) ? (int)$services[$serviceKey]['duration'] : (int)($_GET['duration'] ?? 30);

    if (!wf_valid_date($date) || $duration < 1) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_date']);
        exit;
    }

    $minDate = date('Y-m-d');
    $maxDate = date('Y-m-d', strtotime("+{$config['booking_window_days']} day"));
    if ($date < $minDate || $date > $maxDate) {
        echo json_encode(['slots' => [], 'closed' => false, 'out_of_range' => true]);
        exit;
    }

    $isClosed = wf_business_hours($date, $config) === null;
    $appointments = wf_read_appointments($config);
    $starts = $isClosed ? [] : wf_available_starts($date, $duration, $config, $appointments, wf_online_earliest_ts($config));
    // Nur tatsächlich freie Startzeiten (kein festes Raster mehr)
    $slots = array_map(fn($t) => ['time' => $t, 'end' => wf_minutes_to_time(wf_time_to_minutes($t) + $duration), 'available' => true], $starts);

    echo json_encode(['slots' => $slots, 'closed' => $isClosed, 'out_of_range' => false, 'duration' => $duration]);
    exit;
}

// ---- Neue Buchung anlegen ----
if ($action === 'book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $vorname   = trim($_POST['vorname'] ?? '');
    $nachname  = trim($_POST['nachname'] ?? '');
    $telefon   = trim($_POST['telefon'] ?? '');
    $type      = $_POST['type'] ?? '';       // mann | kind
    $serviceKey = $_POST['service'] ?? '';
    $date      = $_POST['date'] ?? '';
    $time      = $_POST['time'] ?? '';

    if ($vorname === '' || $nachname === '' || $telefon === '') {
        echo json_encode(['success' => false, 'message' => 'Bitte Vorname, Nachname und Telefonnummer ausfüllen.']);
        exit;
    }
    if (!in_array($type, ['mann', 'kind'], true)) {
        echo json_encode(['success' => false, 'message' => 'Bitte auswählen: Herr oder Kind.']);
        exit;
    }
    if ($type === 'kind') {
        $serviceKey = 'kind';
    }
    $services = wf_services_keyed($config);
    if (!isset($services[$serviceKey])) {
        echo json_encode(['success' => false, 'message' => 'Bitte eine Leistung auswählen.']);
        exit;
    }
    if (!wf_valid_date($date) || !wf_valid_time($time)) {
        echo json_encode(['success' => false, 'message' => 'Bitte Datum und Uhrzeit auswählen.']);
        exit;
    }

    $minDate = date('Y-m-d');
    $maxDate = date('Y-m-d', strtotime("+{$config['booking_window_days']} day"));
    if ($date < $minDate || $date > $maxDate) {
        echo json_encode(['success' => false, 'message' => 'Dieses Datum liegt außerhalb des Buchungszeitraums.']);
        exit;
    }

    $service = $services[$serviceKey];
    $duration = (int)$service['duration'];

    $newAppointment = null;
    $failReason = null; // 'duplicate' | Fehlercode aus wf_booking_error()

    // Verfügbarkeit UND Duplikat-Prüfung werden ERNEUT innerhalb des exklusiven
    // Locks durchgeführt, damit zwei Kunden niemals denselben/überlappenden
    // Termin bekommen können und ein Kunde nicht zweimal am selben Tag bucht.
    $ok = wf_write_appointments($config, function ($data) use (
        &$newAppointment, &$failReason, $config, $date, $time, $duration, $serviceKey, $service, $type, $vorname, $nachname, $telefon
    ) {
        if (wf_customer_has_booking_on_date($data, $date, $vorname, $nachname, $telefon)) {
            $failReason = 'duplicate';
            return false;
        }

        $failReason = wf_booking_error($date, $time, $duration, $config, $data, [
            'earliest_ts' => wf_online_earliest_ts($config), 'online' => true,
        ]);
        if ($failReason !== null) return false;

        $id = wf_generate_id();
        $newAppointment = [
            'id' => $id,
            'date' => $date,
            'time' => $time,
            'duration' => $duration,          // feste Kopie – ändert sich nie mehr mit der Leistung
            'end' => wf_minutes_to_time(wf_time_to_minutes($time) + $duration),
            'price' => $service['price'] ?? null,
            'service' => $serviceKey,
            'service_label' => $service['label'],
            'type' => $type,
            'type_label' => $type === 'mann' ? 'Herr' : 'Kind',
            'vorname' => $vorname,
            'nachname' => $nachname,
            'telefon' => $telefon,
            'status' => 'pending', // wartet auf Annahme/Ablehnung im Dashboard
            'source' => 'customer',
            'created_at' => date('c'),
        ];
        $data[] = $newAppointment;
        return $data;
    });

    if (!$ok || !$newAppointment) {
        if ($failReason === 'duplicate') {
            echo json_encode([
                'success' => false,
                'message' => 'Sie haben für diesen Tag bereits einen Termin gebucht. Pro Tag ist nur ein Termin pro Person möglich.',
                'duplicate' => true,
            ]);
        } elseif ($failReason === 'overlap' || $failReason === null) {
            echo json_encode([
                'success' => false,
                'message' => 'Dieser Termin wurde leider gerade eben vergeben. Bitte wählen Sie eine andere Uhrzeit.',
                'slot_taken' => true,
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => wf_booking_error_message($failReason, $config),
                'slot_taken' => true,
            ]);
        }
        exit;
    }

    // Benachrichtigungen: jeder Kanal einzeln abgesichert. Ein Fehler in einem
    // Kanal verhindert weder die (bereits gespeicherte) Buchung noch die anderen.
    // E-Mail an info@ deaktiviert (04.11.2026) – Benachrichtigung nur per SMS + Dashboard
    // try { wf_send_owner_notification($config, $newAppointment); } catch (Throwable $e) { error_log('Wolf Friseur E-Mail: ' . $e->getMessage()); }
    try { wf_sms_owner_new_booking($config, $newAppointment); } catch (Throwable $e) { error_log('Wolf Friseur SMS: ' . $e->getMessage()); }
    wf_telegram_notify_new_booking($config, $newAppointment);

    // ---- Treuekarte: Konto automatisch anlegen, falls noch keins existiert ----
    // (Fehler hier dürfen die bereits gespeicherte Buchung nicht beeinflussen.)
    // Es wird KEIN Stempel vergeben – nur der aktuelle Stand wird angezeigt.
    $loyalty = ['status' => 'error', 'password_default' => false];
    try {
        $loyaltyPassword = (string)($_POST['loyalty_password'] ?? '');
        $acc = wf_loyalty_ensure_account($vorname . ' ' . $nachname, $telefon, $loyaltyPassword);
        $loyalty = ['status' => $acc['status'], 'password_default' => $acc['password_default']];
        if ($acc['status'] !== 'error') {
            $loyalty['visits'] = (int)($acc['data']['visits'] ?? 0);
            $loyalty['free_ready'] = !empty($acc['data']['freeReady']);
            $loyalty['total'] = WF_LOYALTY_VISITS;
            $loyalty['reward_eur'] = WF_LOYALTY_REWARD_EUR;
        }
    } catch (Throwable $e) {
        $loyalty = ['status' => 'error', 'password_default' => false];
    }

    echo json_encode(['success' => true, 'appointment' => $newAppointment, 'loyalty' => $loyalty]);
    exit;
}

// ---- Eigene Termine suchen (für Stornierung) ----
if ($action === 'find_bookings') {
    $vorname  = trim($_GET['vorname'] ?? '');
    $nachname = trim($_GET['nachname'] ?? '');
    $telefon  = trim($_GET['telefon'] ?? '');

    if ($vorname === '' || $nachname === '' || $telefon === '') {
        echo json_encode(['success' => false, 'message' => 'Bitte Vorname, Nachname und Telefonnummer eingeben.']);
        exit;
    }

    $appointments = wf_read_appointments($config);
    $now = time();
    $matches = [];
    foreach ($appointments as $a) {
        if (($a['type'] ?? '') === 'block') continue;
        if (($a['status'] ?? '') === 'cancelled') continue;
        if (!wf_customer_matches($a, $vorname, $nachname, $telefon)) continue;
        if (wf_appointment_timestamp($a) <= $now) continue; // nur zukünftige Termine

        $hoursUntil = wf_hours_until($a);
        $matches[] = [
            'id' => $a['id'],
            'date' => $a['date'],
            'time' => $a['time'],
            'service_label' => $a['service_label'],
            'type_label' => $a['type_label'],
            'status' => $a['status'] ?? 'confirmed',
            'can_cancel' => $hoursUntil >= $config['self_cancel_min_hours'],
        ];
    }
    usort($matches, fn($x, $y) => [$x['date'], $x['time']] <=> [$y['date'], $y['time']]);

    echo json_encode(['success' => true, 'appointments' => $matches, 'min_hours' => $config['self_cancel_min_hours'], 'salon_phone' => $config['salon_phone']]);
    exit;
}

// ---- Eigenen Termin stornieren ----
if ($action === 'cancel_booking' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = $_POST['id'] ?? '';
    $vorname  = trim($_POST['vorname'] ?? '');
    $nachname = trim($_POST['nachname'] ?? '');
    $telefon  = trim($_POST['telefon'] ?? '');

    $failReason = null; // 'not_found' | 'too_late'
    $ok = wf_write_appointments($config, function ($data) use ($id, $vorname, $nachname, $telefon, $config, &$failReason) {
        $found = false;
        foreach ($data as &$a) {
            if ($a['id'] !== $id) continue;
            if (($a['status'] ?? '') === 'cancelled') continue;
            if (!wf_customer_matches($a, $vorname, $nachname, $telefon)) continue;
            if (wf_hours_until($a) < $config['self_cancel_min_hours']) {
                $failReason = 'too_late';
                return false;
            }
            $a['status'] = 'cancelled';
            $a['cancelled_by'] = 'customer';
            $found = true;
        }
        unset($a);
        return $found ? $data : false;
    });

    if (!$ok) {
        if ($failReason === 'too_late') {
            echo json_encode([
                'success' => false,
                'message' => "Eine Online-Stornierung ist ab {$config['self_cancel_min_hours']} Stunden vor dem Termin nicht mehr möglich. Bitte rufen Sie uns direkt an: {$config['salon_phone']}.",
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Termin nicht gefunden. Bitte prüfen Sie Ihre Angaben.']);
        }
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

// ---- FAQ-Rückmeldung: Kunde findet die Antworten nicht hilfreich ----
if ($action === 'send_feedback' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $vorname  = trim($_POST['vorname'] ?? '');
    $nachname = trim($_POST['nachname'] ?? '');
    $telefon  = trim($_POST['telefon'] ?? '');
    $note     = trim($_POST['note'] ?? '');
    $context  = trim($_POST['context'] ?? '');

    if ($vorname === '' || $nachname === '' || $telefon === '' || $note === '') {
        echo json_encode(['success' => false, 'message' => 'Bitte Name, Telefonnummer und Ihre Nachricht ausfüllen.']);
        exit;
    }

    $entry = [
        'id' => wf_generate_id(),
        'vorname' => $vorname,
        'nachname' => $nachname,
        'telefon' => $telefon,
        'note' => $note,
        'context' => $context,
        'status' => 'open',
        'admin_reply' => '',
        'replied_at' => null,
        'created_at' => date('c'),
    ];
    $ok = wf_save_messages($config, function ($data) use ($entry) {
        $data[] = $entry;
        return $data;
    });
    echo json_encode(['success' => $ok]);
    exit;
}

// ---- Kunde prüft, ob der Inhaber auf seine Nachricht geantwortet hat ----
if ($action === 'find_reply') {
    $vorname  = trim($_GET['vorname'] ?? '');
    $nachname = trim($_GET['nachname'] ?? '');
    $telefon  = trim($_GET['telefon'] ?? '');

    if ($vorname === '' || $nachname === '' || $telefon === '') {
        echo json_encode(['success' => false, 'message' => 'Bitte Vorname, Nachname und Telefonnummer eingeben.']);
        exit;
    }

    $messages = wf_load_messages($config);
    $matches = [];
    foreach ($messages as $m) {
        if (($m['status'] ?? '') !== 'replied') continue;
        if (!wf_customer_matches($m, $vorname, $nachname, $telefon)) continue;
        $matches[] = [
            'note' => $m['note'],
            'admin_reply' => $m['admin_reply'],
            'replied_at' => $m['replied_at'],
            'created_at' => $m['created_at'],
        ];
    }
    usort($matches, fn($x, $y) => strcmp($y['created_at'], $x['created_at']));

    echo json_encode(['success' => true, 'replies' => $matches]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'invalid_action']);