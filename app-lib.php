<?php
// ============================================================
// Wolf Friseur – Mobile Apps (Android / iOS) – Server-Bibliothek
// ------------------------------------------------------------
// Ergänzt das bestehende Buchungssystem um:
//  * Kundenkonten mit SMS-Code-Anmeldung (Telefonnummer wird verifiziert)
//  * Sitzungen (Bearer-Token, nur als Hash gespeichert)
//  * Termine des Kunden (aus derselben appointments.json – kein zweiter Speicher)
//  * Nachrichten (Konversationen Kunde <-> Salon)
//  * Push-Geräte + Push-Versand (FCM für Android, APNs für iOS)
//
// Alle App-Daten liegen in einem eigenen Ordner (Standard: app-data/), der
// per .htaccess gesperrt wird. Gleiches Speicherprinzip wie der Rest des
// Systems: JSON-Dateien mit Dateisperre (wf_read_json_file / wf_write_json_file).
// ============================================================

require_once __DIR__ . '/functions.php';

define('WF_APP_API_VERSION', 1);

// ---- Konfiguration (config.php -> 'app' => [...], alles optional) ----
function wf_app_cfg($config) {
    $defaults = [
        'data_dir'           => __DIR__ . '/app-data',
        'session_days'       => 90,      // Sitzung läuft ab, wenn so lange nicht benutzt
        'session_max_days'   => 365,     // absolute Höchstdauer einer Sitzung
        'otp_ttl_seconds'    => 600,
        'otp_max_attempts'   => 5,
        'allowed_phone_prefixes' => ['+49', '+43', '+41'], // Schutz vor SMS-Betrug ins Ausland
        'message_max_length' => 2000,
        // true = Kunde mit funktionierender Push-Zustellung bekommt bei
        // Annahme/Ablehnung KEINE zusätzliche SMS (vermeidet doppelte Hinweise).
        // Standard false = bisheriges SMS-Verhalten bleibt unverändert.
        'push_replaces_sms'  => false,
        // Telegram-Hinweis an den Friseur bei neuer App-Nachricht (ohne Inhalt)
        'telegram_on_message' => true,
        // Optionaler Test-Zugang für die App-Store-Prüfung (leer = aus)
        'review_phone'       => '',
        'review_code'        => '',
        'push' => [
            'fcm_service_account_file' => '', // Pfad zur Firebase-Dienstkonto-JSON (außerhalb public_html!)
            'apns_key_file'  => '',           // Pfad zur .p8-Datei (außerhalb public_html!)
            'apns_key_id'    => '',
            'apns_team_id'   => '',
            'apns_bundle_id' => 'de.wolffriseur.app',
            'apns_sandbox'   => false,
        ],
    ];
    $cfg = ($config['app'] ?? []) + $defaults;
    $cfg['push'] = (($config['app']['push'] ?? []) + $defaults['push']);
    // Umgebungsvariablen haben Vorrang (empfohlen für Pfade/Geheimnisse)
    $env = [
        'WF_FCM_SERVICE_ACCOUNT_FILE' => 'fcm_service_account_file',
        'WF_APNS_KEY_FILE' => 'apns_key_file', 'WF_APNS_KEY_ID' => 'apns_key_id',
        'WF_APNS_TEAM_ID' => 'apns_team_id', 'WF_APNS_BUNDLE_ID' => 'apns_bundle_id',
    ];
    foreach ($env as $name => $key) {
        $v = getenv($name);
        if (is_string($v) && trim($v) !== '') $cfg['push'][$key] = trim($v);
    }
    $sandbox = getenv('WF_APNS_SANDBOX');
    if ($sandbox !== false && $sandbox !== '') $cfg['push']['apns_sandbox'] = in_array(strtolower($sandbox), ['1', 'true', 'yes'], true);
    $dir = getenv('WF_APP_DATA_DIR');
    if (is_string($dir) && $dir !== '') $cfg['data_dir'] = $dir;
    return $cfg;
}

// Nur für automatisierte Tests: liefert den SMS-Code in der Antwort zurück.
// Wird ausschließlich über die Server-Umgebung gesetzt, NIE in Produktion.
function wf_app_test_mode() {
    return getenv('WF_APP_TEST_MODE') === '1';
}

// ============================================================
// Speicher
// ============================================================

