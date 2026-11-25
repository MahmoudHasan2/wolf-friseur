<?php
require __DIR__ . '/functions.php';
$config = wf_load_config();

$days = [];
$weekdayNames = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
for ($i = 0; $i <= (int)$config['booking_window_days']; $i++) {
    $date = date('Y-m-d', strtotime("+{$i} day"));
    $days[] = [
        'date' => $date,
        'label' => $weekdayNames[(int)date('w', strtotime($date))],
        'short' => date('d.m.', strtotime($date)),
        'closed' => wf_business_hours($date, $config) === null,
    ];
}

// Herren-Leistungen (inkl. der neuen Pakete) + fester Kinder-Eintrag, aus services.json
$allServices = wf_load_services($config);
$herrServices = array_values(array_filter($allServices, fn($s) => ($s['category'] ?? '') === 'herr'));
usort($herrServices, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
$servicesKeyed = [];
foreach ($allServices as $s) $servicesKeyed[$s['key']] = $s;

$faqs = wf_load_faqs($config);
usort($faqs, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

$fmtPrice = function ($p) {
    if ($p === null || $p === '') return '';
    return number_format((float)$p, 2, ',', '.') . ' €';
};
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Termin buchen | Wolf Friseur</title>
<meta name="robots" content="noindex">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="icon" type="image/png" href="https://i.ibb.co/pvm4r092/Element-255.png">
<style>
@import url('https://fonts.cdnfonts.com/css/itc-serif-gothic');
:root{
  --black:#282828; --white:#ffffff; --blue:#2f519f; --blue-light:#3d6bc7; --blue-glow:#5b85e0;
  --red:#c41e3a; --gray:#323232; --gray-light:#3a3a3a;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Montserrat',sans-serif;background:var(--black);color:var(--white);min-height:100vh;}
h1,h2,h3,.logo-text{font-family:'ITC Serif Gothic','Playfair Display',serif !important;font-weight:900 !important;}

.navbar{position:sticky;top:0;width:100%;padding:16px 30px;display:flex;justify-content:space-between;align-items:center;
  background:rgba(40,40,40,0.96);backdrop-filter:blur(20px);border-bottom:1px solid rgba(47,81,159,0.3);z-index:100;}
.logo{display:flex;align-items:center;gap:12px;text-decoration:none;}
.logo-icon{width:40px;height:40px;}
.logo-icon img{width:100%;height:100%;object-fit:contain;}
.logo-text{font-size:1.2rem;color:var(--white);letter-spacing:2px;}
.logo-text span{color:var(--blue);}
.back-link{color:rgba(255,255,255,0.7);text-decoration:none;font-size:0.85rem;letter-spacing:1px;transition:color .3s;}
.back-link:hover{color:var(--blue-glow);}

.wrap{max-width:760px;margin:0 auto;padding:50px 20px 100px;}
.page-title{text-align:center;margin-bottom:10px;}
.section-label{display:block;text-align:center;padding:8px 22px;border:1px solid var(--blue);border-radius:50px;
  font-size:0.75rem;letter-spacing:3px;text-transform:uppercase;color:var(--blue-glow);margin:0 auto 18px;max-width:200px;}
.page-title h1{font-size:clamp(1.8rem,6vw,2.8rem);text-align:center;margin-bottom:10px;}
.page-title p{text-align:center;color:rgba(255,255,255,0.6);margin-bottom:40px;font-size:0.95rem;}

.card{background:rgba(255,255,255,0.03);border:1px solid rgba(47,81,159,0.3);border-radius:18px;padding:28px 24px;margin-bottom:22px;}
.card h3{font-size:1.15rem;margin-bottom:18px;color:var(--blue-glow);letter-spacing:1px;}
.card.disabled{opacity:0.4;pointer-events:none;}

.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-group{margin-bottom:16px;}
.form-group label{display:block;margin-bottom:7px;font-size:0.78rem;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,0.6);}
.form-group input{width:100%;padding:13px 16px;background:rgba(255,255,255,0.05);border:1px solid rgba(47,81,159,0.3);
  border-radius:10px;color:var(--white);font-family:'Montserrat',sans-serif;font-size:0.95rem;outline:none;transition:all .3s;}
.form-group input:focus{border-color:var(--blue-glow);box-shadow:0 0 14px rgba(47,81,159,0.25);}

.choice-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.choice-card{position:relative;}
.choice-card input{position:absolute;opacity:0;}
.choice-card label{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;
  padding:16px 10px;border:1.5px solid rgba(47,81,159,0.35);border-radius:12px;cursor:pointer;text-align:center;
  transition:all .25s;background:rgba(255,255,255,0.02);min-height:78px;}
.choice-card label .t{font-weight:600;font-size:0.92rem;}
.choice-card label .d{font-size:0.75rem;color:rgba(255,255,255,0.55);}
.choice-card input:checked + label{border-color:var(--blue-glow);background:rgba(47,81,159,0.22);box-shadow:0 0 16px rgba(47,81,159,0.3);}
.choice-card input:checked + label .t{color:var(--blue-glow);}

/* ---- Leistungs-Auswahl (Herren): größere Karten mit Preis + Beschreibung ---- */
.service-list{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.service-card{position:relative;}
.service-card input{position:absolute;opacity:0;}
.service-card label{display:flex;flex-direction:column;gap:6px;padding:16px 16px;border:1.5px solid rgba(47,81,159,0.35);
  border-radius:14px;cursor:pointer;transition:all .25s;background:rgba(255,255,255,0.02);height:100%;}
.service-card .row{display:flex;justify-content:space-between;align-items:baseline;gap:8px;}
.service-card .name{font-weight:700;font-size:1rem;}
.service-card .price{font-weight:700;font-size:0.98rem;color:var(--blue-glow);white-space:nowrap;}
.service-card .dur{font-size:0.72rem;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:1px;}
.service-card .desc{font-size:0.8rem;line-height:1.5;color:rgba(255,255,255,0.6);}
.service-card input:checked + label{border-color:var(--blue-glow);background:rgba(47,81,159,0.22);box-shadow:0 0 16px rgba(47,81,159,0.3);}
.service-card input:checked + label .name{color:var(--blue-glow);}
@media (max-width:520px){.service-list{grid-template-columns:1fr;}}

/* ---- FAQ / Rückfragen-Karte ---- */
.faq-yesno{display:flex;gap:12px;}
.faq-yesno button{flex:1;padding:12px;border-radius:12px;border:1.5px solid rgba(47,81,159,0.35);
  background:rgba(255,255,255,0.02);color:var(--white);font-family:'Montserrat',sans-serif;font-size:0.88rem;
  font-weight:600;cursor:pointer;transition:all .2s;}
.faq-yesno button:hover{border-color:var(--blue-glow);background:rgba(47,81,159,0.18);}
.faq-item{border:1px solid rgba(47,81,159,0.25);border-radius:12px;padding:14px 16px;margin-bottom:10px;background:rgba(255,255,255,0.02);}
.faq-item .q{font-weight:600;font-size:0.9rem;margin-bottom:6px;color:var(--blue-glow);}
.faq-item .a{font-size:0.85rem;line-height:1.6;color:rgba(255,255,255,0.75);}
.faq-helpful{text-align:center;margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.08);}
.faq-helpful p{font-size:0.85rem;color:rgba(255,255,255,0.6);margin-bottom:10px;}
.faq-feedback{display:none;margin-top:14px;}
.faq-feedback.show{display:block;}
.faq-feedback textarea{width:100%;min-height:90px;padding:13px 16px;background:rgba(255,255,255,0.05);
  border:1px solid rgba(47,81,159,0.3);border-radius:10px;color:var(--white);font-family:'Montserrat',sans-serif;
  font-size:0.9rem;resize:vertical;outline:none;margin-bottom:10px;}
.faq-feedback textarea:focus{border-color:var(--blue-glow);}
.faq-thanks{font-size:0.85rem;color:var(--blue-glow);text-align:center;padding:10px 0;display:none;}
.faq-thanks.show{display:block;}
.reply-item{background:rgba(255,255,255,0.03);border:1px solid rgba(47,81,159,0.25);border-radius:12px;
  padding:14px 16px;margin-bottom:10px;}
.reply-item .q{font-size:0.8rem;color:rgba(255,255,255,0.55);margin-bottom:8px;}
.reply-item .a{font-size:0.9rem;line-height:1.6;}
.reply-item .a b{color:var(--blue-glow);}

.day-tabs{display:flex;gap:8px;overflow-x:auto;padding-bottom:8px;margin-bottom:20px;}
.day-tab{flex:0 0 auto;padding:10px 16px;border-radius:12px;border:1.5px solid rgba(47,81,159,0.3);
  background:rgba(255,255,255,0.02);cursor:pointer;text-align:center;transition:all .25s;min-width:66px;}
.day-tab .dow{font-size:0.72rem;color:rgba(255,255,255,0.55);text-transform:uppercase;letter-spacing:1px;}
.day-tab .dm{font-weight:700;font-size:0.95rem;margin-top:2px;}
.day-tab.active{border-color:var(--blue-glow);background:rgba(47,81,159,0.25);box-shadow:0 0 14px rgba(47,81,159,0.3);}
.day-tab.today .dow{color:var(--blue-glow);}

.slots-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(82px,1fr));gap:10px;}
.slot-btn{padding:11px 6px;border-radius:10px;border:1.5px solid rgba(47,81,159,0.3);background:rgba(255,255,255,0.02);
  color:var(--white);font-family:'Montserrat',sans-serif;font-size:0.88rem;font-weight:500;cursor:pointer;transition:all .2s;}
