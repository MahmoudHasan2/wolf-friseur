<?php
// ============================================================
// Wolf Friseur – Aufbewahrungsfrist der Termine (Cronjob)
// Löscht Termine, deren ERSTELLUNG ('created_at') länger als 1 Jahr
// zurückliegt. Termine ohne lesbares Erstelldatum bleiben erhalten.
//
// Hostinger hPanel -> Erweitert -> Cron Jobs, z.B. täglich 03:30:
//   /usr/bin/php /home/<BENUTZER>/domains/wolf-friseur.de/public_html/cron-cleanup.php
// Ohne Cronjob läuft dieselbe Bereinigung automatisch einmal täglich,
// sobald das Dashboard geöffnet wird.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/app-lib.php'; // lädt functions.php mit
$config = wf_load_config();
$removed = wf_purge_expired_appointments($config);
echo date('Y-m-d H:i:s') . " – {$removed} abgelaufene(r) Termin(e) gelöscht.\n";
$removedConv = wf_app_purge_conversations($config);
echo date('Y-m-d H:i:s') . " – {$removedConv} abgelaufene App-Konversation(en) gelöscht.\n";