function wf_app_data_dir($config) {
    $dir = rtrim(wf_app_cfg($config)['data_dir'], '/\\');
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    // Direkten Abruf per Browser sperren (Apache/LiteSpeed)
    if (!file_exists($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    if (!file_exists($dir . '/index.html')) {
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

function wf_app_file($config, $name) {
    return wf_app_data_dir($config) . '/' . $name;
}

function wf_app_read($config, $name, $default = []) {
    return wf_read_json_file(wf_app_file($config, $name), $default);
}

function wf_app_write($config, $name, $callback, $default = []) {
    return wf_write_json_file(wf_app_file($config, $name), $callback, $default);
}

function wf_app_mtime($config, $name) {
    $f = wf_app_file($config, $name);
    clearstatcache(true, $f);
    return file_exists($f) ? filemtime($f) : 0;
}

// Server-Geheimnis für HMAC der SMS-Codes. Umgebungsvariable WF_APP_SECRET
// oder automatisch erzeugte Datei app-data/secret.php (nicht abrufbar).
function wf_app_secret($config) {
    $env = getenv('WF_APP_SECRET');
    if (is_string($env) && strlen($env) >= 32) return $env;
    $file = wf_app_file($config, 'secret.php');
    if (!file_exists($file)) {
        $fp = @fopen($file, 'x');
        if ($fp) {
            fwrite($fp, "<?php http_response_code(404); exit; ?>\n" . bin2hex(random_bytes(32)) . "\n");
            fclose($fp);
        }
    }
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $secret = is_array($lines) && isset($lines[1]) ? trim($lines[1]) : '';
    if (strlen($secret) < 32) throw new RuntimeException('app secret unavailable');
    return $secret;
}

function wf_app_uuid() {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function wf_app_b64url($bin) {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

// Freitext bereinigen: Steuerzeichen raus (Zeilenumbrüche bleiben), trimmen, kürzen
function wf_app_clean_text($s, $max) {
    $s = (string)$s;
    if (!mb_check_encoding($s, 'UTF-8')) return '';
    $s = str_replace("\r\n", "\n", $s);
    $s = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $s);
    $s = trim($s);
    return mb_substr($s, 0, $max);
}

function wf_app_clean_name($s) {
    $s = wf_app_clean_text($s, 60);
    return trim(preg_replace('/\s+/u', ' ', $s));
}

// ============================================================
// Ratenbegrenzung (gleitendes Fenster, pro Schlüssel)
// ============================================================

// Rückgabe: true = erlaubt (und gezählt), false = Limit erreicht
function wf_app_rate_hit($config, $bucket, $limit, $windowSeconds) {
    $allowed = true;
    $now = time();
    $key = hash('sha256', $bucket);
    wf_app_write($config, 'ratelimit.json', function ($data) use ($key, $limit, $windowSeconds, $now, &$allowed) {
        $hits = array_values(array_filter((array)($data[$key]['t'] ?? []), fn($t) => $t > $now - $windowSeconds));
        if (count($hits) >= $limit) {
            $allowed = false;
        } else {
            $hits[] = $now;
        }
        $data[$key] = ['t' => $hits, 'w' => $windowSeconds];
        // Gelegentlich alte Einträge aufräumen
        if (mt_rand(1, 50) === 1) {
            foreach ($data as $k => $v) {
                $w = (int)($v['w'] ?? 86400);
                if (!array_filter((array)($v['t'] ?? []), fn($t) => $t > $now - $w)) unset($data[$k]);
            }
        }
        return $data;
    }, []);
    return $allowed;
}

function wf_app_client_ip() {
    // Bewusst nur REMOTE_ADDR (X-Forwarded-For ist fälschbar)
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

// ============================================================
// Telefonnummer
// ============================================================

// Liefert die Nummer im Format +49..., oder '' wenn ungültig/nicht erlaubt
function wf_app_normalize_phone($config, $phone) {
    $e164 = wf_phone_e164($phone);
    if (!preg_match('/^\+[1-9]\d{7,14}$/', $e164)) return '';
    $prefixes = (array)wf_app_cfg($config)['allowed_phone_prefixes'];
    if ($prefixes) {
        $ok = false;
        foreach ($prefixes as $p) if (strpos($e164, $p) === 0) $ok = true;
        if (!$ok) return '';
    }
    return $e164;
}

// Gleiche Nummer? (gleiche Normalisierung wie für die Kundenkonten)
function wf_app_same_phone($a, $b) {
    $x = wf_phone_e164($a);
    return $x !== '' && $x === wf_phone_e164($b);
}

// ============================================================
// Kunden
// ============================================================

function wf_app_customers($config) {
    return wf_app_read($config, 'customers.json', []);
}

function wf_app_customer_by_id($config, $id) {
    foreach (wf_app_customers($config) as $c) {
        if ($c['id'] === $id) return $c;
    }
    return null;
}

function wf_app_customers_by_phone($config, $phone) {
    $out = [];
    foreach (wf_app_customers($config) as $c) {
        if (wf_app_same_phone($c['phone'], $phone)) $out[] = $c;
    }
    return $out;
}

function wf_app_default_prefs() {
    return ['push_appointments' => true, 'push_messages' => true];
}

// Kunde zur (verifizierten) Nummer finden oder anlegen
function wf_app_customer_upsert_login($config, $phone) {
    $customer = null;
    wf_app_write($config, 'customers.json', function ($data) use ($phone, &$customer) {
        foreach ($data as &$c) {
            if ($c['phone'] === $phone) {
                $c['last_login_at'] = date('c');
                $customer = $c;
                return $data;
            }
        }
        unset($c);
        $customer = [
            'id' => wf_generate_id() . wf_generate_id(),
            'phone' => $phone,
            'vorname' => '',
            'nachname' => '',
            'prefs' => wf_app_default_prefs(),
            'created_at' => date('c'),
            'last_login_at' => date('c'),
        ];
        $data[] = $customer;
        return $data;
    }, []);
    return $customer;
}

function wf_app_customer_public($c) {
    return [
        'id' => $c['id'],
        'phone' => $c['phone'],
        'vorname' => $c['vorname'] ?? '',
        'nachname' => $c['nachname'] ?? '',
        'profile_complete' => trim($c['vorname'] ?? '') !== '' && trim($c['nachname'] ?? '') !== '',
        'prefs' => ($c['prefs'] ?? []) + wf_app_default_prefs(),
        'created_at' => $c['created_at'] ?? null,
    ];
}

// ============================================================
// Anmeldung per SMS-Code
// ============================================================

function wf_app_otp_hash($config, $phone, $code) {
    return hash_hmac('sha256', $phone . '|' . $code, wf_app_secret($config));
}

function wf_app_is_review_login($config, $phone) {
    $cfg = wf_app_cfg($config);
    $rp = trim((string)$cfg['review_phone']);
    return $rp !== '' && trim((string)$cfg['review_code']) !== '' && wf_app_same_phone($rp, $phone);
}

// Rückgabe: ['ok' => bool, 'error' => code|null, 'debug_code' => (nur Testmodus)]
function wf_app_request_code($config, $phoneRaw) {
    $phone = wf_app_normalize_phone($config, $phoneRaw);
    if ($phone === '') return ['ok' => false, 'error' => 'invalid_phone'];

    $ip = wf_app_client_ip();
    if (!wf_app_rate_hit($config, 'otp-ip-h|' . $ip, 10, 3600)
        || !wf_app_rate_hit($config, 'otp-ip-d|' . $ip, 30, 86400)
        || !wf_app_rate_hit($config, 'otp-ph-q|' . $phone, 3, 900)
        || !wf_app_rate_hit($config, 'otp-ph-d|' . $phone, 8, 86400)) {
        return ['ok' => false, 'error' => 'rate_limited'];
    }

    if (wf_app_is_review_login($config, $phone)) return ['ok' => true, 'error' => null];

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $ttl = (int)wf_app_cfg($config)['otp_ttl_seconds'];
    $hash = wf_app_otp_hash($config, $phone, $code);
    wf_app_write($config, 'otp.json', function ($data) use ($phone, $hash, $ttl) {
        $now = time();
        foreach ($data as $k => $v) if (($v['expires'] ?? 0) < $now) unset($data[$k]);
        $data[$phone] = ['hash' => $hash, 'expires' => $now + $ttl, 'attempts' => 0];
        return $data;
    }, []);

    $text = "Wolf Friseur: Ihr Anmeldecode lautet {$code}. Er ist " . (int)round($ttl / 60) . " Minuten gültig. Geben Sie ihn nicht weiter.";
    $sent = false;
    try { $sent = wf_send_sms($config, $phone, $text); } catch (Throwable $e) { error_log('Wolf Friseur App-SMS: ' . $e->getMessage()); }
    $res = ['ok' => true, 'error' => null, 'sms_sent' => $sent];
    if (wf_app_test_mode()) $res['debug_code'] = $code;
    return $res;
}

// Rückgabe: ['ok' => true, 'customer' => [...], 'phone' => ...] oder ['ok' => false, 'error' => ...]
function wf_app_verify_code($config, $phoneRaw, $code) {
    $phone = wf_app_normalize_phone($config, $phoneRaw);
    $code = preg_replace('/\D+/', '', (string)$code);
    if ($phone === '' || strlen($code) !== 6) return ['ok' => false, 'error' => 'invalid_code'];
    if (!wf_app_rate_hit($config, 'verify-ip|' . wf_app_client_ip(), 30, 3600)) return ['ok' => false, 'error' => 'rate_limited'];

    $valid = false;
    if (wf_app_is_review_login($config, $phone)) {
        $valid = hash_equals(preg_replace('/\D+/', '', (string)wf_app_cfg($config)['review_code']), $code);
    } else {
        $max = (int)wf_app_cfg($config)['otp_max_attempts'];
        $hash = wf_app_otp_hash($config, $phone, $code);
        $error = null;
        wf_app_write($config, 'otp.json', function ($data) use ($phone, $hash, $max, &$valid, &$error) {
            $rec = $data[$phone] ?? null;
            if (!$rec || $rec['expires'] < time()) { $error = 'code_expired'; unset($data[$phone]); return $data; }
            if ($rec['attempts'] >= $max) { $error = 'too_many_attempts'; unset($data[$phone]); return $data; }
            if (hash_equals($rec['hash'], $hash)) {
                $valid = true;
                unset($data[$phone]); // Code verbraucht
            } else {
                $data[$phone]['attempts'] = $rec['attempts'] + 1;
            }
            return $data;
        }, []);
        if ($error) return ['ok' => false, 'error' => $error];
    }
    if (!$valid) return ['ok' => false, 'error' => 'invalid_code'];

    $customer = wf_app_customer_upsert_login($config, $phone);
    return ['ok' => true, 'customer' => $customer];
}

// ============================================================
// Sitzungen (Bearer-Token)
// ============================================================

function wf_app_create_session($config, $customerId, $deviceLabel) {
    $token = 'wfa_' . wf_app_b64url(random_bytes(32));
    $cfg = wf_app_cfg($config);
    $now = time();
    $session = [
        'hash' => hash('sha256', $token),
        'customer_id' => $customerId,
        'device' => mb_substr(wf_app_clean_text($deviceLabel, 80), 0, 80),
        'created_at' => $now,
        'last_used_at' => $now,
        'expires_at' => $now + (int)$cfg['session_days'] * 86400,
        'max_expires_at' => $now + (int)$cfg['session_max_days'] * 86400,
    ];
    wf_app_write($config, 'sessions.json', function ($data) use ($session, $now) {
        $data = array_values(array_filter($data, fn($s) => ($s['expires_at'] ?? 0) > $now));
        $data[] = $session;
        return $data;
    }, []);
    return ['token' => $token, 'expires_at' => date('c', $session['expires_at'])];
}

// Token aus dem Request (Authorization: Bearer … – mit Fallbacks, weil
// manche Shared-Hostings den Authorization-Header nicht an PHP weiterreichen)
function wf_app_request_token() {
    $candidates = [
        $_SERVER['HTTP_AUTHORIZATION'] ?? '',
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
    ];
    if (function_exists('getallheaders')) {
        foreach ((array)getallheaders() as $k => $v) {
            if (strtolower($k) === 'authorization') $candidates[] = $v;
        }
    }
    foreach ($candidates as $h) {
        if (preg_match('/^\s*Bearer\s+(wfa_[A-Za-z0-9_-]{20,100})\s*$/', (string)$h, $m)) return $m[1];
    }
    $x = (string)($_SERVER['HTTP_X_WF_TOKEN'] ?? '');
    if (preg_match('/^wfa_[A-Za-z0-9_-]{20,100}$/', $x)) return $x;
    return null;
}

// Rückgabe: ['customer' => ..., 'session_hash' => ...] oder null
function wf_app_authenticate($config, $token) {
    if (!$token) return null;
    $hash = hash('sha256', $token);
    $now = time();
    $session = null;
    foreach (wf_app_read($config, 'sessions.json', []) as $s) {
        if (hash_equals($s['hash'], $hash)) { $session = $s; break; }
    }
    if (!$session || $session['expires_at'] <= $now || ($session['max_expires_at'] ?? PHP_INT_MAX) <= $now) return null;
    $customer = wf_app_customer_by_id($config, $session['customer_id']);
    if (!$customer) return null;

    // Gleitender Ablauf – höchstens einmal pro Stunde schreiben
    if ($now - (int)$session['last_used_at'] > 3600) {
        $days = (int)wf_app_cfg($config)['session_days'];
        wf_app_write($config, 'sessions.json', function ($data) use ($hash, $now, $days) {
            foreach ($data as &$s) {
                if ($s['hash'] === $hash) {
                    $s['last_used_at'] = $now;
                    $s['expires_at'] = min($now + $days * 86400, $s['max_expires_at'] ?? PHP_INT_MAX);
                }
            }
            unset($s);
            return $data;
        }, []);
    }
    return ['customer' => $customer, 'session_hash' => $hash];
}

function wf_app_revoke_session($config, $hash) {
    return wf_app_write($config, 'sessions.json', function ($data) use ($hash) {
        return array_values(array_filter($data, fn($s) => $s['hash'] !== $hash));
    }, []);
}

function wf_app_revoke_customer_sessions($config, $customerId) {
    return wf_app_write($config, 'sessions.json', function ($data) use ($customerId) {
        return array_values(array_filter($data, fn($s) => $s['customer_id'] !== $customerId));
    }, []);
}

// ============================================================
// Termine des Kunden
// ------------------------------------------------------------
// Eigentum: ein Termin gehört dem Kunden, wenn die Telefonnummer des Termins
// (normalisiert) der per SMS-Code VERIFIZIERTEN Nummer des Kontos entspricht.
// So sieht ein Kunde auch seine früheren Website-Buchungen – aber nur, wenn
// er Zugriff auf genau diese Nummer nachgewiesen hat.
// ============================================================

function wf_app_owns_appointment($customer, $a) {
    if (($a['type'] ?? '') === 'block') return false;
    return wf_app_same_phone($a['telefon'] ?? '', $customer['phone']);
}

// Anzeige-Status für die App
function wf_app_display_status($a, $now = null) {
    $now = $now ?? time();
    $status = $a['status'] ?? 'confirmed';
    if ($status === 'cancelled') {
        if (!empty($a['rejected'])) return 'rejected';
        if (($a['cancel_reason'] ?? '') === 'rescheduled') return 'rescheduled';
        return 'cancelled';
    }
    if ($status === 'no_show') return 'no_show';
    $endTs = strtotime($a['date'] . ' ' . wf_appointment_end($a));
    if ($endTs <= $now) return !empty($a['checked_in']) ? 'completed' : 'past';
    return $status === 'pending' ? 'pending' : 'confirmed';
}

// Offene Verschiebungs-Anfrage zu einem Termin (neuer Termin mit 'replaces' = id, Status pending)
function wf_app_pending_change($appointments, $id) {
    foreach ($appointments as $x) {
        if (($x['replaces'] ?? null) === $id && ($x['status'] ?? '') === 'pending') return $x;
    }
    return null;
}

function wf_app_appointment_public($config, $a, $appointments, $now = null) {
    $now = $now ?? time();
    $display = wf_app_display_status($a, $now);
    $active = in_array($display, ['pending', 'confirmed'], true);
    $hoursUntil = wf_hours_until($a);
    $minHours = (float)$config['self_cancel_min_hours'];
    $pendingChange = $active ? wf_app_pending_change($appointments, $a['id']) : null;
    $deadlineTs = wf_appointment_timestamp($a) - (int)round($minHours * 3600);
    return [
        'id' => $a['id'],
        'date' => $a['date'],
        'time' => $a['time'],
        'end' => wf_appointment_end($a),
        'duration' => (int)$a['duration'],
        'service' => $a['service'] ?? '',
        'service_label' => $a['service_label'] ?? '',
        'type' => $a['type'] ?? '',
        'type_label' => $a['type_label'] ?? '',
        'price' => array_key_exists('price', $a) ? $a['price'] : null,
        'price_label' => array_key_exists('price', $a) ? wf_format_price($a['price']) : '',
        'vorname' => $a['vorname'] ?? '',
        'nachname' => $a['nachname'] ?? '',
        'status' => $display,
        'is_upcoming' => $active,
        'can_cancel' => $active && $hoursUntil >= $minHours && empty($a['checked_in']),
        'can_change' => $active && $hoursUntil >= $minHours && empty($a['checked_in']) && !$pendingChange && empty($a['replaces']),
        'change_deadline' => date('c', $deadlineTs),
        'pending_change' => $pendingChange ? ['id' => $pendingChange['id'], 'date' => $pendingChange['date'], 'time' => $pendingChange['time']] : null,
        'replaces' => $a['replaces'] ?? null,
        'created_at' => $a['created_at'] ?? null,
        'source' => ($a['channel'] ?? '') === 'app' ? 'app' : (($a['source'] ?? '') === 'admin' ? 'salon' : 'website'),
    ];
}

function wf_app_customer_appointments($config, $customer) {
    $all = wf_read_appointments($config);
    $now = time();
    $out = [];
    foreach ($all as $a) {
        if (!wf_app_owns_appointment($customer, $a)) continue;
        $out[] = wf_app_appointment_public($config, $a, $all, $now);
    }
    usort($out, fn($x, $y) => [$x['date'], $x['time']] <=> [$y['date'], $y['time']]);
    return $out;
}

function wf_app_find_owned_appointment($config, $customer, $id) {
    $all = wf_read_appointments($config);
    foreach ($all as $a) {
        if (($a['id'] ?? '') === $id && wf_app_owns_appointment($customer, $a)) {
            return wf_app_appointment_public($config, $a, $all);
        }
    }
    return null;
}

function wf_app_booking_window($config) {
    return [date('Y-m-d'), date('Y-m-d', strtotime("+{$config['booking_window_days']} day"))];
}

// Neue Buchung aus der App. Gleiche Regeln wie booking-api.php (action=book),
// alle Prüfungen im exklusiven Lock. Idempotent über $clientRef.
// Rückgabe: ['ok' => bool, 'error' => code, 'appointment' => Rohdaten, 'replayed' => bool]
function wf_app_book($config, $customer, $in, $clientRef, $replacesId = null) {
    $vorname = wf_app_clean_name($in['vorname'] ?? '');
    $nachname = wf_app_clean_name($in['nachname'] ?? '');
    $type = $in['type'] ?? '';
    $serviceKey = (string)($in['service'] ?? '');
    $date = (string)($in['date'] ?? '');
    $time = (string)($in['time'] ?? '');

    if ($vorname === '' || $nachname === '') return ['ok' => false, 'error' => 'name_required'];
    if (!in_array($type, ['mann', 'kind'], true)) return ['ok' => false, 'error' => 'invalid_type'];
    if ($type === 'kind') $serviceKey = 'kind';
    $services = wf_services_keyed($config);
    if (!isset($services[$serviceKey]) || ($type === 'mann' && ($services[$serviceKey]['category'] ?? '') !== 'herr')) {
        return ['ok' => false, 'error' => 'invalid_service'];
    }
    if (!wf_valid_date($date) || !wf_valid_time($time)) return ['ok' => false, 'error' => 'invalid'];
    [$minDate, $maxDate] = wf_app_booking_window($config);
    if ($date < $minDate || $date > $maxDate) return ['ok' => false, 'error' => 'out_of_range'];
    if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', (string)$clientRef)) return ['ok' => false, 'error' => 'invalid_request_id'];

    $service = $services[$serviceKey];
    $duration = (int)$service['duration'];
    $telefon = $customer['phone'];
    $minHours = (float)$config['self_cancel_min_hours'];

    $result = null;
    $error = null;
    $replayed = false;
    wf_write_appointments($config, function ($data) use (
        $config, $customer, $clientRef, $replacesId, $date, $time, $duration, $serviceKey, $service, $type,
        $vorname, $nachname, $telefon, $minHours, &$result, &$error, &$replayed
    ) {
        // Wiederholte Anfrage (z.B. Netzwerkabbruch nach dem Speichern) -> gleiches Ergebnis
        foreach ($data as $a) {
            if (($a['client_ref'] ?? '') === $clientRef && ($a['app_customer_id'] ?? '') === $customer['id']) {
                $result = $a;
                $replayed = true;
                return false;
            }
        }
        $original = null;
        if ($replacesId !== null) {
            foreach ($data as $a) {
                if (($a['id'] ?? '') === $replacesId) $original = $a;
            }
            if (!$original || !wf_app_owns_appointment($customer, $original)) { $error = 'not_found'; return false; }
            if (!in_array($original['status'] ?? '', ['pending', 'confirmed'], true) || !empty($original['checked_in'])
                || wf_appointment_timestamp($original) <= time() || !empty($original['replaces'])) { $error = 'not_changeable'; return false; }
            if (wf_hours_until($original) < $minHours) { $error = 'too_late'; return false; }
            if (wf_app_pending_change($data, $replacesId)) { $error = 'change_pending'; return false; }
        }
        // Ein Termin pro Person und Tag (wie auf der Website); der zu verschiebende Termin zählt nicht mit
        $others = $replacesId === null ? $data : array_values(array_filter($data, fn($a) => ($a['id'] ?? '') !== $replacesId));
        if (wf_customer_has_booking_on_date($others, $date, $vorname, $nachname, $telefon)) { $error = 'duplicate'; return false; }

        $error = wf_booking_error($date, $time, $duration, $config, $data, [
            'earliest_ts' => wf_online_earliest_ts($config), 'online' => true,
        ]);
        if ($error !== null) return false;

        $new = [
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
            'status' => 'pending',
            // 'customer' wie bei Website-Buchungen, damit Alarm/Annehmen im Dashboard unverändert greifen
            'source' => 'customer',
            'channel' => 'app',
            'app_customer_id' => $customer['id'],
            'client_ref' => $clientRef,
            'created_at' => date('c'),
        ];
        if ($original) $new['replaces'] = $original['id'];
        $data[] = $new;
        $result = $new;
        return $data;
    });

    if ($result) return ['ok' => true, 'error' => null, 'appointment' => $result, 'replayed' => $replayed];
    return ['ok' => false, 'error' => $error ?: 'save_failed'];
}

// Benachrichtigungen an den Friseur bei neuer App-Buchung – identisch zur Website
function wf_app_notify_owner_new_booking($config, $a) {
    // E-Mail an info@ deaktiviert (04.11.2026) – Benachrichtigung nur per SMS + Dashboard
    // try { wf_send_owner_notification($config, $a); } catch (Throwable $e) { error_log('Wolf Friseur E-Mail: ' . $e->getMessage()); }
    try { wf_sms_owner_new_booking($config, $a); } catch (Throwable $e) { error_log('Wolf Friseur SMS: ' . $e->getMessage()); }
    wf_telegram_notify_new_booking($config, $a);
}

// Stornierung durch den Kunden – gleiche Frist wie auf der Website
function wf_app_cancel($config, $customer, $id) {
    $error = null;
    $cancelled = null;
    $minHours = (float)$config['self_cancel_min_hours'];
    $ok = wf_write_appointments($config, function ($data) use ($customer, $id, $minHours, &$error, &$cancelled) {
        foreach ($data as &$a) {
            if (($a['id'] ?? '') !== $id) continue;
            if (!wf_app_owns_appointment($customer, $a)) break;
            if (($a['status'] ?? '') === 'cancelled') { $error = 'already_cancelled'; return false; }
            if (!in_array($a['status'] ?? '', ['pending', 'confirmed'], true) || !empty($a['checked_in'])) { $error = 'not_changeable'; return false; }
            if (wf_hours_until($a) < $minHours) { $error = 'too_late'; return false; }
            $a['status'] = 'cancelled';
            $a['cancelled_by'] = 'customer';
            $a['cancelled_at'] = date('c');
            $a['cancel_channel'] = 'app';
            $cancelled = $a;
            unset($a);
            return $data;
        }
        unset($a);
        $error = 'not_found';
        return false;
    });
    if (!$ok || !$cancelled) return ['ok' => false, 'error' => $error ?: 'save_failed'];
    return ['ok' => true, 'appointment' => $cancelled];
}

// Wird von admin-api.php (accept/reject) im selben Lock aufgerufen:
// Wird eine Verschiebung angenommen, gilt der alte Termin als abgesagt.
function wf_app_apply_change_decision(&$data, $newAppt, $accepted) {
    if (empty($newAppt['replaces']) || !$accepted) return null;
    foreach ($data as &$a) {
        if (($a['id'] ?? '') === $newAppt['replaces'] && in_array($a['status'] ?? '', ['pending', 'confirmed'], true)) {
            $a['status'] = 'cancelled';
            $a['cancelled_by'] = 'customer';
            $a['cancel_reason'] = 'rescheduled';
            $a['cancelled_at'] = date('c');
            $a['replaced_by'] = $newAppt['id'];
            $old = $a;
            unset($a);
            return $old;
        }
    }
    unset($a);
    return null;
}

// ============================================================
// Nachrichten
// ------------------------------------------------------------
// conversations.json: Liste von Konversationen, jede mit ihren Nachrichten.
// Pro Kunde gibt es höchstens eine allgemeine Konversation und höchstens
// eine pro Termin. Nachrichten haben eine fortlaufende Nummer (seq) je
// Konversation – daraus ergeben sich "gelesen"-Status und Seitenweise Abruf.
// ============================================================

function wf_app_conv_read_all($config) {
    return wf_app_read($config, 'conversations.json', []);
}

function wf_app_conv_write($config, $callback) {
    return wf_app_write($config, 'conversations.json', $callback, []);
}

function wf_app_conv_unread_for($conv, $who) {
    $readSeq = (int)($who === 'customer' ? ($conv['customer_read_seq'] ?? 0) : ($conv['staff_read_seq'] ?? 0));
    $other = $who === 'customer' ? 'staff' : 'customer';
    $n = 0;
    foreach ($conv['messages'] as $m) {
        if ($m['sender'] === $other && $m['seq'] > $readSeq) $n++;
    }
    return $n;
}

function wf_app_message_public($m, $conv, $viewer) {
    $out = [
        'id' => $m['id'],
        'seq' => (int)$m['seq'],
        'sender' => $m['sender'],
        'text' => $m['text'],
        'created_at' => $m['created_at'],
        'client_msg_id' => $m['client_msg_id'] ?? null,
    ];
    // Status aus Sicht des Absenders: 'sent' (auf dem Server) oder 'read'
    $otherRead = (int)($m['sender'] === 'customer' ? ($conv['staff_read_seq'] ?? 0) : ($conv['customer_read_seq'] ?? 0));
    $out['status'] = $m['seq'] <= $otherRead ? 'read' : 'sent';
    if ($viewer === 'staff') {
        $out['push_status'] = $m['push_status'] ?? null;
        $out['staff_user'] = $m['staff_user'] ?? null;
    }
    return $out;
}

function wf_app_conv_last($conv) {
    $last = $conv['messages'] ? $conv['messages'][count($conv['messages']) - 1] : null;
    return $last ? ['sender' => $last['sender'], 'text' => mb_substr($last['text'], 0, 140), 'created_at' => $last['created_at']] : null;
}

function wf_app_conv_summary_customer($config, $conv, $appointmentsById) {
    $appt = $conv['appointment_id'] ? ($appointmentsById[$conv['appointment_id']] ?? null) : null;
    return [
        'id' => $conv['id'],
        'appointment_id' => $conv['appointment_id'],
        'appointment' => $appt ? ['date' => $appt['date'], 'time' => $appt['time'], 'service_label' => $appt['service_label'] ?? ''] : null,
        'subject' => $conv['subject'],
        'unread' => wf_app_conv_unread_for($conv, 'customer'),
        'last_message' => wf_app_conv_last($conv),
        'updated_at' => $conv['updated_at'],
    ];
}

function wf_app_appointments_by_id($config) {
    $out = [];
    foreach (wf_read_appointments($config) as $a) $out[$a['id']] = $a;
    return $out;
}

function wf_app_customer_conversations($config, $customer) {
    $byId = wf_app_appointments_by_id($config);
    $out = [];
    foreach (wf_app_conv_read_all($config) as $c) {
        if ($c['customer_id'] !== $customer['id']) continue;
        $out[] = wf_app_conv_summary_customer($config, $c, $byId);
    }
    usort($out, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));
    return $out;
}

function wf_app_customer_unread_total($config, $customer) {
    $n = 0;
    foreach (wf_app_conv_read_all($config) as $c) {
        if ($c['customer_id'] === $customer['id']) $n += wf_app_conv_unread_for($c, 'customer');
    }
    return $n;
}

// Konversation (allgemein oder zu einem Termin) finden oder anlegen.
// Rückgabe: ['ok' => bool, 'conversation' => ..., 'error' => ...]
function wf_app_conv_open($config, $customer, $appointmentId) {
    $subject = 'Allgemeine Anfrage';
    if ($appointmentId !== null) {
        $appt = wf_app_find_owned_appointment($config, $customer, $appointmentId);
        if (!$appt) return ['ok' => false, 'error' => 'not_found'];
        $subject = 'Termin ' . date('d.m.Y', strtotime($appt['date'])) . ', ' . $appt['time'] . ' Uhr';
    }
    $conv = null;
    wf_app_conv_write($config, function ($data) use ($customer, $appointmentId, $subject, &$conv) {
        foreach ($data as $c) {
            if ($c['customer_id'] === $customer['id'] && $c['appointment_id'] === $appointmentId) { $conv = $c; return false; }
        }
        $now = date('c');
        $conv = [
            'id' => wf_generate_id(),
            'customer_id' => $customer['id'],
            'appointment_id' => $appointmentId,
            'subject' => $subject,
            'created_at' => $now,
            'updated_at' => $now,
            'next_seq' => 1,
            'customer_read_seq' => 0,
            'staff_read_seq' => 0,
            'messages' => [],
        ];
        $data[] = $conv;
        return $data;
    });
    return $conv ? ['ok' => true, 'conversation' => $conv] : ['ok' => false, 'error' => 'save_failed'];
}

// Nachricht anhängen. $sender: 'customer' | 'staff'. Idempotent über $clientMsgId.
// $customerId: wenn gesetzt, muss die Konversation diesem Kunden gehören.
// Rückgabe: ['ok' => bool, 'error' => ..., 'message' => ..., 'conversation' => ..., 'replayed' => bool, 'first_unread' => bool]
function wf_app_conv_append($config, $convId, $sender, $text, $clientMsgId, $customerId = null, $staffUser = null) {
    $max = (int)wf_app_cfg($config)['message_max_length'];
    $text = wf_app_clean_text($text, $max);
    if ($text === '') return ['ok' => false, 'error' => 'empty_message'];
    if ($clientMsgId !== null && !preg_match('/^[A-Za-z0-9-]{8,64}$/', (string)$clientMsgId)) return ['ok' => false, 'error' => 'invalid_request_id'];

    $res = ['ok' => false, 'error' => 'not_found'];
    wf_app_conv_write($config, function ($data) use ($convId, $sender, $text, $clientMsgId, $customerId, $staffUser, &$res) {
        foreach ($data as &$c) {
            if ($c['id'] !== $convId) continue;
            if ($customerId !== null && $c['customer_id'] !== $customerId) break;
            if ($clientMsgId !== null) {
                foreach ($c['messages'] as $m) {
                    if (($m['client_msg_id'] ?? null) === $clientMsgId && $m['sender'] === $sender) {
                        $res = ['ok' => true, 'message' => $m, 'conversation' => $c, 'replayed' => true, 'first_unread' => false];
                        return false;
                    }
                }
            }
            $firstUnread = $sender === 'customer' ? wf_app_conv_unread_for($c, 'staff') === 0 : wf_app_conv_unread_for($c, 'customer') === 0;
            $m = [
                'id' => wf_generate_id(),
                'seq' => (int)$c['next_seq'],
                'sender' => $sender,
                'text' => $text,
                'created_at' => date('c'),
                'client_msg_id' => $clientMsgId,
            ];
            if ($staffUser !== null) $m['staff_user'] = $staffUser;
            $c['messages'][] = $m;
            $c['next_seq'] = $m['seq'] + 1;
            $c['updated_at'] = $m['created_at'];
            // Wer schreibt, hat alles davor gelesen
            if ($sender === 'customer') $c['customer_read_seq'] = $m['seq'];
            else $c['staff_read_seq'] = $m['seq'];
            $res = ['ok' => true, 'message' => $m, 'conversation' => $c, 'replayed' => false, 'first_unread' => $firstUnread];
            unset($c);
            return $data;
        }
        unset($c);
        return false;
    });
    return $res;
}

function wf_app_conv_set_push_status($config, $convId, $msgId, $status) {
    wf_app_conv_write($config, function ($data) use ($convId, $msgId, $status) {
        foreach ($data as &$c) {
            if ($c['id'] !== $convId) continue;
            foreach ($c['messages'] as &$m) if ($m['id'] === $msgId) $m['push_status'] = $status;
            unset($m);
        }
        unset($c);
        return $data;
    });
}

// Als gelesen markieren. $who: 'customer' | 'staff'
function wf_app_conv_mark_read($config, $convId, $who, $customerId = null) {
    $found = false;
    wf_app_conv_write($config, function ($data) use ($convId, $who, $customerId, &$found) {
        foreach ($data as &$c) {
            if ($c['id'] !== $convId) continue;
            if ($customerId !== null && $c['customer_id'] !== $customerId) break;
            $found = true;
            $key = $who === 'customer' ? 'customer_read_seq' : 'staff_read_seq';
            $last = (int)$c['next_seq'] - 1;
            if ((int)$c[$key] === $last) return false; // nichts zu tun
            $c[$key] = $last;
            unset($c);
            return $data;
        }
        unset($c);
        return false;
    });
    return $found;
}

function wf_app_conv_get($config, $convId) {
    foreach (wf_app_conv_read_all($config) as $c) if ($c['id'] === $convId) return $c;
    return null;
}

// Nachrichten seitenweise: $beforeSeq (ältere laden) oder $afterSeq (neue abholen)
function wf_app_conv_messages_page($conv, $viewer, $beforeSeq, $afterSeq, $limit) {
    $limit = max(1, min(100, (int)$limit));
    $msgs = $conv['messages'];
    if ($afterSeq !== null) {
        $msgs = array_values(array_filter($msgs, fn($m) => $m['seq'] > $afterSeq));
        $hasMore = count($msgs) > $limit;
        $msgs = array_slice($msgs, 0, $limit);
    } else {
        if ($beforeSeq !== null) $msgs = array_values(array_filter($msgs, fn($m) => $m['seq'] < $beforeSeq));
        $hasMore = count($msgs) > $limit;
        $msgs = array_slice($msgs, -$limit);
    }
    return [
        'messages' => array_map(fn($m) => wf_app_message_public($m, $conv, $viewer), $msgs),
        'has_more' => $hasMore,
    ];
}

// Konversationen, deren letzte Aktivität länger als die Aufbewahrungsfrist
// (wie bei Terminen: 1 Jahr) zurückliegt, löschen.
function wf_app_purge_conversations($config, $now = null) {
    $removed = 0;
    $cutoff = (new DateTime('@' . ($now ?? time())))->modify(WF_RETENTION);
    wf_app_conv_write($config, function ($data) use ($cutoff, &$removed) {
        $keep = array_values(array_filter($data, function ($c) use ($cutoff) {
            try { return new DateTime($c['updated_at']) >= $cutoff; } catch (Throwable $e) { return true; }
        }));
        $removed = count($data) - count($keep);
        return $removed > 0 ? $keep : false;
    });
    return $removed;
}

// ---- Dashboard-Sicht ----

function wf_app_staff_conversations($config) {
    $customers = [];
    foreach (wf_app_customers($config) as $c) $customers[$c['id']] = $c;
    $byId = wf_app_appointments_by_id($config);
    $out = [];
    foreach (wf_app_conv_read_all($config) as $c) {
        if (!$c['messages']) continue;
        $cust = $customers[$c['customer_id']] ?? null;
        $appt = $c['appointment_id'] ? ($byId[$c['appointment_id']] ?? null) : null;
        $out[] = [
            'id' => $c['id'],
            'customer' => $cust ? ['name' => trim(($cust['vorname'] ?? '') . ' ' . ($cust['nachname'] ?? '')), 'phone' => $cust['phone']] : ['name' => '(gelöschtes Konto)', 'phone' => ''],
            'subject' => $c['subject'],
            'appointment' => $appt ? wf_app_staff_appt_summary($appt) : null,
            'unread' => wf_app_conv_unread_for($c, 'staff'),
            'last_message' => wf_app_conv_last($c),
            'updated_at' => $c['updated_at'],
        ];
    }
    usort($out, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));
    return $out;
}

function wf_app_staff_appt_summary($a) {
    return [
        'id' => $a['id'], 'date' => $a['date'], 'time' => $a['time'], 'end' => wf_appointment_end($a),
        'service_label' => $a['service_label'] ?? '', 'status' => $a['status'] ?? '',
        'name' => trim(($a['vorname'] ?? '') . ' ' . ($a['nachname'] ?? '')),
        'display_status' => wf_app_display_status($a),
    ];
}

function wf_app_staff_unread_total($config) {
    $n = 0;
    foreach (wf_app_conv_read_all($config) as $c) $n += wf_app_conv_unread_for($c, 'staff') > 0 ? 1 : 0;
    return $n;
}

// Telegram-Hinweis an den Friseur – ohne Nachrichtentext, nur einmal pro
// ungelesener Phase einer Konversation (keine Flut bei mehreren Nachrichten).
function wf_app_notify_staff_new_message($config, $conv, $customer) {
    if (!wf_app_cfg($config)['telegram_on_message']) return 'disabled';
    try {
        if (wf_telegram_token($config) === '') return 'not_configured';
        $first = preg_replace('/[^\p{L} \-]/u', '', (string)($customer['vorname'] ?? ''));
        $text = "💬 Neue App-Nachricht" . ($first !== '' ? " von {$first}" : '') . "\n" . $conv['subject']
            . "\n\nBitte im Dashboard unter „App-Chats“ antworten.";
        $ok = true;
        foreach (wf_telegram_recipients($config) as $chatId) {
            if (!wf_telegram_send_to($config, $chatId, $text)) $ok = false;
        }
        return $ok ? 'sent' : 'failed';
    } catch (Throwable $e) {
        error_log('Wolf Friseur Telegram (Nachricht): ' . $e->getMessage());
        return 'failed';
    }
}

// ============================================================
// Konto löschen (App-Store-Pflicht). Termine bleiben als Geschäftsunterlagen
// erhalten (normale Aufbewahrungsfrist), alles App-Bezogene wird entfernt.
// ============================================================

function wf_app_delete_customer($config, $customerId) {
    wf_app_conv_write($config, fn($data) => array_values(array_filter($data, fn($c) => $c['customer_id'] !== $customerId)));
    wf_app_write($config, 'devices.json', fn($data) => array_values(array_filter($data, fn($d) => $d['customer_id'] !== $customerId)), []);
    wf_app_revoke_customer_sessions($config, $customerId);
    return wf_app_write($config, 'customers.json', fn($data) => array_values(array_filter($data, fn($c) => $c['id'] !== $customerId)), []);
}

// ============================================================
// Push-Geräte
// ============================================================

function wf_app_register_device($config, $customerId, $platform, $token, $meta) {
    if (!in_array($platform, ['android', 'ios'], true)) return false;
    if (!preg_match('/^[A-Za-z0-9:_\-\.]{20,4096}$/', (string)$token)) return false;
    $env = ($meta['apns_env'] ?? '') === 'sandbox' ? 'sandbox' : 'production';
    return wf_app_write($config, 'devices.json', function ($data) use ($customerId, $platform, $token, $meta, $env) {
        // Ein Token gehört immer nur zu einem Konto (Gerät gewechselt / neu angemeldet)
        $data = array_values(array_filter($data, fn($d) => $d['token'] !== $token));
        // Höchstens 10 Geräte pro Kunde
        $mine = array_values(array_filter($data, fn($d) => $d['customer_id'] === $customerId));
        if (count($mine) >= 10) {
            usort($mine, fn($a, $b) => strcmp($a['updated_at'], $b['updated_at']));
            $drop = $mine[0]['token'];
            $data = array_values(array_filter($data, fn($d) => $d['token'] !== $drop));
        }
        $data[] = [
            'customer_id' => $customerId,
            'platform' => $platform,
            'provider' => $platform === 'ios' ? 'apns' : 'fcm',
            'apns_env' => $env,
            'token' => $token,
            'locale' => mb_substr(preg_replace('/[^A-Za-z_-]/', '', (string)($meta['locale'] ?? 'de')), 0, 10),
            'app_version' => mb_substr(preg_replace('/[^0-9A-Za-z.\- ()]/', '', (string)($meta['app_version'] ?? '')), 0, 30),
            'updated_at' => date('c'),
        ];
        return $data;
    }, []);
}

function wf_app_unregister_device($config, $customerId, $token) {
    return wf_app_write($config, 'devices.json', function ($data) use ($customerId, $token) {
        return array_values(array_filter($data, fn($d) => !($d['token'] === $token && $d['customer_id'] === $customerId)));
    }, []);
}

function wf_app_customer_devices($config, $customerId) {
    return array_values(array_filter(wf_app_read($config, 'devices.json', []), fn($d) => $d['customer_id'] === $customerId));
}

// ============================================================
// Push-Versand
// ------------------------------------------------------------
// Inhalt bewusst knapp und ohne sensible Daten (kein Name, keine Nachricht).
// Doppelte Zustellung desselben Ereignisses wird über $dedupeKey verhindert.
// Rückgabe: 'sent' | 'no_device' | 'disabled' | 'not_configured' | 'failed' | 'duplicate'
// ============================================================

function wf_app_push_to_customer($config, $customer, $kind, $title, $body, $data, $dedupeKey = null) {
    try {
        $prefs = ($customer['prefs'] ?? []) + wf_app_default_prefs();
        if ($kind === 'message' && !$prefs['push_messages']) return 'disabled';
        if ($kind === 'appointment' && !$prefs['push_appointments']) return 'disabled';
        $devices = wf_app_customer_devices($config, $customer['id']);
        if (!$devices) return 'no_device';

        if ($dedupeKey !== null) {
            $dup = false;
            $now = time();
            wf_app_write($config, 'push_log.json', function ($log) use ($dedupeKey, $now, &$dup) {
                foreach ($log as $k => $t) if ($t < $now - 30 * 86400) unset($log[$k]);
                if (isset($log[$dedupeKey])) { $dup = true; return false; }
                $log[$dedupeKey] = $now;
                return $log;
            }, []);
            if ($dup) return 'duplicate';
        }

        $anyConfigured = false;
        $anySent = false;
        foreach ($devices as $d) {
            $r = $d['provider'] === 'apns'
                ? wf_app_apns_send($config, $d, $title, $body, $data)
                : wf_app_fcm_send($config, $d, $title, $body, $data);
            if ($r === 'not_configured') continue;
            $anyConfigured = true;
            if ($r === 'sent') $anySent = true;
            if ($r === 'invalid_token') wf_app_unregister_device($config, $customer['id'], $d['token']);
        }
        if (!$anyConfigured) return 'not_configured';
        return $anySent ? 'sent' : 'failed';
    } catch (Throwable $e) {
        error_log('Wolf Friseur Push: ' . $e->getMessage());
        return 'failed';
    }
}

// Termin-Ereignis an alle App-Konten mit derselben (verifizierten) Nummer
// $event: accepted | rejected | cancelled_by_salon | changed | rescheduled
function wf_app_notify_appointment($config, $a, $event) {
    if (($a['type'] ?? '') === 'block' || trim($a['telefon'] ?? '') === '') return 'no_device';
    $customers = wf_app_customers_by_phone($config, $a['telefon']);
    if (!$customers) return 'no_device';
    $when = wf_sms_date($a['date']) . ', ' . $a['time'] . ' Uhr';
    switch ($event) {
        case 'accepted':           $title = 'Termin bestätigt'; $body = "Ihr Termin am {$when} ist bestätigt."; break;
        case 'rescheduled':        $title = 'Neuer Termin bestätigt'; $body = "Ihr verschobener Termin am {$when} ist bestätigt."; break;
        case 'rejected':           $title = 'Termin nicht möglich'; $body = "Ihr Wunschtermin am {$when} konnte leider nicht angenommen werden."; break;
        case 'cancelled_by_salon': $title = 'Termin abgesagt'; $body = "Ihr Termin am {$when} wurde vom Salon abgesagt."; break;
        case 'changed':            $title = 'Termin geändert'; $body = "Ihr Termin wurde geändert: {$when}."; break;
        default: return 'failed';
    }
    $key = 'appt:' . $a['id'] . ':' . $event . ':' . $a['date'] . 'T' . $a['time'];
    $best = 'no_device';
    $rank = ['sent' => 5, 'failed' => 4, 'duplicate' => 3, 'not_configured' => 2, 'disabled' => 1, 'no_device' => 0];
    foreach ($customers as $c) {
        $r = wf_app_push_to_customer($config, $c, 'appointment', $title, $body,
            ['type' => 'appointment', 'appointment_id' => $a['id']], $key . ':' . $c['id']);
        if ($rank[$r] > $rank[$best]) $best = $r;
    }
    return $best;
}

// HTTP-POST mit JSON-Körper. Rückgabe: ['code' => int, 'body' => string]
function wf_app_http_json($url, $payload, $headers, $http2 = false) {
    $body = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (!function_exists('curl_init')) return ['code' => 0, 'body' => 'curl missing'];
    $ch = curl_init($url);
    $opts = [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($http2 && defined('CURL_HTTP_VERSION_2_0')) $opts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2_0;
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => (string)$resp];
}

// ---- FCM HTTP v1 (Android) ----

function wf_app_fcm_account($config) {
    $file = wf_app_cfg($config)['push']['fcm_service_account_file'];
    if ($file === '' || !is_readable($file)) return null;
    $sa = json_decode((string)file_get_contents($file), true);
    if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key']) || empty($sa['project_id'])) return null;
    return $sa;
}

function wf_app_fcm_access_token($config, $sa) {
    $state = wf_app_read($config, 'push_state.json', []);
    if (!empty($state['fcm_token']) && ($state['fcm_expires'] ?? 0) > time() + 60) return $state['fcm_token'];
    $now = time();
    $tokenUri = $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token';
    $jwt = wf_app_b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . wf_app_b64url(json_encode([
        'iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => $tokenUri, 'iat' => $now, 'exp' => $now + 3600,
    ]));
    $sig = '';
    if (!openssl_sign($jwt, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) return null;
    $jwt .= '.' . wf_app_b64url($sig);
    $res = wf_http_post($tokenUri, ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]);
    $data = json_decode($res['body'], true);
    if (!$res['ok'] || empty($data['access_token'])) {
        error_log('Wolf Friseur FCM OAuth fehlgeschlagen: HTTP ' . $res['code']);
        return null;
    }
    $token = $data['access_token'];
    $exp = $now + (int)($data['expires_in'] ?? 3600);
    wf_app_write($config, 'push_state.json', function ($s) use ($token, $exp) { $s['fcm_token'] = $token; $s['fcm_expires'] = $exp; return $s; }, []);
    return $token;
}

// Rückgabe: 'sent' | 'invalid_token' | 'failed' | 'not_configured'
function wf_app_fcm_send($config, $device, $title, $body, $data) {
    $sa = wf_app_fcm_account($config);
    if (!$sa) return 'not_configured';
    $access = wf_app_fcm_access_token($config, $sa);
    if (!$access) return 'failed';
    $payload = ['message' => [
        'token' => $device['token'],
        'notification' => ['title' => $title, 'body' => $body],
        'data' => array_map('strval', $data),
        'android' => ['priority' => 'high', 'notification' => ['channel_id' => ($data['type'] ?? '') === 'message' ? 'messages' : 'appointments']],
    ]];
    $res = wf_app_http_json('https://fcm.googleapis.com/v1/projects/' . rawurlencode($sa['project_id']) . '/messages:send',
        $payload, ['Content-Type: application/json', 'Authorization: Bearer ' . $access]);
    if ($res['code'] >= 200 && $res['code'] < 300) return 'sent';
    if ($res['code'] === 404 || strpos($res['body'], 'UNREGISTERED') !== false
        || ($res['code'] === 400 && strpos($res['body'], 'registration token') !== false)) return 'invalid_token';
    error_log('Wolf Friseur FCM fehlgeschlagen: HTTP ' . $res['code']);
    return 'failed';
}

// ---- APNs (iOS), Token-basierte Authentifizierung (.p8) ----

// DER-kodierte ECDSA-Signatur -> 64 Byte r||s (für JWT ES256)
function wf_app_der_to_raw($der) {
    $pos = 2;
    if (ord($der[1]) & 0x80) $pos += ord($der[1]) & 0x7f;
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $pos++; // 0x02
        $len = ord($der[$pos++]);
        $int = substr($der, $pos, $len);
        $pos += $len;
        $int = ltrim($int, "\x00");
        $out .= str_pad($int, 32, "\x00", STR_PAD_LEFT);
    }
    return $out;
}

function wf_app_apns_jwt($config) {
    $p = wf_app_cfg($config)['push'];
    if ($p['apns_key_file'] === '' || !is_readable($p['apns_key_file']) || $p['apns_key_id'] === '' || $p['apns_team_id'] === '') return null;
    $state = wf_app_read($config, 'push_state.json', []);
    if (!empty($state['apns_jwt']) && ($state['apns_iat'] ?? 0) > time() - 2400 && ($state['apns_kid'] ?? '') === $p['apns_key_id']) return $state['apns_jwt'];
    $now = time();
    $jwt = wf_app_b64url(json_encode(['alg' => 'ES256', 'kid' => $p['apns_key_id']])) . '.' . wf_app_b64url(json_encode(['iss' => $p['apns_team_id'], 'iat' => $now]));
    $key = openssl_pkey_get_private((string)file_get_contents($p['apns_key_file']));
    $sig = '';
    if (!$key || !openssl_sign($jwt, $sig, $key, OPENSSL_ALGO_SHA256)) return null;
    $jwt .= '.' . wf_app_b64url(wf_app_der_to_raw($sig));
    $kid = $p['apns_key_id'];
    wf_app_write($config, 'push_state.json', function ($s) use ($jwt, $now, $kid) { $s['apns_jwt'] = $jwt; $s['apns_iat'] = $now; $s['apns_kid'] = $kid; return $s; }, []);
    return $jwt;
}

function wf_app_apns_send($config, $device, $title, $body, $data) {
    $p = wf_app_cfg($config)['push'];
    $jwt = wf_app_apns_jwt($config);
    if (!$jwt) return 'not_configured';
    $sandbox = ($device['apns_env'] ?? '') === 'sandbox' || $p['apns_sandbox'];
    $host = $sandbox ? 'https://api.sandbox.push.apple.com' : 'https://api.push.apple.com';
    $payload = ['aps' => ['alert' => ['title' => $title, 'body' => $body], 'sound' => 'default']] + array_map('strval', $data);
    $res = wf_app_http_json($host . '/3/device/' . rawurlencode($device['token']), $payload, [
        'authorization: bearer ' . $jwt,
        'apns-topic: ' . $p['apns_bundle_id'],
        'apns-push-type: alert',
        'apns-priority: 10',
        'content-type: application/json',
    ], true);
    if ($res['code'] === 200) return 'sent';
    if ($res['code'] === 410 || strpos($res['body'], 'BadDeviceToken') !== false || strpos($res['body'], 'Unregistered') !== false) return 'invalid_token';
    error_log('Wolf Friseur APNs fehlgeschlagen: HTTP ' . $res['code'] . ' ' . substr($res['body'], 0, 200));
    return 'failed';
}
