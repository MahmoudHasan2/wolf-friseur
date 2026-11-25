<?php
// ============================================================
// Wolf Friseur – API für die Mobile Apps (Android / iOS)
// ------------------------------------------------------------
// Aufruf: app-api.php?r=<route>   (JSON rein, JSON raus)
// Anmeldung: Authorization: Bearer <token>  (Fallback-Header: X-WF-Token)
// Antwort:   {"ok": true, ...}  oder  {"ok": false, "error": "<code>", "message": "<Text>"}
// Die Website (termin.php / booking-api.php) bleibt davon unberührt.
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require __DIR__ . '/app-lib.php';
$config = wf_load_config();

$route = (string)($_GET['r'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ---- Hilfsfunktionen für Antworten ----

function wf_api_out($data, $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

function wf_api_messages() {
    return [
        'invalid_request'     => 'Ungültige Anfrage.',
        'not_found'           => 'Nicht gefunden.',
        'unauthorized'        => 'Bitte melden Sie sich erneut an.',
        'rate_limited'        => 'Zu viele Versuche. Bitte warten Sie einige Minuten.',
        'invalid_phone'       => 'Bitte geben Sie eine gültige Mobilnummer ein.',
        'invalid_code'        => 'Der Code ist nicht korrekt.',
        'code_expired'        => 'Der Code ist abgelaufen. Bitte fordern Sie einen neuen an.',
        'too_many_attempts'   => 'Zu viele Fehlversuche. Bitte fordern Sie einen neuen Code an.',
        'name_required'       => 'Bitte Vor- und Nachnamen angeben.',
        'invalid_type'        => 'Bitte auswählen: Herr oder Kind.',
        'invalid_service'     => 'Bitte eine Leistung auswählen.',
        'invalid'             => 'Bitte Datum und Uhrzeit auswählen.',
        'out_of_range'        => 'Dieses Datum liegt außerhalb des Buchungszeitraums.',
        'duplicate'           => 'Für diesen Tag besteht bereits ein Termin. Pro Tag ist nur ein Termin pro Person möglich.',
        'overlap'             => 'Dieser Termin wurde leider gerade eben vergeben. Bitte wählen Sie eine andere Uhrzeit.',
        'closed'              => 'An diesem Tag ist der Salon geschlossen.',
        'outside_hours'       => 'Der Termin muss innerhalb der Öffnungszeiten liegen.',
        'already_cancelled'   => 'Dieser Termin ist bereits abgesagt.',
        'not_changeable'      => 'Dieser Termin kann nicht mehr geändert werden.',
        'change_pending'      => 'Für diesen Termin wartet bereits eine Änderungsanfrage auf Bestätigung.',
        'empty_message'       => 'Bitte eine Nachricht eingeben.',
        'invalid_request_id'  => 'Ungültige Anfrage-ID.',
        'profile_incomplete'  => 'Bitte vervollständigen Sie zuerst Ihr Profil (Vor- und Nachname).',
        'invalid_device'      => 'Ungültiges Gerät.',
        'save_failed'         => 'Speichern fehlgeschlagen. Bitte erneut versuchen.',
        'server_error'        => 'Serverfehler. Bitte später erneut versuchen.',
    ];
}

function wf_api_fail($error, $code = 400, $message = null, $extra = []) {
    global $config;
    if ($message === null) {
        if ($error === 'lead_time' || $error === 'past') $message = wf_booking_error_message($error, $config);
        elseif ($error === 'too_late') $message = "Eine Online-Änderung ist ab {$config['self_cancel_min_hours']} Stunden vor dem Termin nicht mehr möglich. Bitte rufen Sie uns an: {$config['salon_phone']}.";
        else $message = wf_api_messages()[$error] ?? 'Aktion nicht möglich.';
    }
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $error, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

// Geschäftsfehler -> HTTP-Status
function wf_api_status_for($error) {
    $map = ['not_found' => 404, 'rate_limited' => 429, 'overlap' => 409, 'duplicate' => 409, 'change_pending' => 409,
            'already_cancelled' => 409, 'save_failed' => 500];
    return $map[$error] ?? 422;
}

// JSON-Körper (oder Formular-Daten) lesen
function wf_api_input() {
    static $in = null;
    if ($in !== null) return $in;
    $raw = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false && strlen($raw) > 64 * 1024) wf_api_fail('invalid_request', 413);
    $json = $raw ? json_decode($raw, true) : null;
    $in = is_array($json) ? $json : $_POST;
    return $in;
}

function wf_api_str($key, $max = 200) {
    $v = wf_api_input()[$key] ?? ($_GET[$key] ?? '');
    return is_scalar($v) ? mb_substr(trim((string)$v), 0, $max) : '';
}

function wf_api_require_method($m) {
    global $method;
    if ($method !== $m) wf_api_fail('invalid_request', 405);
}

function wf_api_auth() {
    global $config;
    $auth = wf_app_authenticate($config, wf_app_request_token());
    if (!$auth) wf_api_fail('unauthorized', 401);
    // Grobe Bremse gegen Missbrauch pro Konto
    if (!wf_app_rate_hit($config, 'api|' . $auth['customer']['id'], 600, 600)) wf_api_fail('rate_limited', 429);
    return $auth;
}

function wf_api_require_profile($customer) {
    if (trim($customer['vorname'] ?? '') === '' || trim($customer['nachname'] ?? '') === '') wf_api_fail('profile_incomplete', 422);
}

try {

// ============================================================
// Öffentlich (ohne Anmeldung)
// ============================================================

if ($route === 'config') {
    $settings = wf_load_settings($config);
    $hours = [];
    foreach ($config['business_hours'] as $day => $h) $hours[$day] = $h ? ['open' => $h[0], 'close' => $h[1]] : null;
    wf_api_out([
        'api_version' => WF_APP_API_VERSION,
        'salon' => [
            'name' => 'Wolf Friseur',
            'phone' => $config['salon_phone'],
            'address' => 'Musterstraße 1, 38547 Calberlah',
            'website' => 'https://wolf-friseur.de/',
            'privacy_url' => 'https://wolf-friseur.de/datenschutz.html',
            'imprint_url' => 'https://wolf-friseur.de/impressum.html',
        ],
        'business_hours' => $hours,
        'booking_window_days' => (int)$config['booking_window_days'],
        'booking_lead_minutes' => (int)$settings['booking_lead_minutes'],
        'self_cancel_min_hours' => (float)$config['self_cancel_min_hours'],
        'bookings_need_confirmation' => true,
        'today' => date('Y-m-d'),
        'server_time' => date('c'),
        'timezone' => 'Europe/Berlin',
    ]);
}

if ($route === 'services') {
    $services = wf_load_services($config);
    usort($services, fn($a, $b) => [($a['category'] ?? '') === 'kind' ? 1 : 0, $a['order'] ?? 0] <=> [($b['category'] ?? '') === 'kind' ? 1 : 0, $b['order'] ?? 0]);
    wf_api_out(['services' => array_map(fn($s) => [
        'key' => $s['key'],
        'label' => $s['label'],
        'duration' => (int)$s['duration'],
        'price' => $s['price'] === null ? null : (float)$s['price'],
        'price_label' => wf_format_price($s['price']),
        'description' => $s['description'] ?? '',
        'category' => $s['category'] ?? 'herr',
    ], $services)]);
}

if ($route === 'days') {
    [$minDate] = wf_app_booking_window($config);
    $days = [];
    for ($i = 0; $i <= (int)$config['booking_window_days']; $i++) {
        $date = date('Y-m-d', strtotime("{$minDate} +{$i} day"));
        $h = wf_business_hours($date, $config);
        $days[] = ['date' => $date, 'closed' => $h === null, 'open' => $h[0] ?? null, 'close' => $h[1] ?? null];
    }
    wf_api_out(['days' => $days]);
}

// Freie Startzeiten – exakt dieselbe Berechnung wie booking-api.php?action=slots
if ($route === 'slots') {
    $date = wf_api_str('date', 10);
    $type = wf_api_str('type', 10);
    $serviceKey = $type === 'kind' ? 'kind' : wf_api_str('service', 60);
    $services = wf_services_keyed($config);
    if (!isset($services[$serviceKey])) wf_api_fail('invalid_service', 422);
    if (!wf_valid_date($date)) wf_api_fail('invalid', 422);
    $duration = (int)$services[$serviceKey]['duration'];
    [$minDate, $maxDate] = wf_app_booking_window($config);
    if ($date < $minDate || $date > $maxDate) wf_api_out(['date' => $date, 'slots' => [], 'closed' => false, 'out_of_range' => true, 'duration' => $duration]);
    $closed = wf_business_hours($date, $config) === null;
    $starts = $closed ? [] : wf_available_starts($date, $duration, $config, wf_read_appointments($config), wf_online_earliest_ts($config));
    wf_api_out([
        'date' => $date,
        'closed' => $closed,
        'out_of_range' => false,
        'duration' => $duration,
        'slots' => array_map(fn($t) => ['time' => $t, 'end' => wf_minutes_to_time(wf_time_to_minutes($t) + $duration)], $starts),
    ]);
}

// ============================================================
// Anmeldung
// ============================================================

if ($route === 'auth/request_code') {
    wf_api_require_method('POST');
    $r = wf_app_request_code($config, wf_api_str('phone', 40));
    if (!$r['ok']) wf_api_fail($r['error'], wf_api_status_for($r['error']));
    $out = ['message' => 'Wir haben Ihnen einen Code per SMS geschickt.', 'code_length' => 6];
    if (isset($r['debug_code'])) $out['debug_code'] = $r['debug_code'];
    wf_api_out($out);
}

if ($route === 'auth/verify') {
    wf_api_require_method('POST');
    $r = wf_app_verify_code($config, wf_api_str('phone', 40), wf_api_str('code', 12));
    if (!$r['ok']) wf_api_fail($r['error'], $r['error'] === 'rate_limited' ? 429 : 401);
    $session = wf_app_create_session($config, $r['customer']['id'], wf_api_str('device_name', 80));
    wf_api_out(['token' => $session['token'], 'expires_at' => $session['expires_at'], 'customer' => wf_app_customer_public($r['customer'])]);
}

// ============================================================
// Ab hier nur mit gültiger Sitzung
// ============================================================

if ($route === 'auth/logout') {
    wf_api_require_method('POST');
    $auth = wf_api_auth();
    $push = wf_api_str('push_token', 4096);
    if ($push !== '') wf_app_unregister_device($config, $auth['customer']['id'], $push);
    wf_app_revoke_session($config, $auth['session_hash']);
    wf_api_out([]);
}

if ($route === 'me') {
    $auth = wf_api_auth();
    $c = $auth['customer'];
    if ($method === 'POST') {
        $in = wf_api_input();
        $vorname = array_key_exists('vorname', $in) ? wf_app_clean_name($in['vorname']) : null;
        $nachname = array_key_exists('nachname', $in) ? wf_app_clean_name($in['nachname']) : null;
        if (($vorname !== null && $vorname === '') || ($nachname !== null && $nachname === '')) wf_api_fail('name_required', 422);
        $prefs = isset($in['prefs']) && is_array($in['prefs']) ? $in['prefs'] : null;
        $updated = null;
        wf_app_write($config, 'customers.json', function ($data) use ($c, $vorname, $nachname, $prefs, &$updated) {
            foreach ($data as &$x) {
                if ($x['id'] !== $c['id']) continue;
                if ($vorname !== null) $x['vorname'] = $vorname;
                if ($nachname !== null) $x['nachname'] = $nachname;
                if ($prefs !== null) {
                    $cur = ($x['prefs'] ?? []) + wf_app_default_prefs();
                    foreach (array_keys(wf_app_default_prefs()) as $k) if (array_key_exists($k, $prefs)) $cur[$k] = (bool)$prefs[$k];
                    $x['prefs'] = $cur;
                }
                $updated = $x;
            }
            unset($x);
            return $updated ? $data : false;
        }, []);
        if (!$updated) wf_api_fail('save_failed', 500);
        $c = $updated;
    }
    wf_api_out(['customer' => wf_app_customer_public($c), 'unread_messages' => wf_app_customer_unread_total($config, $c)]);
}

if ($route === 'account/delete') {
    wf_api_require_method('POST');
    $auth = wf_api_auth();
    wf_app_delete_customer($config, $auth['customer']['id']);
    wf_api_out(['message' => 'Ihr App-Konto wurde gelöscht.']);
}

// ---- Termine ----

if ($route === 'appointments') {
    $auth = wf_api_auth();
    $all = wf_app_customer_appointments($config, $auth['customer']);
    $upcoming = array_values(array_filter($all, fn($a) => $a['is_upcoming']));
    $past = array_reverse(array_values(array_filter($all, fn($a) => !$a['is_upcoming'] && in_array($a['status'], ['completed', 'past', 'no_show'], true))));
    $cancelled = array_reverse(array_values(array_filter($all, fn($a) => in_array($a['status'], ['cancelled', 'rejected', 'rescheduled'], true))));
    wf_api_out(['upcoming' => $upcoming, 'past' => $past, 'cancelled' => $cancelled]);
}

if ($route === 'appointment') {
    $auth = wf_api_auth();
    $a = wf_app_find_owned_appointment($config, $auth['customer'], wf_api_str('id', 40));
    if (!$a) wf_api_fail('not_found', 404);
    wf_api_out(['appointment' => $a]);
}

if ($route === 'appointments/book') {
    wf_api_require_method('POST');
    $auth = wf_api_auth();
    $c = $auth['customer'];
    wf_api_require_profile($c);
    if (!wf_app_rate_hit($config, 'book|' . $c['id'], 10, 3600)) wf_api_fail('rate_limited', 429);
    $in = wf_api_input();
    $in['vorname'] = ($in['vorname'] ?? '') !== '' ? $in['vorname'] : $c['vorname'];
    $in['nachname'] = ($in['nachname'] ?? '') !== '' ? $in['nachname'] : $c['nachname'];
    $r = wf_app_book($config, $c, $in, wf_api_str('request_id', 64));
    if (!$r['ok']) wf_api_fail($r['error'], wf_api_status_for($r['error']));
    if (!$r['replayed']) wf_app_notify_owner_new_booking($config, $r['appointment']);
    $all = wf_read_appointments($config);
    wf_api_out(['appointment' => wf_app_appointment_public($config, $r['appointment'], $all), 'replayed' => $r['replayed'],
        'message' => 'Ihre Terminanfrage ist eingegangen. Sie erhalten eine Bestätigung, sobald der Salon den Termin angenommen hat.'], $r['replayed'] ? 200 : 201);
}

if ($route === 'appointments/cancel') {
    wf_api_require_method('POST');
    $auth = wf_api_auth();
    $r = wf_app_cancel($config, $auth['customer'], wf_api_str('id', 40));
    if (!$r['ok']) wf_api_fail($r['error'], wf_api_status_for($r['error']));
    wf_api_out(['appointment' => wf_app_find_owned_appointment($config, $auth['customer'], $r['appointment']['id']), 'message' => 'Ihr Termin wurde storniert.']);
}

// Änderungsanfrage: neuer Wunschtermin wird als Anfrage gespeichert (Slot ist
// reserviert). Der bisherige Termin bleibt gültig, bis der Salon die Änderung
// im Dashboard annimmt – erst dann wird er automatisch freigegeben.
if ($route === 'appointments/change') {
    wf_api_require_method('POST');
    $auth = wf_api_auth();
    $c = $auth['customer'];
    if (!wf_app_rate_hit($config, 'book|' . $c['id'], 10, 3600)) wf_api_fail('rate_limited', 429);
    $id = wf_api_str('id', 40);
    $orig = null;
    foreach (wf_read_appointments($config) as $a) if (($a['id'] ?? '') === $id && wf_app_owns_appointment($c, $a)) $orig = $a;
    if (!$orig) wf_api_fail('not_found', 404);
    $in = wf_api_input();
    $in['vorname'] = $orig['vorname'];
    $in['nachname'] = $orig['nachname'];
    $in['type'] = $orig['type'];
    if (($in['service'] ?? '') === '') $in['service'] = $orig['service'];
    $r = wf_app_book($config, $c, $in, wf_api_str('request_id', 64), $id);
    if (!$r['ok']) wf_api_fail($r['error'], wf_api_status_for($r['error']));
    if (!$r['replayed']) wf_app_notify_owner_new_booking($config, $r['appointment'] + ['type_label' => ($r['appointment']['type_label'] ?? '') . ' (Verschiebung)']);
    $all = wf_read_appointments($config);
    wf_api_out(['appointment' => wf_app_appointment_public($config, $r['appointment'], $all), 'replayed' => $r['replayed'],
        'message' => 'Ihre Änderungsanfrage ist eingegangen. Ihr bisheriger Termin bleibt bestehen, bis der Salon die Änderung bestätigt.'], $r['replayed'] ? 200 : 201);
}

// ---- Nachrichten ----

if ($route === 'conversations') {
    $auth = wf_api_auth();
    $c = $auth['customer'];
    if ($method === 'POST') {
        // Konversation öffnen (allgemein oder zu einem Termin), optional mit erster Nachricht
        $apptId = wf_api_str('appointment_id', 40);
        $r = wf_app_conv_open($config, $c, $apptId === '' ? null : $apptId);
        if (!$r['ok']) wf_api_fail($r['error'], wf_api_status_for($r['error']));
        wf_api_out(['conversation' => wf_app_conv_summary_customer($config, $r['conversation'], wf_app_appointments_by_id($config))]);
    }
    wf_api_out(['conversations' => wf_app_customer_conversations($config, $c), 'unread_total' => wf_app_customer_unread_total($config, $c)]);
}

if ($route === 'messages') {
    $auth = wf_api_auth();
    $c = $auth['customer'];
    $convId = wf_api_str('conversation_id', 40);
    if ($method === 'POST') {
        if (!wf_app_rate_hit($config, 'msg|' . $c['id'], 30, 600)) wf_api_fail('rate_limited', 429);
        $in = wf_api_input();
        $r = wf_app_conv_append($config, $convId, 'customer', is_string($in['text'] ?? null) ? $in['text'] : '', wf_api_str('client_msg_id', 64), $c['id']);
        if (!$r['ok']) wf_api_fail($r['error'], wf_api_status_for($r['error']));
        if (!$r['replayed'] && $r['first_unread']) wf_app_notify_staff_new_message($config, $r['conversation'], $c);
        wf_api_out(['message' => wf_app_message_public($r['message'], $r['conversation'], 'customer'), 'replayed' => $r['replayed']], $r['replayed'] ? 200 : 201);
    }
    $conv = wf_app_conv_get($config, $convId);
    if (!$conv || $conv['customer_id'] !== $c['id']) wf_api_fail('not_found', 404);
    $before = isset($_GET['before_seq']) ? (int)$_GET['before_seq'] : null;
    $after = isset($_GET['after_seq']) ? (int)$_GET['after_seq'] : null;
    $page = wf_app_conv_messages_page($conv, 'customer', $before, $after, (int)($_GET['limit'] ?? 30));
    wf_api_out(['conversation' => wf_app_conv_summary_customer($config, $conv, wf_app_appointments_by_id($config))] + $page);
}

if ($route === 'conversations/read') {
    wf_api_require_method('POST');
    $auth = wf_api_auth();
    if (!wf_app_conv_mark_read($config, wf_api_str('conversation_id', 40), 'customer', $auth['customer']['id'])) wf_api_fail('not_found', 404);
    wf_api_out(['unread_total' => wf_app_customer_unread_total($config, $auth['customer'])]);
}

if ($route === 'unread') {
    $auth = wf_api_auth();
    wf_api_out(['unread_total' => wf_app_customer_unread_total($config, $auth['customer'])]);
}

// ---- Push-Geräte ----

if ($route === 'devices/register') {
    wf_api_require_method('POST');
    $auth = wf_api_auth();
    $in = wf_api_input();
    $ok = wf_app_register_device($config, $auth['customer']['id'], wf_api_str('platform', 10), wf_api_str('token', 4096), [
        'locale' => $in['locale'] ?? 'de', 'app_version' => $in['app_version'] ?? '', 'apns_env' => $in['apns_env'] ?? '',
    ]);
    if (!$ok) wf_api_fail('invalid_device', 422);
    wf_api_out([]);
}

if ($route === 'devices/unregister') {
    wf_api_require_method('POST');
    $auth = wf_api_auth();
    wf_app_unregister_device($config, $auth['customer']['id'], wf_api_str('token', 4096));
    wf_api_out([]);
}

wf_api_fail('not_found', 404, 'Unbekannte Route.');

} catch (Throwable $e) {
    error_log('Wolf Friseur App-API Fehler: ' . get_class($e) . ' in ' . $e->getFile() . ':' . $e->getLine());
    wf_api_fail('server_error', 500);
}
