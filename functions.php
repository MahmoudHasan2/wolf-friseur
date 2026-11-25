<?php
// ============================================================
// Wolf Friseur – Buchungssystem – Hilfsfunktionen
// ============================================================

// Zeitzone fest auf Deutschland setzen, damit "jetzt" (für vergangene
// Termine, Stornierungsfristen usw.) immer korrekt berechnet wird,
// unabhängig von der Standard-Zeitzone des Hosting-Servers.
date_default_timezone_set('Europe/Berlin');

function wf_load_config() {
    return require __DIR__ . '/config.php';
}

// Termine sicher lesen (shared lock)
function wf_read_appointments($config) {
    $file = $config['data_file'];
    if (!file_exists($file)) {
        file_put_contents($file, '[]');
    }
    $fp = fopen($file, 'r');
    if (!$fp) return [];
    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

// Termine sicher schreiben (exclusive lock). $callback bekommt die aktuellen
// Daten und muss die neuen Daten zurückgeben, oder false um abzubrechen
// (z.B. wenn der Slot inzwischen nicht mehr frei ist).
function wf_write_appointments($config, $callback) {
    $file = $config['data_file'];
    if (!file_exists($file)) {
        file_put_contents($file, '[]');
    }
    $fp = fopen($file, 'r+');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    $content = stream_get_contents($fp);
    $data = json_decode($content, true);
    if (!is_array($data)) $data = [];
    $result = $callback($data);
    // JSON zuerst erzeugen: schlägt das fehl (z.B. ungültiges UTF-8), bleibt
    // die Datei unverändert, statt geleert zu werden.
    $json = $result === false ? false : json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

function wf_day_key($date) {
    $map = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'];
    $n = (int)date('N', strtotime($date));
    return $map[$n];
}

function wf_business_hours($date, $config) {
    $key = wf_day_key($date);
    return $config['business_hours'][$key] ?? null;
}

function wf_time_to_minutes($t) {
    [$h, $m] = explode(':', $t);
    return ((int)$h) * 60 + (int)$m;
}

function wf_minutes_to_time($mins) {
    $h = intdiv($mins, 60);
    $m = $mins % 60;
    return sprintf('%02d:%02d', $h, $m);
}

// ============================================================
// Terminplanung minutengenau
// ------------------------------------------------------------
// Ein Termin belegt [Beginn, Beginn + Dauer). Die Dauer wird beim Anlegen
// fest im Termin gespeichert ('duration', zusätzlich 'end' als Uhrzeit) und
// später NIE aus der Leistung neu berechnet. Zwei Termine kollidieren genau
// dann, wenn  neuBeginn < altEnde && neuEnde > altBeginn  – ein Termin darf
// also exakt dort beginnen, wo der vorige endet.
// Es gibt kein festes Zeitraster mehr: der Server akzeptiert jede Minute.
// ============================================================

// Abstand der Uhrzeit-Vorschläge auf der Buchungsseite innerhalb einer freien
// Lücke. Nur Anzeige – jede Lücke beginnt zusätzlich exakt am Ende des vorigen
// Termins und endet mit der spätesten noch passenden Startzeit.
function wf_suggestion_step($config) {
    return max(1, (int)($config['suggestion_step_minutes'] ?? 15));
}

function wf_valid_date($date) {
    if (!is_string($date) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) return false;
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

function wf_valid_time($time) {
    return is_string($time) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
}

// Ende (HH:MM) eines Termins aus Beginn + gespeicherter Dauer
function wf_appointment_end($a) {
    return wf_minutes_to_time(wf_time_to_minutes($a['time']) + (int)$a['duration']);
}

// belegte Intervalle an einem Tag (aktive Termine + manuelle Blockierungen),
// aufsteigend nach Beginn. $excludeId: diesen Termin ignorieren (beim Bearbeiten).
function wf_busy_intervals($date, $appointments, $excludeId = null) {
    $intervals = [];
    foreach ($appointments as $a) {
        if ($a['date'] !== $date) continue;
        if (($a['status'] ?? '') === 'cancelled') continue;
        if ($excludeId !== null && $a['id'] === $excludeId) continue;
        $start = wf_time_to_minutes($a['time']);
        $end = $start + (int)$a['duration'];
        $intervals[] = [$start, $end, $a['id']];
    }
    usort($intervals, fn($x, $y) => $x[0] <=> $y[0]);
    return $intervals;
}

function wf_overlaps($s1, $e1, $s2, $e2) {
    return $s1 < $e2 && $s2 < $e1;
}

// Früheste erlaubte Startzeit (Unix-Zeit) für ONLINE-Buchungen:
// jetzt (auf die Minute abgerundet) + Mindestvorlauf aus den Einstellungen.
// Ohne Vorlauf muss der Termin mindestens in der nächsten Minute beginnen.
function wf_online_earliest_ts($config, $now = null) {
    $now = $now ?? time();
    $nowMinute = $now - ($now % 60);
    $lead = max(0, (int)(wf_load_settings($config)['booking_lead_minutes'] ?? 0));
    return $nowMinute + max($lead * 60, 60);
}

// Früheste Startzeit für MANUELLE Termine im Dashboard: die aktuelle Minute
// (kein Mindestvorlauf, aber nicht in der Vergangenheit).
function wf_manual_earliest_ts($now = null) {
    $now = $now ?? time();
    return $now - ($now % 60);
}

// Zentrale Prüfung für JEDEN neuen/verschobenen Termin (Online, manuell,
// Blockierung, Bearbeiten). Muss innerhalb von wf_write_appointments()
// aufgerufen werden, damit gleichzeitige Anfragen sich nicht überholen.
// $opts: 'earliest_ts' => Unix-Zeit oder null (keine Prüfung),
//        'online' => bool (Fehlercode 'lead_time' statt 'past'),
//        'exclude_id' => Termin-ID, die ignoriert wird.
// Rückgabe: null = erlaubt, sonst ein Fehlercode (siehe wf_booking_error_message).
function wf_booking_error($date, $time, $duration, $config, $appointments, $opts = []) {
    $duration = (int)$duration;
    if (!wf_valid_date($date) || !wf_valid_time($time) || $duration < 1) return 'invalid';
    $hours = wf_business_hours($date, $config);
    if (!$hours) return 'closed';
    $start = wf_time_to_minutes($time);
    $end = $start + $duration;
    if ($start < wf_time_to_minutes($hours[0]) || $end > wf_time_to_minutes($hours[1])) return 'outside_hours';
    if (isset($opts['earliest_ts']) && strtotime($date . ' ' . $time) < $opts['earliest_ts']) {
        return !empty($opts['online']) ? 'lead_time' : 'past';
    }
    foreach (wf_busy_intervals($date, $appointments, $opts['exclude_id'] ?? null) as $b) {
        if (wf_overlaps($start, $end, $b[0], $b[1])) return 'overlap';
    }
    return null;
}

function wf_booking_error_message($code, $config = null) {
    switch ($code) {
        case 'invalid':       return 'Bitte ein gültiges Datum, eine gültige Uhrzeit und Dauer angeben.';
        case 'closed':        return 'An diesem Tag ist der Salon geschlossen.';
        case 'outside_hours': return 'Der Termin muss innerhalb der Öffnungszeiten liegen und spätestens zum Ladenschluss enden.';
        case 'past':          return 'Dieser Zeitpunkt liegt in der Vergangenheit.';
        case 'lead_time':     return 'Online-Termine müssen mindestens ' . (int)(wf_load_settings($config)['booking_lead_minutes'] ?? 0) . ' Minuten im Voraus gebucht werden. Bitte wählen Sie eine spätere Uhrzeit.';
        case 'overlap':       return 'Dieser Zeitraum überschneidet sich mit einem anderen Termin.';
        default:              return 'Dieser Termin ist nicht möglich.';
    }
}

// Freie Lücken eines Tages innerhalb der Öffnungszeiten: [[von, bis], ...] in Minuten
function wf_free_gaps($date, $config, $appointments) {
    $hours = wf_business_hours($date, $config);
    if (!$hours) return [];
    $open = wf_time_to_minutes($hours[0]);
    $close = wf_time_to_minutes($hours[1]);
    $gaps = [];
    $cursor = $open;
    foreach (wf_busy_intervals($date, $appointments) as $b) {
        if ($b[0] > $cursor) $gaps[] = [$cursor, min($b[0], $close)];
        $cursor = max($cursor, $b[1]);
        if ($cursor >= $close) break;
    }
    if ($cursor < $close) $gaps[] = [$cursor, $close];
    return array_values(array_filter($gaps, fn($g) => $g[1] > $g[0]));
}

// Buchbare Startzeiten für die Buchungsseite (Liste von 'HH:MM'): feste
// Viertelstunden der Uhr (09:00, 09:15, 09:30 …), aber nur dort, wo die
// komplette Dauer in eine freie Lücke passt. Der Server selbst akzeptiert
// weiterhin jede Minute (z.B. manuelle Termine im Dashboard).
function wf_available_starts($date, $duration, $config, $appointments, $earliestTs = null) {
    $duration = (int)$duration;
    if ($duration < 1 || !wf_valid_date($date)) return [];
    $earliestMin = null;
    if ($earliestTs !== null) {
        $earliestDate = date('Y-m-d', $earliestTs);
        if ($date < $earliestDate) return [];
        if ($date === $earliestDate) {
            $earliestMin = (int)date('G', $earliestTs) * 60 + (int)date('i', $earliestTs) + ((int)date('s', $earliestTs) > 0 ? 1 : 0);
        }
    }
    $step = wf_suggestion_step($config);
    $starts = [];
    foreach (wf_free_gaps($date, $config, $appointments) as [$gapStart, $gapEnd]) {
        $latest = $gapEnd - $duration;
        $first = max($gapStart, $earliestMin ?? $gapStart);
        // auf die nächste volle Viertelstunde der Uhr aufrunden
        for ($t = (int)(ceil($first / $step) * $step); $t <= $latest; $t += $step) $starts[$t] = true;
    }
    ksort($starts);
    return array_map('wf_minutes_to_time', array_keys($starts));
}

function wf_generate_id() {
    return bin2hex(random_bytes(8));
}

// ============================================================
// Admin-Zugangsdaten (admin_credentials.json) – ermöglicht dem
// Inhaber, das Dashboard-Passwort selbst zu ändern, ohne config.php
// zu bearbeiten. Wird beim ersten Zugriff automatisch aus den
// bestehenden Werten in config.php befüllt.
// ============================================================

function wf_credentials_file($config) {
    return dirname($config['data_file']) . '/admin_credentials.json';
}

function wf_load_credentials($config) {
    $default = [
        'username' => $config['admin_username'],
        'password_hash' => $config['admin_password_hash'],
    ];
    return wf_read_json_file(wf_credentials_file($config), $default);
}

function wf_save_credentials($config, $callback) {
    $default = [
        'username' => $config['admin_username'],
        'password_hash' => $config['admin_password_hash'],
    ];
    return wf_write_json_file(wf_credentials_file($config), $callback, $default);
}

// ============================================================
// Generische, sicher gesperrte JSON-Datenspeicher (gleiches Prinzip
// wie wf_read_appointments / wf_write_appointments), wiederverwendet
// für Leistungen, FAQ und Kundennachrichten.
// ============================================================

function wf_read_json_file($file, $default) {
    if (!file_exists($file)) {
        file_put_contents($file, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    $fp = fopen($file, 'r');
    if (!$fp) return $default;
    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($content, true);
    return is_array($data) ? $data : $default;
}

function wf_write_json_file($file, $callback, $default = []) {
    if (!file_exists($file)) {
        file_put_contents($file, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    $fp = fopen($file, 'r+');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    $content = stream_get_contents($fp);
    $data = json_decode($content, true);
    if (!is_array($data)) $data = $default;
    $result = $callback($data);
    $json = $result === false ? false : json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

// ============================================================
// Allgemeine Einstellungen (settings.json) – im Dashboard änderbar
// ============================================================

function wf_settings_file($config) {
    return dirname($config['data_file']) . '/settings.json';
}

function wf_default_settings() {
    return [
        // Online-Buchungen müssen mindestens so viele Minuten im Voraus liegen
        'booking_lead_minutes' => 0,
    ];
}

function wf_load_settings($config) {
    return wf_read_json_file(wf_settings_file($config), wf_default_settings()) + wf_default_settings();
}

function wf_save_settings($config, $callback) {
    return wf_write_json_file(wf_settings_file($config), $callback, wf_default_settings());
}

// ============================================================
// Leistungen (services.json) – ersetzt die früher feste Liste in
// config.php. Kategorie "herr" = im Dashboard verwaltbar (Preis,
// Dauer, Beschreibung). Kategorie "kind" = fester Systemeintrag,
// nicht über das Dashboard veränderbar.
// ============================================================

function wf_services_file($config) {
    return dirname($config['data_file']) . '/services.json';
}

function wf_default_services() {
    return [
        ['key' => 'haare', 'label' => 'Haarschnitt', 'duration' => 30, 'price' => 18,
         'description' => 'Klassischer Haarschnitt, individuell nach Wunsch und Kopfform gestaltet.',
         'category' => 'herr', 'order' => 1],
        ['key' => 'bart', 'label' => 'Bartschnitt', 'duration' => 30, 'price' => 15,
         'description' => 'Präzises Bartschneiden und -formen für ein gepflegtes Erscheinungsbild.',
         'category' => 'herr', 'order' => 2],
        ['key' => 'beide', 'label' => 'Haare & Bart', 'duration' => 60, 'price' => 30,
         'description' => 'Kombination aus Haarschnitt und Bartschnitt in einem entspannten Termin.',
         'category' => 'herr', 'order' => 3],
        ['key' => 'hamam', 'label' => 'Haare mit Hamam', 'duration' => 60, 'price' => 35,
         'description' => 'Haarschnitt inklusive traditioneller Hamam-Haarwäsche und Kopfmassage.',
         'category' => 'herr', 'order' => 4],
        ['key' => 'vip_complete', 'label' => 'VIP Complete', 'duration' => 90, 'price' => 45,
         'description' => 'Rundum-Paket: Haarschnitt, Bartschnitt und Hamam-Haarwäsche in einem Termin.',
         'category' => 'herr', 'order' => 5],
        ['key' => 'vip_paket', 'label' => 'VIP Paket', 'duration' => 90, 'price' => 55,
         'description' => 'Unser exklusivstes Paket: Haare, Bart, Hamam-Wäsche und Gesichtspflege.',
         'category' => 'herr', 'order' => 6],
        ['key' => 'kind', 'label' => 'Kinderhaarschnitt', 'duration' => 30, 'price' => null,
         'description' => '', 'category' => 'kind', 'order' => 1],
    ];
}

function wf_load_services($config) {
    return wf_read_json_file(wf_services_file($config), wf_default_services());
}

// Leistungen als [key => Leistung] für schnellen Zugriff (Buchung/Validierung)
function wf_services_keyed($config) {
    $out = [];
    foreach (wf_load_services($config) as $s) $out[$s['key']] = $s;
    return $out;
}

function wf_save_services($config, $callback) {
    return wf_write_json_file(wf_services_file($config), $callback, wf_default_services());
}

// ============================================================
// Häufige Fragen (faqs.json) – vom Dashboard aus verwaltbar,
// werden dem Kunden nach Auswahl von Leistung + Termin angezeigt.
// ============================================================

function wf_faqs_file($config) {
    return dirname($config['data_file']) . '/faqs.json';
}

function wf_default_faqs() {
    return [
        ['id' => 'faq_default_1', 'order' => 1,
         'question' => 'Muss ich für einen Herren- oder Kinderhaarschnitt vorher einen Termin buchen?',
         'answer' => 'Nein, das ist nicht zwingend nötig. Sie können jederzeit spontan vorbeikommen. Für kürzere Wartezeiten empfehlen wir dennoch eine Online-Buchung.'],
        ['id' => 'faq_default_2', 'order' => 2,
         'question' => 'Kann ich meinen Termin stornieren oder verschieben?',
         'answer' => 'Ja, Sie können Ihren Termin bis spätestens 3 Stunden vorher kostenlos online stornieren. Für eine Verschiebung stornieren Sie den alten Termin und buchen einfach einen neuen.'],
        ['id' => 'faq_default_3', 'order' => 3,
         'question' => 'Was ist im VIP Paket enthalten?',
         'answer' => 'Das VIP Paket umfasst Haarschnitt, Bartschnitt, eine Hamam-Haarwäsche sowie eine abschließende Gesichtspflege.'],
    ];
}

function wf_load_faqs($config) {
    return wf_read_json_file(wf_faqs_file($config), wf_default_faqs());
}

function wf_save_faqs($config, $callback) {
    return wf_write_json_file(wf_faqs_file($config), $callback, wf_default_faqs());
}

// ============================================================
// Kundennachrichten (messages.json) – Rückmeldungen zu den FAQ
// ("War das hilfreich? Nein") landen hier für den Admin.
// status: open (neu) -> seen (vom Admin geöffnet) -> replied (beantwortet)
// ============================================================

function wf_messages_file($config) {
    return dirname($config['data_file']) . '/messages.json';
}

function wf_load_messages($config) {
    return wf_read_json_file(wf_messages_file($config), []);
}

function wf_save_messages($config, $callback) {
    return wf_write_json_file(wf_messages_file($config), $callback, []);
}

// ---- Hilfsfunktionen für Duplikat-Prüfung und Selbst-Stornierung ----

function wf_normalize_name($s) {
    return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string)$s)), 'UTF-8');
}

function wf_normalize_phone($s) {
    return preg_replace('/\D+/', '', (string)$s);
}

function wf_customer_matches($a, $vorname, $nachname, $telefon) {
    return wf_normalize_name($a['vorname']) === wf_normalize_name($vorname)
        && wf_normalize_name($a['nachname']) === wf_normalize_name($nachname)
        && wf_normalize_phone($a['telefon']) === wf_normalize_phone($telefon)
        && wf_normalize_phone($telefon) !== ''; // leere Telefonnummern nie matchen
}

function wf_appointment_timestamp($a) {
    return strtotime($a['date'] . ' ' . $a['time']);
}

// Stunden, die bis zum Termin verbleiben (kann negativ sein, wenn der Termin vorbei ist)
function wf_hours_until($a) {
    return (wf_appointment_timestamp($a) - time()) / 3600;
}

// Prüft, ob am gegebenen Datum bereits ein aktiver (nicht abgesagter) Termin
// für denselben Kunden existiert (Name + Telefon).
function wf_customer_has_booking_on_date($appointments, $date, $vorname, $nachname, $telefon) {
    foreach ($appointments as $a) {
        if ($a['date'] !== $date) continue;
        if (($a['status'] ?? '') === 'cancelled') continue;
        if (($a['type'] ?? '') === 'block') continue;
        if (wf_customer_matches($a, $vorname, $nachname, $telefon)) return true;
    }
    return false;
}

function wf_send_owner_notification($config, $appointment) {
    $to = $config['owner_email'];
    $subject = '=?UTF-8?B?' . base64_encode('Neue Terminbuchung - Wolf Friseur') . '?=';
    $body = "Neue Buchung ueber die Website:\n\n"
        . "Name: {$appointment['vorname']} {$appointment['nachname']}\n"
        . "Telefon: {$appointment['telefon']}\n"
        . "Typ: {$appointment['type_label']}\n"
        . "Leistung: {$appointment['service_label']}\n"
        . "Datum: {$appointment['date']}\n"
        . "Uhrzeit: {$appointment['time']}\n";
    $headers = "From: no-reply@wolf-friseur.de\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($to, $subject, $body, $headers);
}

// ============================================================
// SMS-Benachrichtigungen (Anbieter siehe config.php -> 'sms')
// ============================================================

// Deutsche Nummern ins internationale Format bringen: 0176... -> +49176...
function wf_phone_e164($phone) {
    $p = preg_replace('/[^\d+]/', '', (string)$phone);
    if (strpos($p, '00') === 0) return '+' . substr($p, 2);
    if (strpos($p, '+') === 0) return $p;
    if (strpos($p, '0') === 0) return '+49' . substr($p, 1);
    // Deutsche Mobilnummer ohne führende 0 (z.B. 15100000000) -> +49 151 00000000
    if (preg_match('/^1[5-7]\d{8,9}$/', $p)) return '+49' . $p;
    return $p === '' ? '' : '+' . $p;
}

// Einfache HTTP-POST-Anfrage (Formular-Daten). Liefert ['ok' => bool, 'code' => int, 'body' => string]
function wf_http_post($url, $fields, $headers = [], $basicAuth = null) {
    $payload = http_build_query($fields);
    $body = false;
    $code = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($basicAuth) curl_setopt($ch, CURLOPT_USERPWD, $basicAuth);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        if ($basicAuth) $headers[] = 'Authorization: Basic ' . base64_encode($basicAuth);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $headers) . "\r\n",
            'content'       => $payload,
            'timeout'       => 10,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})/', $http_response_header[0], $m)) {
            $code = (int)$m[1];
        }
    }

    return ['ok' => $body !== false && $code >= 200 && $code < 300, 'code' => $code, 'body' => (string)$body];
}