.slot-btn:hover:not(:disabled){border-color:var(--blue-glow);background:rgba(47,81,159,0.18);}
.slot-btn.selected{border-color:var(--blue-glow);background:var(--blue);box-shadow:0 0 14px rgba(47,81,159,0.5);}
.slot-btn:disabled{opacity:0.28;cursor:not-allowed;text-decoration:line-through;}
.slots-empty{color:rgba(255,255,255,0.5);font-size:0.9rem;padding:14px 0;text-align:center;}

.btn{padding:15px 32px;font-size:0.9rem;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;border:none;
  border-radius:50px;cursor:pointer;transition:all .3s;width:100%;}
.btn-primary{background:linear-gradient(135deg,var(--blue),var(--blue-light));color:var(--white);box-shadow:0 10px 34px rgba(47,81,159,0.4);}
.btn-primary:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 16px 44px rgba(47,81,159,0.55);}
.btn-primary:disabled{opacity:0.4;cursor:not-allowed;transform:none;}

.summary{font-size:0.88rem;color:rgba(255,255,255,0.65);text-align:center;margin-bottom:16px;min-height:20px;}
.summary strong{color:var(--blue-glow);}

.msg{padding:16px 18px;border-radius:12px;margin-bottom:20px;font-size:0.9rem;line-height:1.6;display:none;white-space:pre-line;}
.msg.show{display:block;}
.msg.success{background:rgba(47,81,159,0.15);border:1px solid var(--blue-glow);}
.msg.error{background:rgba(196,30,58,0.12);border:1px solid var(--red);}

.hint{font-size:0.78rem;color:rgba(255,255,255,0.45);margin-top:6px;}

.cancel-toggle{display:block;text-align:center;margin:30px 0 10px;color:rgba(255,255,255,0.55);
  font-size:0.85rem;text-decoration:underline;cursor:pointer;background:none;border:none;width:100%;font-family:'Montserrat',sans-serif;}
