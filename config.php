<?php
// ============================================================
// Wolf Friseur – Buchungssystem – Konfiguration
// ============================================================
// Hier alle Einstellungen anpassen (Öffnungszeiten, Leistungen,
// Admin-Zugang, E-Mail-Adresse für Benachrichtigungen).
// ============================================================

return [

    // Öffnungszeiten pro Wochentag: [Öffnung, Schließung] oder null = geschlossen
    'business_hours' => [
        'mon' => ['09:00', '19:00'],
        'tue' => ['09:00', '19:00'],
        'wed' => ['09:00', '19:00'],
        'thu' => ['09:00', '19:00'],
        'fri' => ['09:00', '19:00'],
        'sat' => ['09:00', '16:00'],
        'sun' => null,
    ],

    // Früher: festes Zeitraster. Wird NICHT mehr verwendet – Termine können
    // jetzt zu jeder Minute beginnen (bleibt nur aus Kompatibilitätsgründen).
    'slot_minutes' => 30,

    // Raster der Uhrzeiten auf der Buchungsseite: 15 = 09:00, 09:15, 09:30 …
    // Angezeigt werden nur Zeiten, an denen die ganze Leistung frei ist.
    // Im Dashboard können Termine weiterhin zu jeder Minute eingetragen werden.
    // Der Mindestvorlauf für Online-Buchungen wird im Dashboard unter
    // "Einstellungen" festgelegt.
    'suggestion_step_minutes' => 15,

    // Wie viele Tage im Voraus gebucht werden darf (0 = nur heute, 7 = heute + 7 Tage)
    'booking_window_days' => 7,

    // HINWEIS: Die Leistungen (Haarschnitt, Bart, VIP-Pakete usw.) werden seit
    // dem Update NICHT mehr hier fest eingetragen, sondern dynamisch in der
    // Datei "services.json" gespeichert (wird automatisch angelegt). Sie
    // können sie bequem über das Dashboard verwalten (Tab "Leistungen"):
    // Namen, Preise, Beschreibungen und Dauer (beliebige Minutenzahl) jederzeit
    // ändern, ohne diese Datei zu bearbeiten. Eine geänderte Dauer gilt nur
    // für neue Buchungen.

    // E-Mail-Adresse, an die bei jeder neuen Buchung eine Benachrichtigung geschickt wird
    'owner_email' => 'info@wolf-friseur.de',

    // SMS-Benachrichtigungen
    //  - Bei jeder neuen Buchung bekommt der Friseur eine SMS mit den Details.
    //  - Nimmt der Friseur den Termin im Dashboard an oder lehnt ihn ab,
    //    bekommt der Kunde eine SMS.
    // Anbieter: 'seven' (seven.io, deutscher Anbieter) oder 'twilio'.
    // Leer lassen ('') = SMS ausgeschaltet (Buchungen funktionieren trotzdem).
    'sms' => [
        'provider'     => 'seven',                 // 'seven' | 'twilio' | ''
        'owner_phone'  => '+49 151 00000000',                 // Handy des Friseurs, z.B. '+49 151 00000000'
        'sender'       => 'WolfFriseur',      // Absendername (max. 11 Zeichen, nur Buchstaben/Ziffern)

        // seven.io: API-Key aus dem seven.io-Konto (Einstellungen -> API-Keys)
        'seven_api_key' => '',

        // Twilio: Account SID + Auth Token aus der Twilio-Console.
        // Bei Twilio muss 'sender' eine gekaufte Twilio-Nummer (z.B. '+4915...')
        // oder ein freigeschalteter Alphanumeric Sender sein.
        'twilio_sid'   => '',
        'twilio_token' => '',
    ],

    // Telegram-Benachrichtigung an den Friseur (zusätzlich zur SMS, unabhängig davon)
    // Bot-Token von @BotFather hier eintragen – ODER (bevorzugt) als
    // Umgebungsvariable WF_TELEGRAM_BOT_TOKEN setzen. Leer = Telegram aus.
    // Der Chat wird danach im Dashboard über "Telegram verbinden" verknüpft.
    'telegram' => [
        'bot_token' => '',
        'chat_ids'  => [],   // optional: zusätzliche feste Chat-IDs (normalerweise leer lassen)
    ],

    // Ein Kunde darf seinen Termin online nur stornieren, wenn noch mindestens
    // so viele Stunden bis zum Termin übrig sind. Danach muss er anrufen.
    'self_cancel_min_hours' => 3,

    // Telefonnummer, die dem Kunden angezeigt wird, falls er zu kurzfristig stornieren möchte
    'salon_phone' => '+49 151 00000000',

    // Admin-Zugang zum Dashboard
    // Standard-Benutzername: admin
    // Standard-Passwort:     WolfFriseur2026!
    // WICHTIG: Bitte das Passwort so bald wie möglich ändern (siehe README unten).
    // HINWEIS: Sobald das Passwort einmal über den Dashboard-Tab "Einstellungen"
    // geändert wurde, wird der neue Wert in "admin_credentials.json" gespeichert
    // und diese beiden Zeilen hier werden nicht mehr verwendet.
    'admin_username' => 'admin',
    'admin_password_hash' => '',

    // Datei, in der alle Termine gespeichert werden
    'data_file' => __DIR__ . '/appointments.json',
];

// Um das Passwort zu ändern: in einer lokalen PHP-Umgebung (oder per Online-Tool)
// folgenden Code ausführen und den Wert oben bei 'admin_password_hash' ersetzen:
//   echo password_hash('NeuesPasswort123', PASSWORD_DEFAULT);