// SMS versenden. Liefert true/false. Fehler werden ins PHP-Error-Log geschrieben,
// eine fehlgeschlagene SMS darf nie eine Buchung/Aktion verhindern.
function wf_send_sms($config, $to, $text) {
    $sms = $config['sms'] ?? [];
    $provider = $sms['provider'] ?? '';
    $to = wf_phone_e164($to);
    if ($provider === '' || $to === '') return false;
    $sender = trim($sms['sender'] ?? '');

    try {
        if ($provider === 'seven') {
            // Ohne 'from' verwendet seven.io die Standard-Absendernummer
            $fields = ['to' => $to, 'text' => $text];
            if ($sender !== '') $fields['from'] = $sender;
            $res = wf_http_post('https://gateway.seven.io/api/sms', $fields,
                ['X-Api-Key: ' . ($sms['seven_api_key'] ?? ''), 'Accept: application/json']);
            // seven.io antwortet mit HTTP 200; success = "100" bedeutet zugestellt an das Gateway
            $data = json_decode($res['body'], true);
            $ok = $res['ok'] && (string)($data['success'] ?? $res['body']) === '100';
        } elseif ($provider === 'twilio') {
            $sid = $sms['twilio_sid'] ?? '';
            $res = wf_http_post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json",
                ['To' => $to, 'From' => $sender, 'Body' => $text],
                [], $sid . ':' . ($sms['twilio_token'] ?? ''));
            $ok = $res['ok'];
        } else {
            return false;
        }
    } catch (Throwable $e) {
        $res = ['code' => 0, 'body' => $e->getMessage()];
        $ok = false;
    }

    if (!$ok) error_log("Wolf Friseur SMS an {$to} fehlgeschlagen ({$provider}): HTTP {$res['code']} {$res['body']}");
    wf_sms_log($to, $sender, $ok, $res['code'], $res['body']);
    return $ok;
}