.cancel-toggle:hover{color:var(--blue-glow);}
.cancel-panel{display:none;}
.cancel-panel.show{display:block;}
.cancel-result{margin-top:16px;}
.cancel-item{background:rgba(255,255,255,0.03);border:1px solid rgba(47,81,159,0.25);border-radius:12px;
  padding:14px 16px;margin-bottom:10px;display:flex;flex-wrap:wrap;align-items:center;gap:10px;justify-content:space-between;}
.cancel-item .info{font-size:0.88rem;}
.cancel-item .info .d{font-weight:600;}
.cancel-item .info .s{color:rgba(255,255,255,0.55);font-size:0.8rem;}
.btn-cancel-item{padding:8px 16px;border-radius:20px;font-size:0.78rem;border:1px solid var(--red);
  background:rgba(196,30,58,0.12);color:var(--red);cursor:pointer;letter-spacing:0.5px;}
.btn-cancel-item:hover{background:rgba(196,30,58,0.22);}
.btn-cancel-item:disabled{opacity:0.4;cursor:not-allowed;}
.too-late-note{font-size:0.78rem;color:rgba(255,255,255,0.5);margin-top:4px;}

/* ---- Treuekarte nach der Buchung (aktueller Stand) ---- */
.loyalty-card{display:none;text-align:center;}
.loyalty-card.show{display:block;}
.stamps{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;max-width:320px;margin:6px auto 14px;}
.stamp{aspect-ratio:1;border-radius:50%;border:1.5px dashed rgba(91,133,224,0.5);display:flex;align-items:center;justify-content:center;
  font-size:0.9rem;color:rgba(255,255,255,0.3);}
.stamp.on{border-style:solid;border-color:var(--blue-glow);background:rgba(47,81,159,0.35);color:var(--white);box-shadow:0 0 10px rgba(47,81,159,0.4);}
.loyalty-count{font-size:1.4rem;font-weight:700;color:var(--blue-glow);margin-bottom:6px;}

/* ---- Cookie-Zustimmung ---- */
.cookie-banner{display:none;position:fixed;bottom:0;left:0;right:0;z-index:400;background:rgba(20,20,20,0.98);
  border-top:1px solid rgba(47,81,159,0.4);padding:18px 20px;backdrop-filter:blur(10px);
  box-shadow:0 -8px 30px rgba(0,0,0,0.4);}
.cookie-banner.show{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:16px;}
.cookie-banner p{max-width:640px;font-size:0.82rem;line-height:1.5;color:rgba(255,255,255,0.75);margin:0;}
.cookie-actions{display:flex;gap:10px;flex-shrink:0;}
.cookie-actions .btn{padding:10px 18px;font-size:0.8rem;}
@media (max-width:520px){.cookie-banner{padding:16px;}.cookie-actions{width:100%;}.cookie-actions .btn{flex:1;}}
</style>
</head>
<body>

<nav class="navbar">
  <a class="logo" href="index.html">
    <div class="logo-icon"><img src="https://i.ibb.co/pvm4r092/Element-255.png" alt="Wolf Friseur Logo"></div>
    <div class="logo-text">WOLF <span>FRISEUR</span></div>
  </a>
  <a class="back-link" href="index.html"><i class="fas fa-arrow-left"></i> Zur Startseite</a>
</nav>

