<?php
// Temporäre Diagnose: Welche Öffnungszeiten liest der Server? Nach dem Test LÖSCHEN.
header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/functions.php';

echo "PHP-Version: " . PHP_VERSION . "\n";
echo "Server-Zeit: " . date('Y-m-d H:i:s T') . "\n";
echo "config.php: " . (file_exists(__DIR__ . '/config.php') ? filesize(__DIR__ . '/config.php') . ' Bytes, geändert ' . date('Y-m-d H:i', filemtime(__DIR__ . '/config.php')) : 'FEHLT') . "\n\n";

$config = wf_load_config();
echo "wf_load_config() liefert: " . gettype($config) . "\n";
if (!is_array($config)) exit("FEHLER: config.php gibt kein Array zurück (fehlt 'return [' ?)\n");

echo "business_hours:\n";
var_export($config['business_hours'] ?? 'FEHLT');
echo "\n\nNächste 8 Tage:\n";
for ($i = 0; $i <= 7; $i++) {
    $date = date('Y-m-d', strtotime("+{$i} day"));
    $h = wf_business_hours($date, $config);
    echo $date . ' (' . wf_day_key($date) . '): ' . ($h ? implode(' - ', $h) : 'GESCHLOSSEN') . "\n";
}