// SMS-Protokoll (letzte Versuche inkl. Antwort des Anbieters), abrufbar im
// eingeloggten Zustand über admin-api.php?action=sms_log. Die Datei endet auf
// .php und beendet sich sofort, damit sie im Browser nicht lesbar ist.
define('WF_SMS_LOG_FILE', __DIR__ . '/sms-log.php');

function wf_sms_log($to, $sender, $ok, $code, $body) {
    $line = date('Y-m-d H:i:s') . ' | an ' . $to . ' | von ' . ($sender === '' ? '(Standard)' : $sender)
        . ' | ' . ($ok ? 'OK' : 'FEHLER') . ' | HTTP ' . $code . ' | ' . str_replace(["\r", "\n"], ' ', substr($body, 0, 500));
    if (!file_exists(WF_SMS_LOG_FILE)) {
        @file_put_contents(WF_SMS_LOG_FILE, "<?php http_response_code(404); exit; ?>\n");
    }
    @file_put_contents(WF_SMS_LOG_FILE, $line . "\n", FILE_APPEND | LOCK_EX);
}

function wf_sms_log_read($limit = 50) {
    if (!file_exists(WF_SMS_LOG_FILE)) return [];
    $lines = array_slice(file(WF_SMS_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), 1);
    return array_reverse(array_slice($lines, -$limit));
}