<div class="wrap">
  <div class="page-title">
    <span class="section-label">Termin buchen</span>
    <h1>Ihr <span style="color:var(--blue-glow)">Wunschtermin</span></h1>
    <p>Wählen Sie Ihre Daten, die gewünschte Leistung und eine freie Uhrzeit.</p>
  </div>

  <div id="msgBox" class="msg"></div>

  <!-- Treuekarte: wird nach erfolgreicher Buchung mit dem aktuellen Stand angezeigt -->
  <div class="card loyalty-card" id="loyaltyCard">
    <h3>Ihre Treuekarte</h3>
    <div class="loyalty-count" id="loyaltyCount"></div>
    <div class="stamps" id="loyaltyStamps"></div>
    <p class="hint" id="loyaltyHint"></p>
  </div>

  <!-- Meine gebuchten Termine (nur sichtbar, wenn Cookie-Zustimmung + gespeicherter Termin vorhanden) -->
  <div class="card" id="myAppointmentsCard" style="display:none;">
    <h3>Ihre gebuchten Termine</h3>
    <div class="cancel-result" id="myAppointmentsList"></div>
  </div>

  <!-- Step 1: Persönliche Daten -->
  <div class="card" id="cardPersonal">
    <h3>1. Ihre Daten</h3>
    <div class="form-row">
      <div class="form-group">
        <label>Vorname</label>
        <input type="text" id="vorname" placeholder="Vorname">
      </div>
      <div class="form-group">
        <label>Nachname</label>
        <input type="text" id="nachname" placeholder="Nachname">
      </div>
    </div>
    <div class="form-group">
      <label>Mobilnummer</label>
      <input type="tel" id="telefon" placeholder="+49 ...">
    </div>
    <div class="form-group">
      <label>Passwort für Ihre Treuekarte (optional)</label>
      <input type="password" id="loyaltyPassword" placeholder="Mindestens 4 Zeichen" autocomplete="new-password">
      <p class="hint">Mit jeder Buchung sammeln Sie Besuche auf Ihrer Treuekarte – nach 10 Besuchen erhalten Sie 7 € Rabatt auf Ihren Haarschnitt. Nur bei Ihrer ersten Buchung nötig. Leer lassen = die letzten 4 Ziffern Ihrer Mobilnummer.</p>
    </div>
    <div class="form-group">
      <label>Wer bekommt den Termin?</label>
      <div class="choice-grid">
        <div class="choice-card">
          <input type="radio" name="type" id="typeMann" value="mann">
          <label for="typeMann"><span class="t"><i class="fas fa-user"></i> Herr</span></label>
        </div>
        <div class="choice-card">
          <input type="radio" name="type" id="typeKind" value="kind">
          <label for="typeKind"><span class="t"><i class="fas fa-child"></i> Kind</span><span class="d"><?php echo (int)($servicesKeyed['kind']['duration'] ?? 30); ?> Min</span></label>
        </div>
      </div>
    </div>
  </div>

  <!-- Step 2: Leistung (nur für Herren) -->
  <div class="card" id="cardService" style="display:none;">
    <h3>2. Gewünschte Leistung</h3>
    <div class="service-list">
      <?php foreach ($herrServices as $s): ?>
      <div class="service-card">
        <input type="radio" name="service" id="svc_<?php echo htmlspecialchars($s['key']); ?>" value="<?php echo htmlspecialchars($s['key']); ?>">
        <label for="svc_<?php echo htmlspecialchars($s['key']); ?>">
          <div class="row">
            <span class="name"><?php echo htmlspecialchars($s['label']); ?></span>
            <span class="price"><?php echo htmlspecialchars($fmtPrice($s['price'])); ?></span>
          </div>
          <span class="dur"><?php echo (int)$s['duration']; ?> Minuten</span>
          <?php if (!empty($s['description'])): ?><span class="desc"><?php echo htmlspecialchars($s['description']); ?></span><?php endif; ?>
        </label>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Step 3: Tag & Uhrzeit -->
  <div class="card" id="cardSlots" style="display:none;">
    <h3>3. Tag & Uhrzeit wählen</h3>
    <div class="day-tabs" id="dayTabs"></div>
    <div class="slots-grid" id="slotsGrid"></div>
  </div>

  <!-- FAQ / Rückfragen-Karte -->
  <div class="card" id="cardFaq" style="display:none;">
    <h3>Haben Sie noch eine Frage?</h3>
    <div id="faqIntro" class="faq-yesno">
      <button type="button" id="faqYesBtn">Ja, ich habe eine Frage</button>
      <button type="button" id="faqNoBtn">Nein, danke</button>
    </div>
    <div id="faqList" style="display:none;margin-top:16px;">
      <?php foreach ($faqs as $f): ?>
      <div class="faq-item">
        <div class="q"><?php echo htmlspecialchars($f['question']); ?></div>
        <div class="a"><?php echo nl2br(htmlspecialchars($f['answer'])); ?></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($faqs)): ?>
      <p class="hint">Aktuell sind keine häufigen Fragen hinterlegt.</p>
      <?php endif; ?>
      <div class="faq-helpful" id="faqHelpfulBox">
        <p>War diese Antwort hilfreich?</p>
        <div class="faq-yesno">
          <button type="button" id="faqHelpfulYes">Ja</button>
          <button type="button" id="faqHelpfulNo">Nein</button>
        </div>
      </div>
      <div class="faq-feedback" id="faqFeedback">
        <p class="hint" style="margin-bottom:10px;">Schade – schreiben Sie uns kurz, was Sie wissen möchten. Der Inhaber meldet sich bei Ihnen.</p>
        <textarea id="faqNote" placeholder="Ihre Nachricht..."></textarea>
        <button type="button" class="btn btn-primary" id="faqSendBtn">Nachricht senden</button>
      </div>
      <p class="faq-thanks" id="faqThanks">Danke! Ihre Nachricht wurde an uns übermittelt.</p>
    </div>
  </div>

  <div class="summary" id="summary"></div>
  <button class="btn btn-primary" id="submitBtn" disabled>Termin bestätigen</button>
  <p class="hint" style="text-align:center;">Für Herren- und Kinderhaarschnitte ist kein Termin nötig – Sie können aber auch vorab online buchen.</p>

  <button class="cancel-toggle" id="cancelToggle">Bereits gebucht? Termin stornieren</button>
  <div class="card cancel-panel" id="cancelPanel">
    <h3>Termin stornieren</h3>
    <div class="form-row">
      <div class="form-group">
        <label>Vorname</label>
        <input type="text" id="cVorname" placeholder="Vorname">
      </div>
      <div class="form-group">
        <label>Nachname</label>
        <input type="text" id="cNachname" placeholder="Nachname">
      </div>
    </div>
    <div class="form-group">
      <label>Mobilnummer</label>
      <input type="tel" id="cTelefon" placeholder="+49 ...">
    </div>
    <button class="btn btn-outline" id="cSearchBtn" style="width:100%;background:transparent;border:1.5px solid var(--blue);color:var(--white);padding:13px;border-radius:50px;cursor:pointer;">Meine Termine suchen</button>
    <div class="cancel-result" id="cancelResult"></div>
  </div>

  <button class="cancel-toggle" id="replyToggle">Antwort auf Ihre Nachricht prüfen</button>
  <div class="card cancel-panel" id="replyPanel">
    <h3>Antwort prüfen</h3>
    <div class="form-row">
      <div class="form-group">
        <label>Vorname</label>
        <input type="text" id="rVorname" placeholder="Vorname">
      </div>
      <div class="form-group">
        <label>Nachname</label>
        <input type="text" id="rNachname" placeholder="Nachname">
      </div>
    </div>
    <div class="form-group">
      <label>Mobilnummer</label>
      <input type="tel" id="rTelefon" placeholder="+49 ...">
    </div>
    <button class="btn btn-outline" id="rSearchBtn" style="width:100%;background:transparent;border:1.5px solid var(--blue);color:var(--white);padding:13px;border-radius:50px;cursor:pointer;">Antwort suchen</button>
    <div class="cancel-result" id="replyResult"></div>
  </div>
</div>

