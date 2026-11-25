<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage.']);
    exit;
}

function clean_line($str) {
    $str = trim($str ?? '');
    return str_replace(["\r", "\n"], '', $str);
}

$vorname   = clean_line($_POST['vorname'] ?? '');
$nachname  = clean_line($_POST['nachname'] ?? '');
$telefon   = clean_line($_POST['telefon'] ?? '');
$email     = clean_line($_POST['email'] ?? '');
$nachricht = trim($_POST['nachricht'] ?? '');

if ($vorname === '' || $nachname === '') {
    echo json_encode(['success' => false, 'message' => 'Bitte Vor- und Nachnamen angeben.']);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Bitte eine gültige E-Mail-Adresse angeben.']);
    exit;
}

$to      = 'info@wolf-friseur.de';
$subject = 'Neue Terminanfrage von ' . $vorname . ' ' . $nachname;

$body  = "Neue Terminanfrage über die Website wolf-friseur.de\n\n";
$body .= "Vorname: {$vorname}\n";
$body .= "Nachname: {$nachname}\n";
$body .= "Telefon: " . ($telefon !== '' ? $telefon : '-') . "\n";
$body .= "E-Mail: " . ($email !== '' ? $email : '-') . "\n\n";
$body .= "Nachricht:\n" . ($nachricht !== '' ? $nachricht : '-') . "\n";

$fromEmail = 'info@wolf-friseur.de';
$fromName  = 'Wolf Friseur Website';

$headers  = "From: {$fromName} <{$fromEmail}>\r\n";
if ($email !== '') {
    $headers .= "Reply-To: {$email}\r\n";
}
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$sent = mail($to, $encodedSubject, $body, $headers);

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Server konnte die E-Mail nicht senden.']);
}