function wf_sms_date($date) {
    $days = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
    $ts = strtotime($date);
    return $days[(int)date('w', $ts)] . ' ' . date('d.m.Y', $ts);
}

// SMS an den Friseur: neue Buchung
// Bewusst kurz, einzeilig und mit Markenpräfix (wie die Kunden-SMS, die zuverlässig
// ankommen). Keine Kundentelefonnummer im Text: Mobilfunk-Spamfilter blocken
// Nachrichten mit fremden Rufnummern/Freitext von Alpha-Absendern gern still.
function wf_sms_owner_new_booking($config, $a) {
    $owner = $config['sms']['owner_phone'] ?? '';
    if ($owner !== '') {
        $first = preg_replace('/[^\p{L} \-]/u', '', (string)($a['vorname'] ?? ''));
        $text = "Wolf Friseur: Neue Terminanfrage " . wf_sms_date($a['date']) . " um {$a['time']} Uhr"
            . ($first !== '' ? " ({$first})" : '') . ". Bitte im Dashboard annehmen oder ablehnen.";
        wf_send_sms($config, $owner, $text);
    }
}

// ============================================================
// Telegram-Benachrichtigung an den Friseur – eigener Kanal, völlig
// unabhängig von SMS. Wird NUR bei neuen Buchungen gesendet (Online-Buchung
// und manueller Termin im Dashboard), nicht bei Absage/Bearbeitung.
//
// Bot-Token: Umgebungsvariable WF_TELEGRAM_BOT_TOKEN oder config.php ->
// 'telegram' => ['bot_token' => '...']. Niemals in JS/öffentliche Dateien.
// Der Chat des Friseurs wird im Dashboard per "Telegram verbinden" verknüpft
// (Einmal-Code, 15 Min gültig) und in telegram.json gespeichert.
// ============================================================