<script>
const DAYS = <?php echo json_encode($days, JSON_UNESCAPED_UNICODE); ?>;
const TODAY = "<?php echo date('Y-m-d'); ?>";
const SERVICES = <?php echo json_encode($servicesKeyed, JSON_UNESCAPED_UNICODE); ?>;

const state = { type: null, service: null, date: null, time: null, faqHandled: false };

const els = {
  vorname: document.getElementById('vorname'),
  nachname: document.getElementById('nachname'),
  telefon: document.getElementById('telefon'),
  cardService: document.getElementById('cardService'),
  cardSlots: document.getElementById('cardSlots'),
  cardFaq: document.getElementById('cardFaq'),
  dayTabs: document.getElementById('dayTabs'),
  slotsGrid: document.getElementById('slotsGrid'),
  summary: document.getElementById('summary'),
  submitBtn: document.getElementById('submitBtn'),
  msgBox: document.getElementById('msgBox'),
};

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
}

function showMsg(text, type) {
  els.msgBox.textContent = text;
  els.msgBox.className = 'msg show ' + type;
}
function clearMsg() {
  els.msgBox.className = 'msg';
}

function serviceDuration() {
  const key = state.type === 'kind' ? 'kind' : state.service;
  return (key && SERVICES[key]) ? SERVICES[key].duration : null;
}

function isReadyForSlots() {
  if (state.type === 'kind') return true;
  if (state.type === 'mann' && state.service) return true;
  return false;
}

document.querySelectorAll('input[name="type"]').forEach(r => {
  r.addEventListener('change', () => {
    state.type = r.value;
    state.service = null;
    document.querySelectorAll('input[name="service"]').forEach(s => s.checked = false);
    els.cardService.style.display = state.type === 'mann' ? 'block' : 'none';
    refreshReadiness();
  });
});

document.querySelectorAll('input[name="service"]').forEach(r => {
  r.addEventListener('change', () => {
    state.service = r.value;
    refreshReadiness();
  });
});

function refreshReadiness() {
  state.date = null; state.time = null;
  if (isReadyForSlots()) {
    els.cardSlots.style.display = 'block';
    if (!document.querySelector('.day-tab.active')) {
      buildDayTabs();
      selectDay(TODAY);
    } else {
      loadSlots(state.date || TODAY);
    }
  } else {
    els.cardSlots.style.display = 'none';
  }
  els.cardFaq.style.display = 'none';
  resetFaqUI();
  updateSummaryAndButton();
}

function buildDayTabs() {
  els.dayTabs.innerHTML = '';
  DAYS.forEach(d => {
    const tab = document.createElement('div');
    tab.className = 'day-tab' + (d.date === TODAY ? ' today' : '');
    tab.dataset.date = d.date;
    tab.innerHTML = `<div class="dow">${d.label.slice(0,2)}</div><div class="dm">${d.short}</div>`;
    tab.addEventListener('click', () => selectDay(d.date));
    els.dayTabs.appendChild(tab);
  });
}

function selectDay(date) {
  state.date = date;
  state.time = null;
  document.querySelectorAll('.day-tab').forEach(t => t.classList.toggle('active', t.dataset.date === date));
  loadSlots(date);
  updateSummaryAndButton();
}

// quiet = Hintergrund-Aktualisierung ohne "Lade Termine…"-Anzeige
function loadSlots(date, quiet) {
  const duration = serviceDuration();
  if (!duration) return;
  if (!quiet) els.slotsGrid.innerHTML = '<div class="slots-empty">Lade Termine…</div>';
  const params = new URLSearchParams({ action: 'slots', date, duration, type: state.type || '', service: state.service || '' });
  fetch(`booking-api.php?${params.toString()}`)
    .then(r => r.json())
    .then(data => {
      // Antwort für einen inzwischen gewechselten Tag ignorieren
      if (state.date && state.date !== date) return;
      // Gewählte Uhrzeit ist inzwischen belegt (z.B. Termin im Dashboard verlängert)?
      if (state.time && !(data.slots || []).some(s => s.available && s.time === state.time)) {
        state.time = null;
        state.end = null;
        updateSummaryAndButton();
        if (quiet) showMsg('Die gewählte Uhrzeit ist leider nicht mehr frei. Bitte wählen Sie eine andere Uhrzeit.', 'error');
      }
      renderSlots(data);
    })
    .catch(() => { if (!quiet) els.slotsGrid.innerHTML = '<div class="slots-empty">Fehler beim Laden. Bitte Seite neu laden.</div>'; });
}

// Freie Zeiten alle 20 Sekunden neu laden, damit Änderungen im Dashboard
// (neue Termine, verlängerte/verkürzte Dauer, Absagen) sofort sichtbar werden.
let bookingDone = false;
setInterval(() => {
  if (bookingDone || document.visibilityState !== 'visible') return;
  if (!state.date || !isReadyForSlots() || els.submitBtn.textContent === 'Wird gebucht…') return;
  loadSlots(state.date, true);
}, 20000);

function renderSlots(data) {
  els.slotsGrid.innerHTML = '';
  if (data.closed) {
    els.slotsGrid.innerHTML = '<div class="slots-empty">An diesem Tag ist der Salon geschlossen.</div>';
    return;
  }
  // Der Server liefert nur tatsächlich freie Startzeiten (minutengenau)
  const free = (data.slots || []).filter(s => s.available);
  if (free.length === 0) {
    els.slotsGrid.innerHTML = '<div class="slots-empty">Keine freien Termine an diesem Tag verfügbar.</div>';
    return;
  }
  free.forEach(s => {
    const btn = document.createElement('button');
    btn.className = 'slot-btn';
    btn.textContent = s.time;
    btn.title = `${s.time} – ${s.end} Uhr`;
    if (state.time === s.time) btn.classList.add('selected');
    btn.addEventListener('click', () => {
      state.time = s.time;
      state.end = s.end;
      document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');
      updateSummaryAndButton();
    });
    els.slotsGrid.appendChild(btn);
  });
}