define('WF_TELEGRAM_API', 'https://api.telegram.org');
define('WF_TELEGRAM_LINK_TTL', 900); // Gültigkeit eines Verbindungs-Codes in Sekunden

function wf_telegram_token($config) {
    $env = getenv('WF_TELEGRAM_BOT_TOKEN');
    if (is_string($env) && trim($env) !== '') return trim($env);
    return trim((string)($config['telegram']['bot_token'] ?? ''));
}

function wf_telegram_file($config) {
    return dirname($config['data_file']) . '/telegram.json';
}

function wf_telegram_default_state() {
    return ['chat_id' => null, 'chat_name' => '', 'linked_at' => null, 'linked_admin' => null,
            'pending' => null, 'update_offset' => 0, 'bot_username' => ''];
}

function wf_telegram_state($config) {
    return wf_read_json_file(wf_telegram_file($config), wf_telegram_default_state()) + wf_telegram_default_state();
}

function wf_telegram_save_state($config, $callback) {
    return wf_write_json_file(wf_telegram_file($config), function ($data) use ($callback) {
        return $callback($data + wf_telegram_default_state());
    }, wf_telegram_default_state());
}

// Aufruf der Bot-API. Rückgabe: ['ok' => bool, 'result' => mixed, 'code' => int, 'error' => string]
// Der Token taucht weder im Rückgabewert noch im Log auf.
function wf_telegram_api($config, $method, $params = []) {
    $token = wf_telegram_token($config);
    if ($token === '') return ['ok' => false, 'result' => null, 'code' => 0, 'error' => 'not_configured'];
    try {
        $res = wf_http_post(WF_TELEGRAM_API . "/bot{$token}/{$method}", $params);
    } catch (Throwable $e) {
        $res = ['ok' => false, 'code' => 0, 'body' => $e->getMessage()];
    }
    $data = json_decode($res['body'], true);
    $ok = $res['ok'] && is_array($data) && !empty($data['ok']);
    $error = $ok ? '' : (is_array($data) && isset($data['description']) ? (string)$data['description'] : 'HTTP ' . $res['code']);
    return ['ok' => $ok, 'result' => $data['result'] ?? null, 'code' => $res['code'], 'error' => str_replace($token, '***', $error)];
}

// Empfänger: der im Dashboard verknüpfte Chat (+ optional fest eingetragene
// 'chat_ids' aus config.php, falls dort welche gepflegt sind).
function wf_telegram_recipients($config) {
    $ids = [];
    $state = wf_telegram_state($config);
    if (!empty($state['chat_id'])) $ids[] = (string)$state['chat_id'];
    foreach ((array)($config['telegram']['chat_ids'] ?? []) as $id) {
        $id = trim((string)$id);
        if ($id !== '') $ids[] = $id;
    }
    return array_values(array_unique($ids));
}

function wf_telegram_send_to($config, $chatId, $text) {
    $res = wf_telegram_api($config, 'sendMessage', ['chat_id' => $chatId, 'text' => $text, 'disable_web_page_preview' => 1]);
    if (!$res['ok']) error_log("Wolf Friseur Telegram an {$chatId} fehlgeschlagen: {$res['error']}");
    wf_sms_log('telegram:' . $chatId, 'telegram', $res['ok'], $res['code'], $res['ok'] ? 'ok' : $res['error']);
    return $res['ok'];
}

function wf_format_price($price) {
    if ($price === null || $price === '') return 'nach Absprache';
    return number_format((float)$price, 2, ',', '.') . ' €';
}

// Text der Telegram-Nachricht (ohne Treuekarten-Stempel)
function wf_telegram_booking_text($a) {
    $manual = ($a['source'] ?? '') === 'admin';
    $service = ($a['service_label'] ?? '') . (($a['type'] ?? '') === 'kind' ? ' (Kind)' : '');
    $name = trim(($a['vorname'] ?? '') . ' ' . ($a['nachname'] ?? ''));
    $text = ($manual ? "📝 Neuer Termin (manuell eingetragen)" : "🔔 Neue Online-Buchung") . "\n\n"
        . "Name: " . ($name !== '' ? $name : '–') . "\n"
        . "Telefon: " . (trim($a['telefon'] ?? '') !== '' ? $a['telefon'] : '–') . "\n"
        . "Leistung: {$service}\n"
        . "Datum: " . wf_sms_date($a['date']) . "\n"
        . "Beginn: {$a['time']} Uhr\n"
        . "Ende: " . wf_appointment_end($a) . " Uhr\n"
        . "Dauer: " . (int)$a['duration'] . " Min\n"
        . "Preis: " . wf_format_price($a['price'] ?? null);
    if (($a['status'] ?? '') === 'pending') $text .= "\n\nBitte im Dashboard annehmen oder ablehnen.";
    return $text;
}

// Benachrichtigung über eine NEUE Buchung. Wirft nie eine Exception und
// blockiert weder die Buchung noch die SMS. Keine automatische Wiederholung.
// Rückgabe: 'sent' | 'not_configured' | 'not_linked' | 'failed'
function wf_telegram_notify_new_booking($config, $a) {
    try {
        if (wf_telegram_token($config) === '') return 'not_configured';
        $recipients = wf_telegram_recipients($config);
        if (!$recipients) return 'not_linked';
        $text = wf_telegram_booking_text($a);
        $allOk = true;
        foreach ($recipients as $chatId) {
            if (!wf_telegram_send_to($config, $chatId, $text)) $allOk = false;
        }
        return $allOk ? 'sent' : 'failed';
    } catch (Throwable $e) {
        error_log('Wolf Friseur Telegram Fehler: ' . $e->getMessage());
        return 'failed';
    }
}

// ---- Verknüpfung per Einmal-Code ----