function updateSummaryAndButton() {
  const vorname = els.vorname.value.trim();
  const nachname = els.nachname.value.trim();
  const telefon = els.telefon.value.trim();
  const ready = vorname && nachname && telefon && state.type && isReadyForSlots() && state.date && state.time;
  els.submitBtn.disabled = !ready;

  if (state.date && state.time) {
    const d = DAYS.find(x => x.date === state.date);
    const key = state.type === 'kind' ? 'kind' : state.service;
    const svcLabel = (key && SERVICES[key]) ? SERVICES[key].label : '';
    els.summary.innerHTML = `<strong>${svcLabel}</strong> am <strong>${d ? d.label + ', ' + d.short : state.date}</strong> um <strong>${state.time}</strong>${state.end ? '–<strong>' + state.end + '</strong>' : ''} Uhr`;
    els.cardFaq.style.display = 'block';
  } else {
    els.summary.innerHTML = '';
    els.cardFaq.style.display = 'none';
  }
}

[els.vorname, els.nachname, els.telefon].forEach(inp => inp.addEventListener('input', updateSummaryAndButton));

els.submitBtn.addEventListener('click', () => {
  clearMsg();
  els.submitBtn.disabled = true;
  els.submitBtn.textContent = 'Wird gebucht…';

  const body = new URLSearchParams({
    action: 'book',
    vorname: els.vorname.value.trim(),
    nachname: els.nachname.value.trim(),
    telefon: els.telefon.value.trim(),
    loyalty_password: document.getElementById('loyaltyPassword').value,
    type: state.type,
    service: state.service || '',
    date: state.date,
    time: state.time,
  });

  fetch('booking-api.php', { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        let successText = 'Vielen Dank! Ihre Terminanfrage ist eingegangen. Sie erhalten eine SMS, sobald wir Ihren Termin bestätigt haben.';
        const loyalty = data.loyalty || {};
        if (loyalty.status === 'created') {
          successText += '\n\nIhre Treuekarte wurde automatisch erstellt (wolf-friseur.de/card.html). Anmeldung mit Ihrer Mobilnummer und '
            + (loyalty.password_default ? 'den letzten 4 Ziffern Ihrer Mobilnummer als Passwort.' : 'Ihrem gewählten Passwort.')
            + '\nNach 10 Besuchen erhalten Sie 7 € Rabatt auf Ihren Haarschnitt.';
        } else if (loyalty.status === 'exists') {
          successText += '\n\nIhr Besuch wird nach dem Termin automatisch auf Ihrer Treuekarte gutgeschrieben.';
        }
        bookingDone = true;
        showMsg(successText, 'success');
        document.querySelectorAll('.card').forEach(c => c.classList.add('disabled'));
        showLoyaltyCard(loyalty);
        els.submitBtn.style.display = 'none';
        if (getCookie('wf_cookie_consent') === 'accepted') {
          setCookie('wf_customer', JSON.stringify({
            vorname: els.vorname.value.trim(),
            nachname: els.nachname.value.trim(),
            telefon: els.telefon.value.trim(),
          }), 365);
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } else {
        showMsg(data.message || 'Es gab ein Problem bei der Buchung.', 'error');
        if (data.slot_taken) loadSlots(state.date);
        els.submitBtn.disabled = false;
        els.submitBtn.textContent = 'Termin bestätigen';
      }
    })
    .catch(() => {
      showMsg('Verbindungsfehler. Bitte versuchen Sie es erneut oder rufen Sie uns an.', 'error');
      els.submitBtn.disabled = false;
      els.submitBtn.textContent = 'Termin bestätigen';
    });
});

// Aktueller Stempel-Stand der Treuekarte (die Buchung selbst vergibt keinen Stempel)
function showLoyaltyCard(loyalty) {
  if (!loyalty || typeof loyalty.visits !== 'number') return;
  const total = loyalty.total || 10;
  const visits = Math.max(0, Math.min(total, loyalty.visits));
  const stamps = document.getElementById('loyaltyStamps');
  stamps.innerHTML = '';
  for (let i = 1; i <= total; i++) {
    const s = document.createElement('div');
    s.className = 'stamp' + (i <= visits ? ' on' : '');
    s.textContent = i <= visits ? '✓' : i;
    stamps.appendChild(s);
  }
  document.getElementById('loyaltyCount').textContent = `${visits} / ${total} Stempel`;
  document.getElementById('loyaltyHint').textContent = loyalty.free_ready
    ? `Glückwunsch! Bei Ihrem nächsten Besuch erhalten Sie ${loyalty.reward_eur || 7} € Rabatt auf Ihren Haarschnitt.`
    : `Noch ${total - visits} Besuch${total - visits === 1 ? '' : 'e'} bis zu ${loyalty.reward_eur || 7} € Rabatt. Der Stempel wird nach Ihrem Besuch im Salon vergeben.`;
  const card = document.getElementById('loyaltyCard');
  card.classList.remove('disabled');
  card.classList.add('show');
}

// ---- Termin stornieren ----
const cancelToggle = document.getElementById('cancelToggle');
const cancelPanel = document.getElementById('cancelPanel');
const cancelResult = document.getElementById('cancelResult');

cancelToggle.addEventListener('click', () => {
  cancelPanel.classList.toggle('show');
  cancelToggle.textContent = cancelPanel.classList.contains('show') ? 'Formular ausblenden' : 'Bereits gebucht? Termin stornieren';
});