function wf_telegram_session_hash() {
    return hash('sha256', 'wf-tg|' . session_id());
}

function wf_telegram_bot_username($config) {
    $state = wf_telegram_state($config);
    if ($state['bot_username'] !== '') return $state['bot_username'];
    $res = wf_telegram_api($config, 'getMe');
    $username = $res['ok'] ? (string)($res['result']['username'] ?? '') : '';
    if ($username !== '') {
        wf_telegram_save_state($config, function ($s) use ($username) { $s['bot_username'] = $username; return $s; });
    }
    return $username;
}

// Neuen Verbindungs-Code für die aktuelle Admin-Sitzung erzeugen. Ein evtl.
// älterer Code wird dadurch ungültig. Gespeichert wird nur der Hash.
// Rückgabe: ['ok' => bool, 'url' => string, 'message' => string]
function wf_telegram_start_link($config, $adminUsername) {
    if (wf_telegram_token($config) === '') {
        return ['ok' => false, 'message' => 'Telegram ist auf dem Server noch nicht eingerichtet (Bot-Token fehlt).'];
    }
    $bot = wf_telegram_bot_username($config);
    if ($bot === '') {
        return ['ok' => false, 'message' => 'Der Telegram-Bot ist nicht erreichbar. Bitte Bot-Token prüfen.'];
    }
    $code = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    $pending = [
        'code_hash' => hash('sha256', $code),
        'expires' => time() + WF_TELEGRAM_LINK_TTL,
        'session' => wf_telegram_session_hash(),
        'admin' => $adminUsername,
    ];
    $ok = wf_telegram_save_state($config, function ($s) use ($pending) { $s['pending'] = $pending; return $s; });
    if (!$ok) return ['ok' => false, 'message' => 'Speichern fehlgeschlagen.'];
    return ['ok' => true, 'url' => 'https://t.me/' . rawurlencode($bot) . '?start=' . $code];
}

// Holt neue Nachrichten an den Bot (getUpdates – kein Webhook nötig) und
// verknüpft den Chat, der "/start <Code>" mit dem gültigen Code der
// aktuellen Admin-Sitzung geschickt hat. Code ist danach verbraucht.
// Rückgabe: 'linked' | 'waiting' | 'expired' | 'none' | 'error'
function wf_telegram_poll_link($config) {
    $result = 'none';
    $linkedChat = null;
    wf_telegram_save_state($config, function ($s) use ($config, &$result, &$linkedChat) {
        $p = $s['pending'];
        if (!$p || !hash_equals((string)$p['session'], wf_telegram_session_hash())) { $result = 'none'; return false; }
        if (time() > (int)$p['expires']) { $s['pending'] = null; $result = 'expired'; return $s; }
        $res = wf_telegram_api($config, 'getUpdates', [
            'offset' => (int)$s['update_offset'], 'timeout' => 0, 'allowed_updates' => json_encode(['message']),
        ]);
        if (!$res['ok']) { $result = 'error'; error_log('Wolf Friseur Telegram getUpdates: ' . $res['error']); return false; }
        $result = 'waiting';
        foreach ((array)$res['result'] as $u) {
            $s['update_offset'] = max((int)$s['update_offset'], (int)($u['update_id'] ?? 0) + 1);
            $msg = $u['message'] ?? null;
            if (!$msg || ($msg['chat']['type'] ?? '') !== 'private') continue;
            if (!preg_match('/^\/start\s+([A-Za-z0-9_-]{20,64})$/', trim((string)($msg['text'] ?? '')), $m)) continue;
            if ($s['pending'] && hash_equals($s['pending']['code_hash'], hash('sha256', $m[1]))) {
                $s['chat_id'] = (string)$msg['chat']['id'];
                $from = $msg['from'] ?? [];
                $s['chat_name'] = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''))
                    . (!empty($from['username']) ? ' (@' . $from['username'] . ')' : '');
                $s['linked_at'] = date('c');
                $s['linked_admin'] = $s['pending']['admin'];
                $s['pending'] = null; // Einmal-Code verbraucht
                $linkedChat = $s['chat_id'];
                $result = 'linked';
            }
        }
        return $s;
    });
    if ($linkedChat !== null) {
        wf_telegram_send_to($config, $linkedChat, "✅ Wolf Friseur: Telegram ist verbunden. Sie erhalten hier ab jetzt eine Nachricht bei jeder neuen Buchung.");
    }
    return $result;
}

function wf_telegram_unlink($config) {
    $old = null;
    $ok = wf_telegram_save_state($config, function ($s) use (&$old) {
        $old = $s['chat_id'];
        $s['chat_id'] = null; $s['chat_name'] = ''; $s['linked_at'] = null; $s['linked_admin'] = null; $s['pending'] = null;
        return $s;
    });
    if ($ok && $old) {
        wf_telegram_send_to($config, $old, "Wolf Friseur: Telegram wurde im Dashboard getrennt. Sie erhalten hier keine Buchungen mehr.");
    }
    return $ok;
}

// ============================================================
// Archiv & Aufbewahrung
// ------------------------------------------------------------
// Das Archiv nutzt dieselbe appointments.json (kein zweiter Speicher).
// Archiv = vergangene, abgesagte und nicht erschienene Termine (ohne
// Blockierungen). Jeder Termin wird 1 Jahr ab ERSTELLUNG ('created_at')
// aufbewahrt und danach durch wf_purge_expired_appointments() gelöscht.
// ============================================================

define('WF_RETENTION', '-1 year');

function wf_archive_status($a, $now = null) {
    $now = $now ?? time();
    $status = $a['status'] ?? '';
    if ($status === 'cancelled') return 'cancelled';
    if ($status === 'no_show') return 'no_show';
    $endTs = strtotime($a['date'] . ' ' . wf_appointment_end($a));
    if (!empty($a['checked_in'])) return $endTs <= $now ? 'completed' : null;
    return $endTs <= $now ? 'past_open' : null;
}

// Telefonnummer ohne Ländervorwahl/führende 0 – damit "+49 151…", "0049151…"
// und "0151…" bei der Archivsuche dieselbe Nummer finden (auch als Teilstück).
function wf_phone_core($phone) {
    $raw = trim((string)$phone);
    $d = preg_replace('/\D+/', '', $raw);
    if (strpos($d, '0049') === 0) return substr($d, 4);
    if (strpos($d, '49') === 0 && (strpos($raw, '+') === 0 || strlen($d) > 10)) return substr($d, 2);
    if (strpos($d, '0') === 0) return substr($d, 1);
    return $d;
}

// $f: name, phone, date, from, to, status ('' = alle), page, per_page
function wf_archive_search($appointments, $f, $now = null) {
    $now = $now ?? time();
    $name = wf_normalize_name($f['name'] ?? '');
    $phone = wf_phone_core($f['phone'] ?? '');
    $rows = [];
    foreach ($appointments as $a) {
        if (($a['type'] ?? '') === 'block') continue;
        $st = wf_archive_status($a, $now);
        if ($st === null) continue;
        if (!empty($f['status']) && $f['status'] !== $st) continue;
        if (!empty($f['date']) && $a['date'] !== $f['date']) continue;
        if (!empty($f['from']) && $a['date'] < $f['from']) continue;
        if (!empty($f['to']) && $a['date'] > $f['to']) continue;
        if ($name !== '' && mb_strpos(wf_normalize_name(($a['vorname'] ?? '') . ' ' . ($a['nachname'] ?? '')), $name) === false) continue;
        if ($phone !== '' && strpos(wf_phone_core($a['telefon'] ?? ''), $phone) === false) continue;
        $rows[] = [
            'id' => $a['id'], 'date' => $a['date'], 'time' => $a['time'], 'end' => wf_appointment_end($a),
            'duration' => (int)$a['duration'], 'service_label' => $a['service_label'] ?? '', 'type' => $a['type'] ?? '',
            'vorname' => $a['vorname'] ?? '', 'nachname' => $a['nachname'] ?? '', 'telefon' => $a['telefon'] ?? '',
            'price' => array_key_exists('price', $a) ? $a['price'] : null, 'has_price' => array_key_exists('price', $a),
            'archive_status' => $st, 'rejected' => !empty($a['rejected']), 'cancelled_by' => $a['cancelled_by'] ?? '',
            'source' => $a['source'] ?? '', 'created_at' => $a['created_at'] ?? '',
        ];
    }
    usort($rows, fn($x, $y) => [$y['date'], $y['time']] <=> [$x['date'], $x['time']]);
    $perPage = max(1, min(200, (int)($f['per_page'] ?? 50)));
    $total = count($rows);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($pages, (int)($f['page'] ?? 1)));
    return ['items' => array_slice($rows, ($page - 1) * $perPage, $perPage), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
}

// true, wenn der Termin vor mehr als einem Jahr ERSTELLT wurde. Termine ohne
// lesbares Erstelldatum werden sicherheitshalber nie automatisch gelöscht.
function wf_is_expired($a, $now = null) {
    $created = $a['created_at'] ?? '';
    if (!is_string($created) || $created === '') return false;
    try {
        $createdDt = new DateTime($created);
    } catch (Throwable $e) {
        return false;
    }
    $cutoff = (new DateTime('@' . ($now ?? time())))->modify(WF_RETENTION);
    return $createdDt < $cutoff;
}

// Löscht abgelaufene Termine (1 Jahr nach Erstellung). Rückgabe: Anzahl gelöschter Termine.
function wf_purge_expired_appointments($config, $now = null) {
    $removed = 0;
    wf_write_appointments($config, function ($data) use ($now, &$removed) {
        $keep = array_values(array_filter($data, fn($a) => !wf_is_expired($a, $now)));
        $removed = count($data) - count($keep);
        return $removed > 0 ? $keep : false; // nichts zu tun -> Datei nicht anfassen
    });
    if ($removed > 0) error_log("Wolf Friseur: {$removed} Termin(e) nach Ablauf der Aufbewahrungsfrist gelöscht.");
    return $removed;
}

// Höchstens einmal pro Tag (z.B. beim Öffnen des Dashboards), falls kein Cronjob läuft
function wf_purge_daily($config) {
    $today = date('Y-m-d');
    $settings = wf_load_settings($config);
    if (($settings['last_purge_date'] ?? '') === $today) return 0;
    wf_save_settings($config, function ($s) use ($today) { $s['last_purge_date'] = $today; return $s; });
    // App-Nachrichten: gleiche Aufbewahrungsfrist (1 Jahr nach letzter Aktivität)
    if (function_exists('wf_app_purge_conversations')) {
        try { wf_app_purge_conversations($config); } catch (Throwable $e) { error_log('Wolf Friseur App-Chats Bereinigung: ' . $e->getMessage()); }
    }
    return wf_purge_expired_appointments($config);
}

// SMS an den Kunden: Termin angenommen ($accepted = true) oder abgelehnt
function wf_sms_customer_decision($config, $a, $accepted) {
    $when = wf_sms_date($a['date']) . " um {$a['time']} Uhr";
    if ($accepted) {
        $text = "Wolf Friseur: Ihre Terminanfrage für {$when} wurde angenommen. "
            . "Wir freuen uns auf Ihren Besuch.";
    } else {
        $text = "Wolf Friseur: Ihre Terminanfrage für {$when} konnte leider nicht angenommen werden. "
            . "Bitte wählen Sie online eine andere Zeit.";
    }
    return wf_send_sms($config, $a['telefon'], $text);
}

// ============================================================
// Treuekarte (scan.html / card.html / register.html) – Anbindung
// ============================================================
// Die Treuekarte speichert ihre Kunden in der Firebase Realtime
// Database unter "customers/<Telefon-Schlüssel>". Dieselben Daten
// werden hier serverseitig (per REST) genutzt, damit:
//  1) jeder Kunde, der über termin.php bucht, automatisch ein
//     Treuekarten-Konto bekommt (falls er noch keins hat), und
//  2) der Klick auf "Bestätigen" im Dashboard genau wie ein
//     QR-Scan in scan.html einen Besuch (Häkchen) einträgt.
// Belohnung nach 10 Besuchen: 7 € Rabatt auf den Haarschnitt.
// ============================================================

// Umgebungsvariable nur für Testumgebungen (verhindert Zugriffe auf die echte Datenbank)
define('WF_FIREBASE_DB_URL', getenv('WF_FIREBASE_DB_URL') ?: 'https://your-firebase-project-default-rtdb.europe-west1.firebasedatabase.app');
define('WF_LOYALTY_REWARD_EUR', 7);
define('WF_LOYALTY_VISITS', 10);