document.getElementById('cSearchBtn').addEventListener('click', () => {
  const vorname = document.getElementById('cVorname').value.trim();
  const nachname = document.getElementById('cNachname').value.trim();
  const telefon = document.getElementById('cTelefon').value.trim();
  if (!vorname || !nachname || !telefon) {
    cancelResult.innerHTML = '<p class="too-late-note">Bitte alle Felder ausfüllen.</p>';
    return;
  }
  cancelResult.innerHTML = '<p class="too-late-note">Suche…</p>';
  const params = new URLSearchParams({ action: 'find_bookings', vorname, nachname, telefon });
  fetch(`booking-api.php?${params.toString()}`)
    .then(r => r.json())
    .then(data => renderCancelResults(data, vorname, nachname, telefon))
    .catch(() => { cancelResult.innerHTML = '<p class="too-late-note">Fehler bei der Suche.</p>'; });
});

function renderCancelResults(data, vorname, nachname, telefon) {
  if (!data.success) {
    cancelResult.innerHTML = `<p class="too-late-note">${data.message}</p>`;
    return;
  }
  if (!data.appointments || data.appointments.length === 0) {
    cancelResult.innerHTML = '<p class="too-late-note">Keine bevorstehenden Termine unter diesen Angaben gefunden.</p>';
    return;
  }
  cancelResult.innerHTML = '';
  data.appointments.forEach(a => {
    const item = document.createElement('div');
    item.className = 'cancel-item';
    const dateObj = new Date(a.date + 'T' + a.time);
    const dateLabel = dateObj.toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' });
    item.innerHTML = `
      <div class="info">
        <div class="d">${dateLabel}, ${a.time} Uhr</div>
        <div class="s">${a.service_label}</div>
      </div>
    `;
    const btn = document.createElement('button');
    btn.className = 'btn-cancel-item';
    if (a.can_cancel) {
      btn.textContent = 'Stornieren';
      btn.addEventListener('click', () => cancelBooking(a.id, vorname, nachname, telefon, item, data.min_hours, data.salon_phone));
    } else {
      btn.textContent = 'Stornieren';
      btn.disabled = true;
      const note = document.createElement('div');
      note.className = 'too-late-note';
      note.style.width = '100%';
      note.textContent = `Weniger als ${data.min_hours} Std. bis zum Termin – bitte rufen Sie uns an: ${data.salon_phone}.`;
      item.appendChild(note);
    }
    item.appendChild(btn);
    cancelResult.appendChild(item);
  });
}

function cancelBooking(id, vorname, nachname, telefon, itemEl, minHours, salonPhone) {
  const body = new URLSearchParams({ action: 'cancel_booking', id, vorname, nachname, telefon });
  fetch('booking-api.php', { method: 'POST', body })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        itemEl.innerHTML = '<div class="info"><div class="s">Termin storniert.</div></div>';
      } else {
        const note = document.createElement('div');
        note.className = 'too-late-note';
        note.style.width = '100%';
        note.textContent = d.message;
        itemEl.appendChild(note);
      }
    });
}

// ---- FAQ-Karte ----
const faqIntro = document.getElementById('faqIntro');
const faqList = document.getElementById('faqList');
const faqHelpfulBox = document.getElementById('faqHelpfulBox');
const faqFeedback = document.getElementById('faqFeedback');
const faqThanks = document.getElementById('faqThanks');
const faqNote = document.getElementById('faqNote');

function resetFaqUI() {
  state.faqHandled = false;
  faqIntro.style.display = 'flex';
  faqList.style.display = 'none';
  faqHelpfulBox.style.display = 'block';
  faqFeedback.classList.remove('show');
  faqThanks.classList.remove('show');
  faqNote.value = '';
}

document.getElementById('faqYesBtn').addEventListener('click', () => {
  faqIntro.style.display = 'none';
  faqList.style.display = 'block';
});
document.getElementById('faqNoBtn').addEventListener('click', () => {
  faqIntro.style.display = 'none';
  faqList.style.display = 'none';
});

document.getElementById('faqHelpfulYes').addEventListener('click', () => {
  faqHelpfulBox.style.display = 'none';
  faqFeedback.classList.remove('show');
  faqThanks.textContent = 'Schön, das freut uns!';
  faqThanks.classList.add('show');
});
document.getElementById('faqHelpfulNo').addEventListener('click', () => {
  faqHelpfulBox.style.display = 'none';
  faqFeedback.classList.add('show');
});