// HTTP-Anfrage an die Firebase-REST-API. Liefert ['ok' => bool, 'data' => mixed]
function wf_fb_request($method, $path, $data = null) {
    $url = WF_FIREBASE_DB_URL . '/' . ltrim($path, '/') . '.json';
    $payload = $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE);
    $body = false;
    $code = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => $method,
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $payload ?? '',
            'timeout'       => 10,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})/', $http_response_header[0], $m)) {
            $code = (int)$m[1];
        }
    }

    if ($body === false || $code < 200 || $code >= 300) {
        return ['ok' => false, 'data' => null];
    }
    return ['ok' => true, 'data' => json_decode($body, true)];
}

// Gleicher Schlüssel wie toKey() in scan.html / register.html / card.html
function wf_loyalty_key($phone) {
    $k = preg_replace('/[\s\+\-\(\)]/u', '', (string)$phone);
    return preg_replace('/[\.\$#\[\]\/]/', '', $k); // in Firebase-Schlüsseln verbotene Zeichen
}

// Mögliche Schreibweisen derselben deutschen Nummer (0171…, 49171…, 0049171…)
function wf_loyalty_key_variants($key) {
    $v = [$key];
    if (preg_match('/^0049(\d+)$/', $key, $m)) {
        $v[] = '49' . $m[1]; $v[] = '0' . $m[1];
    } elseif (preg_match('/^49(\d+)$/', $key, $m)) {
        $v[] = '0' . $m[1]; $v[] = '0049' . $m[1];
    } elseif (preg_match('/^0([1-9]\d+)$/', $key, $m)) {
        $v[] = '49' . $m[1]; $v[] = '0049' . $m[1];
    }
    return array_values(array_unique($v));
}

// Sucht ein bestehendes Treuekarten-Konto zur Telefonnummer.
// Rückgabe: ['ok' => bool, 'key' => string|null, 'data' => array|null]
function wf_loyalty_find($phone) {
    $key = wf_loyalty_key($phone);
    if ($key === '') return ['ok' => false, 'key' => null, 'data' => null];
    foreach (wf_loyalty_key_variants($key) as $k) {
        $r = wf_fb_request('GET', 'customers/' . rawurlencode($k));
        if (!$r['ok']) return ['ok' => false, 'key' => null, 'data' => null];
        if (is_array($r['data'])) return ['ok' => true, 'key' => $k, 'data' => $r['data']];
    }
    return ['ok' => true, 'key' => null, 'data' => null];
}

// Standard-Passwort, falls der Kunde bei der Buchung keins angibt:
// die letzten 4 Ziffern seiner Mobilnummer.
function wf_loyalty_default_password($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    return strlen($digits) >= 4 ? substr($digits, -4) : $digits;
}

// Legt ein Treuekarten-Konto an, falls es noch keins gibt.
// Rückgabe: ['status' => 'created'|'exists'|'error', 'key' => ..., 'data' => ..., 'password_default' => bool]
function wf_loyalty_ensure_account($name, $phone, $password = '') {
    $found = wf_loyalty_find($phone);
    if (!$found['ok']) return ['status' => 'error', 'key' => null, 'data' => null, 'password_default' => false];
    if ($found['key'] !== null) {
        return ['status' => 'exists', 'key' => $found['key'], 'data' => $found['data'], 'password_default' => false];
    }

    $key = wf_loyalty_key($phone);
    $usedDefault = false;
    $password = (string)$password;
    if (mb_strlen($password) < 4) {
        $password = wf_loyalty_default_password($phone);
        $usedDefault = true;
    }
    $data = [
        'name'      => trim($name),
        'phone'     => trim($phone),
        'password'  => $password,
        'visits'    => 0,
        'freeReady' => false,
        'createdAt' => (int)round(microtime(true) * 1000),
        'source'    => 'termin',
    ];
    $r = wf_fb_request('PUT', 'customers/' . rawurlencode($key), $data);
    if (!$r['ok']) return ['status' => 'error', 'key' => null, 'data' => null, 'password_default' => false];
    return ['status' => 'created', 'key' => $key, 'data' => $data, 'password_default' => $usedDefault];
}

function wf_ms_to_date($ms) {
    if (empty($ms)) return null;
    return date('Y-m-d', (int)floor(((float)$ms) / 1000));
}

// Trägt einen Besuch ein – exakt dieselbe Logik wie ein QR-Scan in scan.html:
//  * Besuch 1–9  -> Häkchen
//  * Besuch 10   -> Belohnung verdient (freeReady = true)
//  * nächster Besuch bei freeReady -> 7 € Rabatt wird eingelöst, neuer Zyklus beginnt (Besuch 1)
//  * pro Kunde höchstens ein Besuch pro Tag (auch wenn er zusätzlich den QR-Code scannt)
// Rückgabe: ['ok' => bool, 'event' => 'stamp'|'reward_earned'|'redeemed'|'already_today', 'visits' => int, 'account' => 'created'|'exists', 'password_default' => bool]
function wf_loyalty_add_visit($name, $phone) {
    $acc = wf_loyalty_ensure_account($name, $phone, '');
    if ($acc['status'] === 'error') return ['ok' => false];

    $key = $acc['key'];
    $d = $acc['data'];
    $visits = (int)($d['visits'] ?? 0);
    $freeReady = !empty($d['freeReady']);
    $today = date('Y-m-d');
    $now = (int)round(microtime(true) * 1000);
    $base = ['ok' => true, 'account' => $acc['status'], 'password_default' => $acc['password_default']];

    if (wf_ms_to_date($d['lastVisit'] ?? null) === $today || wf_ms_to_date($d['lastRedeem'] ?? null) === $today) {
        return $base + ['event' => 'already_today', 'visits' => $visits, 'free_ready' => $freeReady];
    }

    if ($freeReady) {
        $patch = ['visits' => 1, 'freeReady' => false, 'freeUsed' => (int)($d['freeUsed'] ?? 0) + 1, 'lastRedeem' => $now];
        $event = 'redeemed';
        $newVisits = 1;
    } else {
        $newVisits = $visits + 1;
        if ($newVisits >= WF_LOYALTY_VISITS) {
            $newVisits = WF_LOYALTY_VISITS;
            $patch = ['visits' => $newVisits, 'freeReady' => true, 'lastVisit' => $now];
            $event = 'reward_earned';
        } else {
            $patch = ['visits' => $newVisits, 'lastVisit' => $now];
            $event = 'stamp';
        }
    }

    $r = wf_fb_request('PATCH', 'customers/' . rawurlencode($key), $patch);
    if (!$r['ok']) return ['ok' => false];
    return $base + ['event' => $event, 'visits' => $newVisits, 'free_ready' => $event === 'reward_earned'];
}