document.getElementById('faqSendBtn').addEventListener('click', () => {
  const vorname = els.vorname.value.trim();
  const nachname = els.nachname.value.trim();
  const telefon = els.telefon.value.trim();
  const note = faqNote.value.trim();
  if (!vorname || !nachname || !telefon) {
    alert('Bitte oben zuerst Vorname, Nachname und Mobilnummer eintragen.');
    return;
  }
  if (!note) {
    alert('Bitte Ihre Frage kurz eintragen.');
    return;
  }
  const key = state.type === 'kind' ? 'kind' : state.service;
  const svcLabel = (key && SERVICES[key]) ? SERVICES[key].label : '';
  const context = `${svcLabel} am ${state.date} um ${state.time} Uhr`;
  const btn = document.getElementById('faqSendBtn');
  btn.disabled = true;
  btn.textContent = 'Wird gesendet…';
  const body = new URLSearchParams({ action: 'send_feedback', vorname, nachname, telefon, note, context });
  fetch('booking-api.php', { method: 'POST', body })
    .then(r => r.json())
    .then(d => {
      btn.disabled = false;
      btn.textContent = 'Nachricht senden';
      if (d.success) {
        faqFeedback.classList.remove('show');
        faqThanks.textContent = 'Danke! Ihre Nachricht wurde an uns übermittelt.';
        faqThanks.classList.add('show');
      } else {
        alert(d.message || 'Es gab ein Problem beim Senden.');
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.textContent = 'Nachricht senden';
      alert('Verbindungsfehler. Bitte versuchen Sie es erneut.');
    });
});

// ---- Antwort auf frühere Nachricht prüfen ----
const replyToggle = document.getElementById('replyToggle');
const replyPanel = document.getElementById('replyPanel');
const replyResult = document.getElementById('replyResult');

replyToggle.addEventListener('click', () => {
  replyPanel.classList.toggle('show');
  replyToggle.textContent = replyPanel.classList.contains('show') ? 'Formular ausblenden' : 'Antwort auf Ihre Nachricht prüfen';
});

document.getElementById('rSearchBtn').addEventListener('click', () => {
  const vorname = document.getElementById('rVorname').value.trim();
  const nachname = document.getElementById('rNachname').value.trim();
  const telefon = document.getElementById('rTelefon').value.trim();
  if (!vorname || !nachname || !telefon) {
    replyResult.innerHTML = '<p class="too-late-note">Bitte alle Felder ausfüllen.</p>';
    return;
  }
  replyResult.innerHTML = '<p class="too-late-note">Suche…</p>';
  const params = new URLSearchParams({ action: 'find_reply', vorname, nachname, telefon });
  fetch(`booking-api.php?${params.toString()}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        replyResult.innerHTML = `<p class="too-late-note">${data.message}</p>`;
        return;
      }
      if (!data.replies || data.replies.length === 0) {
        replyResult.innerHTML = '<p class="too-late-note">Noch keine Antwort vorhanden. Bitte versuchen Sie es später erneut.</p>';
        return;
      }
      replyResult.innerHTML = '';
      data.replies.forEach(r => {
        const item = document.createElement('div');
        item.className = 'reply-item';
        item.innerHTML = `<div class="q">Ihre Frage: ${escapeHtml(r.note)}</div><div class="a"><b>Antwort:</b> ${escapeHtml(r.admin_reply)}</div>`;
        replyResult.appendChild(item);
      });
    })
    .catch(() => { replyResult.innerHTML = '<p class="too-late-note">Fehler bei der Suche.</p>'; });
});

// ---- Cookies: Zustimmung + zuletzt gebuchten Termin merken ----
function getCookie(name) {
  const match = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
  return match ? decodeURIComponent(match.pop()) : null;
}
function setCookie(name, value, days) {
  const expires = new Date(Date.now() + days * 864e5).toUTCString();
  document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`;
}
function deleteCookie(name) {
  document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`;
}

const cookieBanner = document.getElementById('cookieConsent');
const myAppointmentsCard = document.getElementById('myAppointmentsCard');
const myAppointmentsList = document.getElementById('myAppointmentsList');

function loadMyAppointments() {
  const raw = getCookie('wf_customer');
  if (!raw) return;
  let identity;
  try { identity = JSON.parse(raw); } catch (e) { return; }
  if (!identity || !identity.vorname || !identity.nachname || !identity.telefon) return;
  const params = new URLSearchParams({ action: 'find_bookings', vorname: identity.vorname, nachname: identity.nachname, telefon: identity.telefon });
  fetch(`booking-api.php?${params.toString()}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success || !data.appointments || data.appointments.length === 0) return;
      renderMyAppointments(data, identity.vorname, identity.nachname, identity.telefon);
      myAppointmentsCard.style.display = 'block';
    })
    .catch(() => {});
}

function renderMyAppointments(data, vorname, nachname, telefon) {
  myAppointmentsList.innerHTML = '';
  data.appointments.forEach(a => {
    const item = document.createElement('div');
    item.className = 'cancel-item';
    const dateObj = new Date(a.date + 'T' + a.time);
    const dateLabel = dateObj.toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' });
    item.innerHTML = `
      <div class="info">
        <div class="d">${dateLabel}, ${a.time} Uhr</div>
        <div class="s">${escapeHtml(a.service_label)}</div>
      </div>
    `;
    const btn = document.createElement('button');
    btn.className = 'btn-cancel-item';
    btn.textContent = 'Stornieren';
    if (a.can_cancel) {
      btn.addEventListener('click', () => cancelBooking(a.id, vorname, nachname, telefon, item, data.min_hours, data.salon_phone));
    } else {
      btn.disabled = true;
      const note = document.createElement('div');
      note.className = 'too-late-note';
      note.style.width = '100%';
      note.textContent = `Weniger als ${data.min_hours} Std. bis zum Termin – bitte rufen Sie uns an: ${data.salon_phone}.`;
      item.appendChild(note);
    }
    item.appendChild(btn);
    myAppointmentsList.appendChild(item);
  });
}

const existingConsent = getCookie('wf_cookie_consent');
if (!existingConsent) {
  cookieBanner.classList.add('show');
} else if (existingConsent === 'accepted') {
  loadMyAppointments();
}

document.getElementById('cookieAcceptBtn').addEventListener('click', () => {
  setCookie('wf_cookie_consent', 'accepted', 365);
  cookieBanner.classList.remove('show');
  loadMyAppointments();
});
document.getElementById('cookieDeclineBtn').addEventListener('click', () => {
  setCookie('wf_cookie_consent', 'declined', 365);
  deleteCookie('wf_customer');
  cookieBanner.classList.remove('show');
});
</script>

<!-- Cookie-Zustimmung -->
<div class="cookie-banner" id="cookieConsent">
  <p>Diese Website verwendet ein Cookie, um Ihren zuletzt gebuchten Termin auf diesem Gerät zu speichern und ihn Ihnen beim nächsten Besuch direkt anzuzeigen (inkl. Stornieren-Option). Sind Sie damit einverstanden?</p>
  <div class="cookie-actions">
    <button class="btn" id="cookieDeclineBtn" style="background:transparent;border:1.5px solid rgba(255,255,255,0.25);color:var(--white);">Ablehnen</button>
    <button class="btn btn-primary" id="cookieAcceptBtn">Zustimmen</button>
  </div>
</div>
</body>
</html>
