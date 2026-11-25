<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | Wolf Friseur</title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="icon" type="image/png" href="https://i.ibb.co/pvm4r092/Element-255.png">
<style>
@import url('https://fonts.cdnfonts.com/css/itc-serif-gothic');
:root{
  --black:#282828; --white:#ffffff; --blue:#2f519f; --blue-light:#3d6bc7; --blue-glow:#5b85e0;
  --red:#c41e3a; --green:#3fae6a; --gray:#323232; --gray-light:#3a3a3a;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Montserrat',sans-serif;background:var(--black);color:var(--white);min-height:100vh;}
h1,h2,h3,.logo-text{font-family:'ITC Serif Gothic','Playfair Display',serif !important;font-weight:900 !important;}

/* ---- Login ---- */
#loginScreen{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.login-box{width:100%;max-width:360px;background:rgba(255,255,255,0.03);border:1px solid rgba(47,81,159,0.3);
  border-radius:18px;padding:36px 28px;text-align:center;}
.login-box .logo-icon{width:60px;margin:0 auto 14px;}
.login-box .logo-icon img{width:100%;}
.login-box h2{font-size:1.4rem;margin-bottom:22px;}
.login-box input{width:100%;padding:13px 16px;margin-bottom:14px;background:rgba(255,255,255,0.05);
  border:1px solid rgba(47,81,159,0.3);border-radius:10px;color:var(--white);font-size:0.95rem;outline:none;}
.login-box input:focus{border-color:var(--blue-glow);}
.err{color:var(--red);font-size:0.85rem;margin-bottom:10px;min-height:18px;}

/* ---- App shell ---- */
#app{display:none;}
.navbar{position:sticky;top:0;width:100%;padding:14px 24px;display:flex;justify-content:space-between;align-items:center;
  background:rgba(40,40,40,0.96);backdrop-filter:blur(20px);border-bottom:1px solid rgba(47,81,159,0.3);z-index:100;}
.logo{display:flex;align-items:center;gap:10px;}
.logo-icon{width:36px;height:36px;}
.logo-icon img{width:100%;object-fit:contain;}
.logo-text{font-size:1.05rem;letter-spacing:2px;}
.logo-text span{color:var(--blue);}
.nav-right{display:flex;align-items:center;gap:16px;}
.logout-btn{background:transparent;border:1px solid rgba(196,30,58,0.5);color:var(--red);padding:8px 16px;
  border-radius:20px;font-size:0.8rem;cursor:pointer;letter-spacing:1px;}
.logout-btn:hover{background:rgba(196,30,58,0.15);}

/* ---- Alarm-Banner für neue Buchungen ---- */
#alertBanner{display:none;position:sticky;top:0;z-index:150;padding:16px 20px;text-align:center;
  background:var(--red);color:#fff;animation:alertBlink 1s infinite;}
#alertBanner.show{display:block;}
@keyframes alertBlink{0%,100%{background:var(--red);}50%{background:#7a0f1e;}}
#alertBanner .txt{font-weight:700;font-size:1rem;letter-spacing:0.5px;margin-bottom:10px;}
#alertBanner .txt i{margin-inline-end:8px;}
#alertAckBtn{background:#fff;color:var(--red);border:none;padding:11px 26px;border-radius:24px;font-weight:700;
  font-size:0.85rem;letter-spacing:1px;cursor:pointer;text-transform:uppercase;}
#alertAckBtn:hover{background:rgba(255,255,255,0.85);}

/* ---- Tabs ---- */
.tabbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;border-bottom:1px solid rgba(47,81,159,0.25);padding-bottom:0;}
.tab-btn{position:relative;padding:12px 18px;background:none;border:none;color:rgba(255,255,255,0.55);font-family:'Montserrat',sans-serif;
  font-size:0.88rem;font-weight:600;cursor:pointer;border-bottom:2px solid transparent;letter-spacing:0.5px;}
.tab-btn:hover{color:var(--white);}
.tab-btn.active{color:var(--blue-glow);border-bottom-color:var(--blue-glow);}
.tab-badge{background:var(--red);color:#fff;font-size:0.68rem;font-weight:700;padding:2px 7px;border-radius:20px;margin-inline-start:6px;display:none;}
.tab-panel{display:none;}
.tab-panel.active{display:block;}

.wrap{max-width:1100px;margin:0 auto;padding:30px 20px 80px;}
.toolbar{display:flex;flex-wrap:wrap;gap:14px;align-items:center;justify-content:space-between;margin-bottom:24px;}
.toolbar h1{font-size:1.5rem;}
.filters{display:flex;gap:10px;flex-wrap:wrap;}
.filters select,.filters input{padding:9px 14px;background:rgba(255,255,255,0.05);border:1px solid rgba(47,81,159,0.3);
  border-radius:10px;color:var(--white);font-size:0.85rem;}
.btn{padding:10px 20px;border-radius:20px;border:none;font-size:0.82rem;font-weight:600;letter-spacing:0.5px;cursor:pointer;transition:all .2s;}
.btn-primary{background:linear-gradient(135deg,var(--blue),var(--blue-light));color:#fff;}
.btn-primary:hover{transform:translateY(-1px);}
.btn-outline{background:transparent;border:1px solid rgba(47,81,159,0.5);color:var(--white);}
.btn-outline:hover{background:rgba(47,81,159,0.15);}
.btn-danger{background:rgba(196,30,58,0.15);border:1px solid var(--red);color:var(--red);}
.btn-danger:hover{background:rgba(196,30,58,0.28);}
.btn-small{padding:6px 12px;font-size:0.75rem;border-radius:14px;}

.day-group{margin-bottom:26px;}
.day-group h3{font-size:1rem;color:var(--blue-glow);letter-spacing:1px;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid rgba(47,81,159,0.25);}
.appt-card{background:rgba(255,255,255,0.03);border:1px solid rgba(47,81,159,0.2);border-radius:12px;
  padding:14px 16px;margin-bottom:10px;display:flex;flex-wrap:wrap;align-items:center;gap:12px;}
.appt-card.cancelled{opacity:0.5;}
.appt-card.blocked{border-style:dashed;border-color:rgba(196,30,58,0.4);}
.appt-time{font-weight:700;font-size:1rem;min-width:56px;}
.appt-info{flex:1;min-width:180px;}
.appt-info .name{font-weight:600;font-size:0.92rem;}
.appt-info .meta{font-size:0.78rem;color:rgba(255,255,255,0.55);margin-top:2px;}
.status-tag{font-size:0.7rem;padding:3px 10px;border-radius:12px;text-transform:uppercase;letter-spacing:0.5px;}
.status-tag.confirmed{background:rgba(63,174,106,0.18);color:var(--green);}
.status-tag.cancelled{background:rgba(196,30,58,0.18);color:var(--red);}
.status-tag.blocked{background:rgba(255,255,255,0.1);color:rgba(255,255,255,0.6);}
.appt-actions{display:flex;gap:6px;flex-wrap:wrap;}

.empty-state{text-align:center;color:rgba(255,255,255,0.4);padding:50px 0;}

/* ---- Terminbuch (heutige Termine, wie ein Terminkalender-Heft) ---- */
.today-head{font-size:0.95rem;color:var(--blue-glow);letter-spacing:1px;margin-top:4px;font-weight:600;}
.book-wrap{overflow-x:auto;border:1px solid rgba(47,81,159,0.35);border-radius:12px;background:rgba(255,255,255,0.02);}
.book{width:100%;min-width:760px;border-collapse:collapse;}
.book th{text-align:left;font-size:0.72rem;text-transform:uppercase;letter-spacing:1.2px;color:rgba(255,255,255,0.6);
  padding:14px 14px;border-bottom:2px solid rgba(47,81,159,0.55);background:rgba(47,81,159,0.12);white-space:nowrap;}
.book td{padding:14px;border-bottom:1px solid rgba(255,255,255,0.08);vertical-align:middle;font-size:0.9rem;}
.book th+th,.book td+td{border-left:1px solid rgba(255,255,255,0.08);}
.book tr:last-child td{border-bottom:none;}
.book tr:hover td{background:rgba(47,81,159,0.06);}
.book .c-time{font-weight:700;font-size:1.05rem;white-space:nowrap;width:90px;}
.book .c-time small{display:block;font-weight:400;font-size:0.72rem;color:rgba(255,255,255,0.45);margin-top:2px;}
.book .c-name .n{font-weight:600;}
.book .c-name .p{font-size:0.76rem;color:rgba(255,255,255,0.5);margin-top:2px;}
.book .c-svc small{display:block;font-size:0.72rem;color:rgba(255,255,255,0.45);margin-top:2px;}
.book .c-dur{white-space:nowrap;width:90px;}
.book .c-act{width:1%;white-space:nowrap;}
.book .c-act .appt-actions{flex-wrap:nowrap;}
.book tr.is-cancelled td{opacity:0.45;}
.book tr.is-cancelled .c-name .n{text-decoration:line-through;}
.book tr.is-blocked td{background:repeating-linear-gradient(135deg,rgba(196,30,58,0.05) 0 10px,transparent 10px 20px);}
.book tr.is-done td:first-child{box-shadow:inset 4px 0 0 var(--green);}
.btn-confirm{background:rgba(63,174,106,0.15);border:1px solid var(--green);color:var(--green);}
.btn-confirm:hover:not(:disabled){background:rgba(63,174,106,0.3);}
.btn-confirm.done{background:var(--green);color:#fff;cursor:default;}
.btn-confirm:disabled{opacity:0.75;cursor:default;transform:none;}
.loy-badge{display:inline-block;margin-top:5px;font-size:0.7rem;padding:2px 9px;border-radius:12px;background:rgba(63,174,106,0.15);color:var(--green);font-weight:600;}
.loy-badge.reward{background:rgba(196,30,58,0.18);color:#ff6b81;}
.loy-badge.info{background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.6);}
/* ---- Tagesplan: Stunden untereinander, jede Stunde in 4 Viertelstunden ---- */
.day-select{padding:9px 14px;background:rgba(255,255,255,0.05);border:1px solid rgba(47,81,159,0.45);border-radius:10px;
  color:var(--blue-glow);font-family:'Montserrat',sans-serif;font-size:0.95rem;font-weight:600;letter-spacing:0.5px;margin-top:6px;cursor:pointer;}
.day-select option{background:var(--gray);color:var(--white);}
.plan-wrap{overflow-x:auto;border:1px solid rgba(47,81,159,0.35);border-radius:12px;background:rgba(255,255,255,0.02);}
.plan-inner{min-width:920px;}
.plan-cols{display:grid;grid-template-columns:105px 1.6fr 1fr 75px 350px;align-items:center;gap:0 12px;}
.plan-head{display:grid;grid-template-columns:90px 1fr;background:rgba(47,81,159,0.12);border-bottom:2px solid rgba(47,81,159,0.55);}
.plan-head > div{padding:13px 12px;font-size:0.72rem;text-transform:uppercase;letter-spacing:1.2px;color:rgba(255,255,255,0.6);font-weight:600;}
.plan-head .plan-cols{padding:0 12px;}
.plan-head .plan-cols > div{padding:13px 0;}
.plan{display:grid;grid-template-columns:90px 1fr;grid-auto-rows:34px;position:relative;}
.plan .hour{grid-column:1;border-top:1px solid rgba(255,255,255,0.18);border-right:1px solid rgba(255,255,255,0.1);
  padding:8px 12px;font-weight:700;font-size:1.05rem;}
.plan .q{grid-column:2;background:rgba(255,255,255,0.045);border-top:1px dashed rgba(255,255,255,0.07);
  font-size:0.68rem;color:rgba(255,255,255,0.28);padding:0 12px;display:flex;align-items:center;}
.plan .q.h{border-top:1px solid rgba(255,255,255,0.18);}
.plan .q:hover{background:rgba(255,255,255,0.07);}
.plan-end{display:grid;grid-template-columns:90px 1fr;border-top:1px solid rgba(255,255,255,0.18);}
.plan-end > div{padding:9px 12px;font-size:0.8rem;color:rgba(255,255,255,0.45);}
.plan-end > div:first-child{font-weight:700;color:rgba(255,255,255,0.7);font-size:1.05rem;}
.appt-block{grid-column:2;z-index:2;margin:2px 4px;border-radius:8px;padding:0 8px;
  background:#dce6f9;color:#1b2d5a;border-left:5px solid var(--blue);box-shadow:0 2px 10px rgba(0,0,0,0.25);overflow:hidden;
  display:flex;align-items:center;}
.appt-block .plan-cols{width:100%;}
.appt-block.done{background:#d8f1e2;border-left-color:var(--green);color:#16432a;}
.appt-block.pending{background:#fff1d6;border-left-color:#e0a020;color:#5a3d06;}
.appt-block .pending-tag{display:inline-block;margin-left:6px;font-size:0.66rem;padding:1px 7px;border-radius:12px;background:rgba(224,160,32,0.25);color:#8a5a00;font-weight:700;}
.appt-block.blocked{background:repeating-linear-gradient(135deg,#f4dde2 0 10px,#ecd0d6 10px 20px);border-left-color:var(--red);color:#5a1422;}
.appt-block.cross{background-image:linear-gradient(to bottom,transparent calc(var(--cut) - 1px),rgba(27,45,90,0.45) calc(var(--cut) - 1px),rgba(27,45,90,0.45) calc(var(--cut) + 1px),transparent calc(var(--cut) + 1px));background-size:14px 100%;background-repeat:no-repeat;background-position:left top;}
.appt-block .t{font-weight:700;font-size:0.88rem;white-space:nowrap;}
.appt-block .n{font-weight:700;font-size:0.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.appt-block .sub{font-size:0.72rem;opacity:0.75;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.appt-block .svc{font-weight:600;font-size:0.85rem;}
.appt-block .dur{font-weight:600;font-size:0.85rem;}
.appt-block .appt-actions{flex-wrap:nowrap;justify-content:flex-end;}
.appt-block .btn-outline{color:#1b2d5a;border-color:rgba(47,81,159,0.55);background:rgba(255,255,255,0.55);}
.appt-block .btn-outline:hover{background:#fff;}
.appt-block .btn-danger{background:rgba(255,255,255,0.55);color:#b0142f;border-color:#b0142f;}
.appt-block .btn-confirm{background:rgba(255,255,255,0.55);color:#1d7a45;border-color:#1d7a45;}
.appt-block .btn-confirm.done{background:#2f9a5b;color:#fff;border-color:#2f9a5b;}
.appt-block .loy-badge{margin:0 0 0 6px;background:rgba(29,122,69,0.15);color:#1d7a45;font-size:0.66rem;padding:1px 7px;}
.appt-block .loy-badge.reward{background:rgba(176,20,47,0.12);color:#b0142f;}
.appt-block .loy-badge.info{background:rgba(27,45,90,0.1);color:#1b2d5a;}
.appt-block.noshow{background:#ece6e6;border-left-color:#8a7a7a;color:#4a3d3d;}
.appt-block .noshow-tag{display:inline-block;margin-left:6px;font-size:0.66rem;padding:1px 7px;border-radius:12px;background:rgba(176,20,47,0.12);color:#b0142f;font-weight:700;}
/* freie Lücke zwischen Terminen (minutengenau) – Klick = manueller Termin */
.gap-block{grid-column:2;z-index:1;margin:1px 4px;border-radius:8px;border:1px dashed rgba(63,174,106,0.55);
  background:rgba(63,174,106,0.07);color:#7fd6a0;font-size:0.72rem;padding:0 10px;display:flex;align-items:flex-start;
  cursor:pointer;overflow:hidden;transition:background .2s;}
.gap-block:hover{background:rgba(63,174,106,0.18);}
.gap-block span{padding-top:2px;white-space:nowrap;}
.fit-hint{font-size:0.8rem;margin:4px 0 6px;min-height:18px;}
.fit-hint.ok{color:var(--green);}
.fit-hint.bad{color:var(--red);}
/* ---- Archiv ---- */
.archive-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px;}
.archive-filters input,.archive-filters select{width:100%;padding:9px 12px;background:rgba(255,255,255,0.05);border:1px solid rgba(47,81,159,0.3);
  border-radius:10px;color:var(--white);font-size:0.85rem;font-family:'Montserrat',sans-serif;}
.archive-filters label{display:block;font-size:0.68rem;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;}
.archive-filters select option{background:var(--gray);}
.archive-meta{font-size:0.8rem;color:rgba(255,255,255,0.5);margin-bottom:10px;}
.pager{display:flex;gap:10px;align-items:center;justify-content:center;margin-top:14px;font-size:0.82rem;color:rgba(255,255,255,0.6);}
.status-tag.completed{background:rgba(63,174,106,0.18);color:var(--green);}
.status-tag.no_show{background:rgba(224,160,32,0.18);color:#e0a020;}
.status-tag.past_open{background:rgba(255,255,255,0.1);color:rgba(255,255,255,0.6);}
.tg-status{display:flex;align-items:center;gap:8px;font-weight:600;font-size:0.9rem;margin-bottom:12px;}
.tg-status .dot{width:10px;height:10px;border-radius:50%;background:var(--red);}
.tg-status.on .dot{background:var(--green);}
.settings-hint{color:rgba(255,255,255,0.5);font-size:0.78rem;line-height:1.5;margin-bottom:14px;}
.cancel-list{margin-top:26px;}
.cancel-list h3{font-size:0.95rem;color:rgba(255,255,255,0.6);margin-bottom:10px;}
.cancel-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:10px 14px;border:1px solid rgba(196,30,58,0.3);
  border-radius:10px;margin-bottom:8px;opacity:0.7;font-size:0.88rem;}
.cancel-row .n{flex:1;text-decoration:line-through;}
#loyaltyModal .modal{text-align:center;}
#loyaltyModal .big{font-size:2.4rem;margin-bottom:8px;}
#loyaltyModal p{font-size:0.95rem;line-height:1.6;color:rgba(255,255,255,0.85);}
#loyaltyModal .highlight{margin-top:14px;padding:14px;border-radius:10px;background:var(--red);color:#fff;font-weight:700;font-size:1.05rem;}

/* ---- Leistungen / FAQ Karten ---- */
.svc-card,.faq-card,.msg-card{background:rgba(255,255,255,0.03);border:1px solid rgba(47,81,159,0.2);border-radius:12px;
  padding:16px 18px;margin-bottom:12px;}
.svc-card .row,.faq-card .row{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;}
.svc-card .name{font-weight:700;font-size:1rem;}
.svc-card .meta{font-size:0.78rem;color:rgba(255,255,255,0.55);margin-top:4px;}
.svc-card .desc{font-size:0.82rem;color:rgba(255,255,255,0.65);margin-top:8px;line-height:1.5;}
.svc-card .price{font-weight:700;color:var(--blue-glow);font-size:1rem;white-space:nowrap;}
.svc-actions,.faq-actions{display:flex;gap:6px;flex-shrink:0;}
.faq-card .q{font-weight:600;font-size:0.92rem;}
.faq-card .a{font-size:0.83rem;color:rgba(255,255,255,0.65);margin-top:8px;line-height:1.6;}

/* ---- Nachrichten ---- */
.msg-card.open{border-color:rgba(196,30,58,0.5);}
.msg-card .top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;}
.msg-card .who{font-weight:700;font-size:0.92rem;}
.msg-card .when{font-size:0.72rem;color:rgba(255,255,255,0.45);}
.msg-card .phone{font-size:0.8rem;color:rgba(255,255,255,0.55);margin-top:2px;}
.msg-card .context{font-size:0.75rem;color:rgba(255,255,255,0.45);margin-top:6px;font-style:italic;}
.msg-card .note{font-size:0.88rem;margin-top:10px;line-height:1.6;background:rgba(255,255,255,0.03);
  border-radius:8px;padding:10px 12px;}
.msg-card .reply-box{margin-top:12px;}
.msg-card textarea{width:100%;min-height:70px;padding:10px 12px;background:rgba(255,255,255,0.05);
  border:1px solid rgba(47,81,159,0.3);border-radius:8px;color:var(--white);font-family:'Montserrat',sans-serif;
  font-size:0.85rem;resize:vertical;outline:none;margin-bottom:8px;}
.msg-card .admin-reply{margin-top:10px;font-size:0.85rem;background:rgba(47,81,159,0.15);border-radius:8px;
  padding:10px 12px;line-height:1.6;}
.msg-card .admin-reply b{color:var(--blue-glow);}
.status-pill{font-size:0.68rem;padding:3px 9px;border-radius:12px;text-transform:uppercase;letter-spacing:0.5px;white-space:nowrap;}
.status-pill.open{background:rgba(196,30,58,0.2);color:var(--red);}
.status-pill.seen{background:rgba(255,255,255,0.1);color:rgba(255,255,255,0.6);}
.status-pill.replied{background:rgba(63,174,106,0.18);color:var(--green);}

/* ---- App-Chats ---- */
.app-tag{display:inline-block;margin-left:6px;font-size:0.66rem;padding:1px 7px;border-radius:12px;background:rgba(47,81,159,0.25);color:var(--blue-glow);font-weight:700;}
.chat-layout{display:grid;grid-template-columns:320px 1fr;gap:16px;min-height:520px;}
.chat-list{border:1px solid rgba(47,81,159,0.25);border-radius:12px;overflow-y:auto;max-height:70vh;}
.chat-item{display:block;width:100%;text-align:start;background:none;border:none;border-bottom:1px solid rgba(255,255,255,0.06);
  color:var(--white);padding:12px 14px;cursor:pointer;font-family:'Montserrat',sans-serif;}
.chat-item:hover,.chat-item.active{background:rgba(47,81,159,0.15);}
.chat-item .row1{display:flex;justify-content:space-between;gap:8px;align-items:center;}
.chat-item .nm{font-weight:700;font-size:0.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.chat-item .tm{font-size:0.68rem;color:rgba(255,255,255,0.45);white-space:nowrap;}
.chat-item .sj{font-size:0.72rem;color:var(--blue-glow);margin-top:2px;}
.chat-item .pv{font-size:0.78rem;color:rgba(255,255,255,0.55);margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.chat-item .unread{background:var(--red);color:#fff;font-size:0.68rem;font-weight:700;padding:2px 7px;border-radius:20px;}
.chat-item.has-unread .nm{color:#fff;}
.chat-thread{border:1px solid rgba(47,81,159,0.25);border-radius:12px;display:flex;flex-direction:column;max-height:70vh;min-height:520px;}
.chat-head{padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.08);}
.chat-head .nm{font-weight:700;}
.chat-head .meta{font-size:0.78rem;color:rgba(255,255,255,0.55);margin-top:3px;}
.chat-appt{margin-top:8px;font-size:0.8rem;background:rgba(47,81,159,0.12);border-radius:8px;padding:8px 10px;}
.chat-msgs{flex:1;overflow-y:auto;padding:14px 16px;display:flex;flex-direction:column;gap:8px;}
.bubble{max-width:75%;padding:9px 12px;border-radius:12px;font-size:0.86rem;line-height:1.5;white-space:pre-wrap;word-wrap:break-word;}
.bubble.customer{align-self:flex-start;background:rgba(255,255,255,0.07);border-bottom-left-radius:4px;}
.bubble.staff{align-self:flex-end;background:linear-gradient(135deg,var(--blue),var(--blue-light));border-bottom-right-radius:4px;}
.bubble .bm{font-size:0.66rem;opacity:0.7;margin-top:4px;text-align:end;}
.bubble .bm.err{color:#ffb4b4;opacity:1;}
.chat-compose{display:flex;gap:8px;padding:12px;border-top:1px solid rgba(255,255,255,0.08);}
.chat-compose textarea{flex:1;min-height:44px;max-height:140px;padding:10px 12px;background:rgba(255,255,255,0.05);border:1px solid rgba(47,81,159,0.3);
  border-radius:10px;color:var(--white);font-family:'Montserrat',sans-serif;font-size:0.86rem;resize:vertical;outline:none;}
.chat-empty{margin:auto;color:rgba(255,255,255,0.4);font-size:0.9rem;text-align:center;padding:30px;}
@media (max-width:760px){
  .chat-layout{grid-template-columns:1fr;}
  .chat-layout.thread-open .chat-list{display:none;}
  .chat-layout:not(.thread-open) .chat-thread{display:none;}
}
.chat-back{display:none;}
@media (max-width:760px){.chat-back{display:inline-block;margin-bottom:8px;}}

/* modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:200;align-items:center;justify-content:center;padding:20px;}
.modal-overlay.show{display:flex;}
.modal{width:100%;max-width:440px;max-height:90vh;overflow-y:auto;background:var(--gray);border:1px solid rgba(47,81,159,0.4);border-radius:16px;padding:26px;}
.modal h3{margin-bottom:18px;font-size:1.1rem;}
.modal .form-group{margin-bottom:12px;}
.modal label{display:block;font-size:0.75rem;color:rgba(255,255,255,0.55);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;}
.modal input,.modal select,.modal textarea{width:100%;padding:10px 12px;background:rgba(255,255,255,0.05);border:1px solid rgba(47,81,159,0.3);
  border-radius:8px;color:var(--white);font-size:0.9rem;font-family:'Montserrat',sans-serif;}
.form-group{margin-bottom:12px;}
.form-group label{display:block;font-size:0.75rem;color:rgba(255,255,255,0.55);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;}
.form-group input{width:100%;padding:10px 12px;background:rgba(255,255,255,0.05);border:1px solid rgba(47,81,159,0.3);
  border-radius:8px;color:var(--white);font-size:0.9rem;font-family:'Montserrat',sans-serif;}
.modal textarea{min-height:80px;resize:vertical;}
.modal-actions{display:flex;gap:10px;margin-top:18px;}
.modal-actions .btn{flex:1;}
.modal-err{color:var(--red);font-size:0.82rem;margin-bottom:10px;min-height:16px;}

.toast-wrap{position:fixed;bottom:20px;right:20px;z-index:300;display:flex;flex-direction:column;gap:10px;}
.toast{background:var(--blue);color:#fff;padding:14px 18px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.4);
  font-size:0.85rem;max-width:300px;animation:slidein .3s ease;}
@keyframes slidein{from{transform:translateX(30px);opacity:0;}to{transform:translateX(0);opacity:1;}}
</style>
</head>
<body>

<!-- Login -->
<div id="loginScreen">
  <div class="login-box">
    <div class="logo-icon"><img src="https://i.ibb.co/pvm4r092/Element-255.png" alt="Logo"></div>
    <h2>WOLF <span style="color:var(--blue)">FRISEUR</span><br><small style="font-size:0.8rem;color:rgba(255,255,255,0.5);letter-spacing:2px;">DASHBOARD</small></h2>
    <div class="err" id="loginErr"></div>
    <input type="text" id="loginUser" placeholder="Benutzername" autocomplete="username">
    <input type="password" id="loginPass" placeholder="Passwort" autocomplete="current-password">
    <button class="btn btn-primary" style="width:100%;padding:13px;" id="loginBtn">Anmelden</button>
  </div>
</div>

<!-- App -->
<div id="app">
  <nav class="navbar">
    <div class="logo">
      <div class="logo-icon"><img src="https://i.ibb.co/pvm4r092/Element-255.png" alt="Logo"></div>
      <div class="logo-text">WOLF <span>FRISEUR</span> · Dashboard</div>
    </div>
    <div class="nav-right">
      <button class="logout-btn" id="logoutBtn">Abmelden</button>
    </div>
  </nav>

  <!-- Alarm-Banner: erscheint bei neuer Kundenbuchung, blinkt + piept bis Bestätigung -->
  <div id="alertBanner">
    <div class="txt"><i class="fas fa-triangle-exclamation"></i><span id="alertText">Neue Terminbuchung!</span></div>
    <button id="alertAckBtn">Ich habe die Benachrichtigung erhalten</button>
  </div>

  <div class="wrap">
    <div class="tabbar">
      <button class="tab-btn active" data-tab="termine">Termine</button>
      <button class="tab-btn" data-tab="leistungen">Leistungen</button>
      <button class="tab-btn" data-tab="faq">FAQ</button>
      <button class="tab-btn" data-tab="archiv">Archiv</button>
      <button class="tab-btn" data-tab="appchats">App-Chats<span class="tab-badge" id="chatTabBadge">0</span></button>
      <button class="tab-btn" data-tab="nachrichten">Nachrichten<span class="tab-badge" id="msgTabBadge">0</span></button>
      <button class="tab-btn" data-tab="einstellungen">Einstellungen</button>
    </div>

    <!-- ---- Tab: Termine ---- -->
    <div class="tab-panel active" id="tab-termine">
      <div class="toolbar">
        <div>
          <h1>Termine</h1>
          <select class="day-select" id="daySelect"></select>
        </div>
        <div class="filters">
          <select id="filterStatus">
            <option value="active">Aktiv</option>
            <option value="all">Alle</option>
            <option value="cancelled">Abgesagt</option>
          </select>
          <button class="btn btn-primary" id="manualBtn"><i class="fas fa-plus"></i> Termin eintragen</button>
          <button class="btn btn-outline" id="blockBtn"><i class="fas fa-lock"></i> Zeit blockieren</button>
          <button class="btn btn-primary" id="refreshBtn"><i class="fas fa-rotate"></i> Aktualisieren</button>
        </div>
      </div>
      <div id="list"></div>
    </div>

    <!-- ---- Tab: Leistungen ---- -->
    <div class="tab-panel" id="tab-leistungen">
      <div class="toolbar">
        <h1>Leistungen (Herren)</h1>
        <button class="btn btn-primary" id="addServiceBtn"><i class="fas fa-plus"></i> Neue Leistung</button>
      </div>
      <p class="hint" style="color:rgba(255,255,255,0.5);font-size:0.82rem;margin-bottom:18px;">
        Änderungen erscheinen sofort auf der Buchungsseite der Kunden. Eine geänderte Dauer gilt nur für neue
        Buchungen – bestehende Termine behalten ihre gespeicherte Dauer. Beim Kinderhaarschnitt (fester
        Systemeintrag) ist nur die Dauer änderbar.
      </p>
      <div id="servicesList"></div>
    </div>

    <!-- ---- Tab: FAQ ---- -->
    <div class="tab-panel" id="tab-faq">
      <div class="toolbar">
        <h1>Häufige Fragen</h1>
        <button class="btn btn-primary" id="addFaqBtn"><i class="fas fa-plus"></i> Neue Frage</button>
      </div>
      <p class="hint" style="color:rgba(255,255,255,0.5);font-size:0.82rem;margin-bottom:18px;">
        Diese Fragen &amp; Antworten sieht der Kunde, sobald er nach Auswahl von Leistung und Termin auf
        „Ja, ich habe eine Frage" klickt.
      </p>
      <div id="faqsList"></div>
    </div>

    <!-- ---- Tab: Archiv ---- -->
    <div class="tab-panel" id="tab-archiv">
      <div class="toolbar">
        <h1>Archiv</h1>
        <button class="btn btn-outline" id="archiveResetBtn"><i class="fas fa-rotate-left"></i> Filter zurücksetzen</button>
      </div>
      <p class="hint" style="color:rgba(255,255,255,0.5);font-size:0.82rem;margin-bottom:18px;">
        Vergangene, abgesagte und nicht erschienene Termine. Jeder Termin wird ein Jahr ab seiner Erstellung
        aufbewahrt und danach automatisch gelöscht.
      </p>
      <div class="archive-filters">
        <div><label>Name</label><input id="arcName" placeholder="Teil des Namens"></div>
        <div><label>Telefon</label><input id="arcPhone" placeholder="Nummer oder Teil"></div>
        <div><label>Datum</label><input type="date" id="arcDate"></div>
        <div><label>Von</label><input type="date" id="arcFrom"></div>
        <div><label>Bis</label><input type="date" id="arcTo"></div>
        <div><label>Status</label>
          <select id="arcStatus">
            <option value="">Alle</option>
            <option value="completed">Erledigt</option>
            <option value="cancelled">Abgesagt</option>
            <option value="no_show">Nicht erschienen</option>
            <option value="past_open">Vergangen (ohne Check-in)</option>
          </select>
        </div>
      </div>
      <div class="archive-meta" id="archiveMeta"></div>
      <div id="archiveList"></div>
      <div class="pager" id="archivePager"></div>
    </div>

    <!-- ---- Tab: App-Chats ---- -->
    <div class="tab-panel" id="tab-appchats">
      <div class="toolbar">
        <h1>App-Chats</h1>
        <button class="btn btn-outline" id="refreshChatsBtn"><i class="fas fa-rotate"></i> Aktualisieren</button>
      </div>
      <p class="hint" style="color:rgba(255,255,255,0.5);font-size:0.82rem;margin-bottom:18px;">
        Nachrichten von Kunden aus der Wolf-Friseur-App. Ihre Antwort erscheint sofort in der App des Kunden
        (mit Push-Benachrichtigung, falls eingerichtet). Neue Nachrichten erscheinen hier automatisch.
      </p>
      <div class="chat-layout" id="chatLayout">
        <div class="chat-list" id="chatList"></div>
        <div class="chat-thread" id="chatThread"><div class="chat-empty">Bitte links eine Unterhaltung auswählen.</div></div>
      </div>
    </div>

    <!-- ---- Tab: Nachrichten ---- -->
    <div class="tab-panel" id="tab-nachrichten">
      <div class="toolbar">
        <h1>Kundennachrichten</h1>
        <button class="btn btn-outline" id="refreshMsgsBtn"><i class="fas fa-rotate"></i> Aktualisieren</button>
      </div>
      <p class="hint" style="color:rgba(255,255,255,0.5);font-size:0.82rem;margin-bottom:18px;">
        Diese Nachrichten kommen von Kunden, die die FAQ-Antworten nicht hilfreich fanden. Ihre Antwort
        kann der Kunde über „Antwort auf Ihre Nachricht prüfen" auf der Buchungsseite einsehen.
      </p>
      <div id="messagesList"></div>
    </div>

    <!-- ---- Tab: Einstellungen ---- -->
    <div class="tab-panel" id="tab-einstellungen">
      <div class="toolbar">
        <h1>Einstellungen</h1>
      </div>
      <div class="svc-card" style="max-width:420px;">
        <h3 style="font-size:1rem;margin-bottom:16px;">Dashboard-Passwort ändern</h3>
        <div class="modal-err" id="pwErr" style="min-height:16px;"></div>
        <div class="modal-err" id="pwOk" style="min-height:16px;color:var(--green);"></div>
        <div class="form-group">
          <label>Aktuelles Passwort</label>
          <input type="password" id="pwCurrent" autocomplete="current-password">
        </div>
        <div class="form-group">
          <label>Neues Passwort</label>
          <input type="password" id="pwNew" autocomplete="new-password">
        </div>
        <div class="form-group">
          <label>Neues Passwort bestätigen</label>
          <input type="password" id="pwNewConfirm" autocomplete="new-password">
        </div>
        <p class="hint" style="color:rgba(255,255,255,0.45);font-size:0.75rem;margin-bottom:14px;">Mindestens 8 Zeichen.</p>
        <button class="btn btn-primary" id="pwSaveBtn" style="width:100%;">Passwort speichern</button>
      </div>

      <div class="svc-card" style="max-width:420px;">
        <h3 style="font-size:1rem;margin-bottom:16px;">Online-Buchung: Mindestvorlauf</h3>
        <div class="modal-err" id="leadErr"></div>
        <div class="form-group">
          <label>Minuten vor Terminbeginn</label>
          <input type="number" id="leadMinutes" min="0" max="10080" step="1">
        </div>
        <p class="settings-hint">Kunden können online frühestens so viele Minuten im Voraus buchen (z.&nbsp;B. 40 → um 10:00 Uhr ist 10:40 Uhr der früheste Termin). Gilt nicht für Termine, die Sie selbst im Dashboard eintragen.</p>
        <button class="btn btn-primary" id="leadSaveBtn" style="width:100%;">Speichern</button>
      </div>

      <div class="svc-card" style="max-width:420px;">
        <h3 style="font-size:1rem;margin-bottom:16px;">Telegram-Benachrichtigung</h3>
        <div class="tg-status" id="tgStatus"><span class="dot"></span><span id="tgStatusText">Wird geladen…</span></div>
        <p class="settings-hint" id="tgHint"></p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <button class="btn btn-primary" id="tgConnectBtn"><i class="fab fa-telegram"></i> Telegram verbinden</button>
          <button class="btn btn-outline" id="tgTestBtn" style="display:none;">Testnachricht</button>
          <button class="btn btn-danger" id="tgDisconnectBtn" style="display:none;">Verbindung trennen</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<!-- Ergebnis-Fenster nach "Bestätigen" (Treuekarte) -->
<div class="modal-overlay" id="loyaltyModal">
  <div class="modal">
    <div class="big" id="loyaltyIcon">✓</div>
    <h3 id="loyaltyTitle">Besuch bestätigt</h3>
    <p id="loyaltyText"></p>
    <div class="highlight" id="loyaltyHighlight" style="display:none;"></div>
    <div class="modal-actions">
      <button class="btn btn-primary" id="loyaltyOkBtn">OK</button>
    </div>
  </div>
</div>

<!-- Edit Modal (Termin) -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <h3>Termin bearbeiten</h3>
    <div class="modal-err" id="editErr"></div>
    <div class="form-group"><label>Vorname</label><input id="editVorname"></div>
    <div class="form-group"><label>Nachname</label><input id="editNachname"></div>
    <div class="form-group"><label>Telefon</label><input id="editTelefon"></div>
    <div class="form-group"><label>Datum</label><input type="date" id="editDate"></div>
    <div class="form-group"><label>Uhrzeit</label><input type="time" id="editTime"></div>
    <div class="form-group" id="editServiceWrap">
      <label>Leistung</label>
      <select id="editService"></select>
    </div>
    <div class="form-group">
      <label>Zeit verlängern / verkürzen</label>
      <select id="editDelta"></select>
    </div>
    <div class="fit-hint" id="editFit"></div>
    <div class="modal-actions">
      <button class="btn btn-outline" id="editCancelBtn">Abbrechen</button>
      <button class="btn btn-primary" id="editSaveBtn">Speichern</button>
    </div>
  </div>
</div>

<!-- Block Modal -->
<div class="modal-overlay" id="blockModal">
  <div class="modal">
    <h3>Zeitfenster blockieren</h3>
    <div class="form-group"><label>Datum</label><input type="date" id="blockDate"></div>
    <div class="form-group"><label>Uhrzeit</label><input type="time" id="blockTime"></div>
    <div class="form-group"><label>Dauer (Minuten)</label><input type="number" id="blockDuration" min="1" step="1" value="30"></div>
    <div class="form-group"><label>Notiz (optional)</label><input id="blockNote" placeholder="z.B. Pause, Urlaub"></div>
    <div class="modal-actions">
      <button class="btn btn-outline" id="blockCancelBtn">Abbrechen</button>
      <button class="btn btn-primary" id="blockSaveBtn">Blockieren</button>
    </div>
  </div>
</div>

<!-- Manueller Termin -->
<div class="modal-overlay" id="manualModal">
  <div class="modal">
    <h3>Termin eintragen</h3>
    <div class="modal-err" id="manualErr"></div>
    <div class="form-group"><label>Vorname</label><input id="manVorname"></div>
    <div class="form-group"><label>Nachname</label><input id="manNachname"></div>
    <div class="form-group"><label>Telefon (optional)</label><input id="manTelefon" type="tel"></div>
    <div class="form-group"><label>Leistung</label><select id="manService"></select></div>
    <div class="form-group"><label>Datum</label><input type="date" id="manDate"></div>
    <div class="form-group"><label>Uhrzeit (beliebige Minute)</label><input type="time" id="manTime"></div>
    <div class="fit-hint" id="manFit"></div>
    <div class="modal-actions">
      <button class="btn btn-outline" id="manualCancelBtn">Abbrechen</button>
      <button class="btn btn-primary" id="manualSaveBtn">Eintragen</button>
    </div>
  </div>
</div>

<!-- Leistung Modal (Add/Edit) -->
<div class="modal-overlay" id="serviceModal">
  <div class="modal">
    <h3 id="serviceModalTitle">Neue Leistung</h3>
    <div class="modal-err" id="serviceModalErr"></div>
    <input type="hidden" id="svcKey">
    <div class="form-group svc-full"><label>Name</label><input id="svcLabel" placeholder="z.B. VIP Complete"></div>
    <div class="form-group svc-full"><label>Preis (€)</label><input type="number" id="svcPrice" min="0" step="0.5" placeholder="z.B. 45"></div>
    <div class="form-group">
      <label>Dauer (Minuten)</label>
      <input type="number" id="svcDuration" min="1" max="600" step="1" placeholder="z.B. 20">
      <p class="settings-hint" style="margin:6px 0 0;">Beliebige Minutenzahl. Gilt nur für neue Buchungen – bestehende Termine behalten ihre Dauer.</p>
    </div>
    <div class="form-group svc-full"><label>Beschreibung (kurz)</label><textarea id="svcDescription" placeholder="Zwei Zeilen, die dem Kunden angezeigt werden"></textarea></div>
    <div class="modal-actions">
      <button class="btn btn-outline" id="serviceCancelBtn">Abbrechen</button>
      <button class="btn btn-primary" id="serviceSaveBtn">Speichern</button>
    </div>
  </div>
</div>

<!-- FAQ Modal (Add/Edit) -->
<div class="modal-overlay" id="faqModal">
  <div class="modal">
    <h3 id="faqModalTitle">Neue Frage</h3>
    <div class="modal-err" id="faqModalErr"></div>
    <input type="hidden" id="faqId">
    <div class="form-group"><label>Frage</label><input id="faqQuestion" placeholder="z.B. Muss ich einen Termin buchen?"></div>
    <div class="form-group"><label>Antwort</label><textarea id="faqAnswer" placeholder="Antworttext..."></textarea></div>
    <div class="modal-actions">
      <button class="btn btn-outline" id="faqCancelBtn">Abbrechen</button>
      <button class="btn btn-primary" id="faqSaveBtn">Speichern</button>
    </div>
  </div>
</div>

<script>
let appointments = [];
let SERVICES = [];
let knownIds = new Set();
let firstLoad = true;
let notifPermissionAsked = false;
let serverToday = null;       // heutiges Datum laut Server (Europe/Berlin)
let selectedDate = null;      // im Dropdown gewählter Tag (heute … +7 Tage)
let BUSINESS_HOURS = null;    // Öffnungszeiten aus config.php
let WINDOW_DAYS = 7;
let renderedLocalDay = null;  // für automatischen Tageswechsel um Mitternacht
let serverNowTime = null;     // aktuelle Uhrzeit laut Server (HH:MM)
let serverNowLoadedAt = 0;

const $ = sel => document.querySelector(sel);

function toast(text) {
  const t = document.createElement('div');
  t.className = 'toast';
  t.textContent = text;
  $('#toastWrap').appendChild(t);
  setTimeout(() => t.remove(), 6000);
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
}

function fmtDate(d) {
  const dt = new Date(d + 'T00:00:00');
  const days = ['So','Mo','Di','Mi','Do','Fr','Sa'];
  return `${days[dt.getDay()]}, ${dt.toLocaleDateString('de-DE')}`;
}

function fmtPrice(p) {
  if (p === null || p === undefined || p === '') return '';
  return Number(p).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}

// ---- Alarm-Sound (Web Audio, kein Audio-File nötig) ----
let audioCtx = null;
function ensureAudioCtx() {
  try {
    if (!audioCtx) {
      audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    } else if (audioCtx.state === 'suspended') {
      audioCtx.resume();
    }
  } catch (e) { /* Web Audio nicht verfügbar */ }
}
function beep(freq) {
  if (!audioCtx) return;
  try {
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.type = 'square';
    osc.frequency.value = freq;
    gain.gain.value = 0.16;
    osc.connect(gain); gain.connect(audioCtx.destination);
    osc.start();
    osc.stop(audioCtx.currentTime + 0.3);
  } catch (e) {}
}

let alarmInterval = null;
let titleFlashInterval = null;
const ORIGINAL_TITLE = document.title;
let alarmActive = false;

function startAlarm(count) {
  $('#alertText').textContent = count > 1 ? `${count} neue Terminbuchungen!` : 'Neue Terminbuchung!';
  $('#alertBanner').classList.add('show');
  if (alarmActive) return;
  alarmActive = true;
  ensureAudioCtx();
  let tick = 0;
  alarmInterval = setInterval(() => {
    beep(tick % 2 === 0 ? 880 : 660);
    tick++;
  }, 500);
  titleFlashInterval = setInterval(() => {
    document.title = document.title === ORIGINAL_TITLE ? '🔴 NEUE BUCHUNG!' : ORIGINAL_TITLE;
  }, 1000);
}

function stopAlarm() {
  alarmActive = false;
  $('#alertBanner').classList.remove('show');
  if (alarmInterval) { clearInterval(alarmInterval); alarmInterval = null; }
  if (titleFlashInterval) { clearInterval(titleFlashInterval); titleFlashInterval = null; }
  document.title = ORIGINAL_TITLE;
}

$('#alertAckBtn').addEventListener('click', () => {
  fetch('admin-api.php?action=ack_alert', { method: 'POST' }).then(() => { stopAlarm(); loadList(); });
});

// ---- Auth ----
function checkLogin() {
  fetch('admin-api.php?action=check').then(r => r.json()).then(d => {
    if (d.logged_in) showApp(); else showLogin();
  });
}
function showLogin() {
  $('#loginScreen').style.display = 'flex';
  $('#app').style.display = 'none';
}
function showApp() {
  $('#loginScreen').style.display = 'none';
  $('#app').style.display = 'block';
  loadList();
  loadServices();
  loadFaqs();
  loadMessages();
  loadConversations();
  loadSettings();
  loadTelegramStatus();
  startLivePolling();
}

// ---- Echtzeit-Updates (long-polling) ----
let lastMtime = 0;
let polling = false;

function startLivePolling() {
  if (polling) return;
  polling = true;
  pollOnce();
}

function pollOnce() {
  fetch(`admin-api.php?action=poll&since=${lastMtime}`)
    .then(r => r.json())
    .then(d => {
      if (d.mtime) lastMtime = d.mtime;
      if (d.changed) { loadList(); loadMessages(); loadConversations(); }
    })
    .catch(() => {})
    .finally(() => {
      setTimeout(pollOnce, 400);
    });
}

$('#loginBtn').addEventListener('click', () => {
  $('#loginErr').textContent = '';
  ensureAudioCtx();
  const body = new URLSearchParams({ action: 'login', username: $('#loginUser').value, password: $('#loginPass').value });
  fetch('admin-api.php', { method: 'POST', body }).then(r => r.json()).then(d => {
    if (d.success) { showApp(); } else { $('#loginErr').textContent = d.message || 'Fehler bei der Anmeldung.'; }
  });
});
$('#loginPass').addEventListener('keydown', e => { if (e.key === 'Enter') $('#loginBtn').click(); });

$('#logoutBtn').addEventListener('click', () => {
  fetch('admin-api.php?action=logout').then(() => { stopAlarm(); showLogin(); });
});

// ---- Tabs ----
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    $('#tab-' + btn.dataset.tab).classList.add('active');
  });
});

// ---- List (Termine) ----
function loadList() {
  fetch('admin-api.php?action=list').then(r => r.json()).then(d => {
    if (d.error) { showLogin(); return; }
    appointments = d.appointments || [];
    SERVICES = d.services || [];
    if (d.today) serverToday = d.today;
    if (d.now_time) { serverNowTime = d.now_time; serverNowLoadedAt = Date.now(); }
    if (d.business_hours) BUSINESS_HOURS = d.business_hours;
    if (d.booking_window_days !== undefined) WINDOW_DAYS = d.booking_window_days;
    renderedLocalDay = new Date().toDateString();
    if (d.mtime) lastMtime = d.mtime;
    checkForNew();

    if (d.alert_pending) {
      startAlarm(d.alert_count || 1);
    } else {
      stopAlarm();
    }
    const msgBadge = $('#msgTabBadge');
    if (d.open_messages > 0) {
      msgBadge.textContent = d.open_messages;
      msgBadge.style.display = 'inline-block';
    } else {
      msgBadge.style.display = 'none';
    }
    setChatBadge(d.app_unread || 0);

    render();
    populateEditServiceOptions();
    populateManualServiceOptions();
  });
}

function checkForNew() {
  const isLive = a => a.source === 'customer' && (a.status === 'confirmed' || a.status === 'pending');
  const currentIds = new Set(appointments.filter(isLive).map(a => a.id));
  if (!firstLoad) {
    const newOnes = appointments.filter(a => isLive(a) && !knownIds.has(a.id));
    newOnes.forEach(a => {
      toast(`Neue Buchung: ${a.vorname} ${a.nachname} – ${a.date} ${a.time}`);
      if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('Neue Terminbuchung', { body: `${a.vorname} ${a.nachname} – ${a.date} ${a.time}` });
      }
    });
  } else if ('Notification' in window && Notification.permission === 'default' && !notifPermissionAsked) {
    notifPermissionAsked = true;
    Notification.requestPermission();
  }
  knownIds = currentIds;
  firstLoad = false;
}

function localTodayISO() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function fmtLongDate(d) {
  const dt = new Date(d + 'T00:00:00');
  return dt.toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
}

function endTime(time, duration) {
  const [h, m] = time.split(':').map(Number);
  const t = h * 60 + m + Number(duration || 0);
  return `${String(Math.floor(t / 60) % 24).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`;
}

function toMin(t) { const [h, m] = String(t).split(':').map(Number); return h * 60 + m; }
function toTime(mins) { return `${String(Math.floor(mins / 60) % 24).padStart(2, '0')}:${String(mins % 60).padStart(2, '0')}`; }
function addDaysISO(iso, n) {
  const [y, m, d] = iso.split('-').map(Number);
  const dt = new Date(y, m - 1, d + n);
  return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
}
function dayKeyOf(iso) {
  const [y, m, d] = iso.split('-').map(Number);
  return ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][new Date(y, m - 1, d).getDay()];
}

// ---- Dropdown: heute + die nächsten 7 Tage ----
function buildDaySelect() {
  const today = serverToday || localTodayISO();
  if (!selectedDate || selectedDate < today || selectedDate > addDaysISO(today, WINDOW_DAYS)) selectedDate = today;
  const sel = $('#daySelect');
  sel.innerHTML = '';
  for (let i = 0; i <= WINDOW_DAYS; i++) {
    const iso = addDaysISO(today, i);
    const count = appointments.filter(a => a.date === iso && a.status !== 'cancelled' && a.type !== 'block').length;
    const closed = BUSINESS_HOURS && !BUSINESS_HOURS[dayKeyOf(iso)];
    const opt = document.createElement('option');
    opt.value = iso;
    opt.textContent = `${fmtLongDate(iso)}${i === 0 ? ' (Heute)' : i === 1 ? ' (Morgen)' : ''} – ${closed ? 'geschlossen' : count + ' Termin' + (count === 1 ? '' : 'e')}`;
    sel.appendChild(opt);
  }
  sel.value = selectedDate;
}
$('#daySelect').addEventListener('change', e => { selectedDate = e.target.value; render(); });

// ---- Tagesplan ----
// Jede Stunde der Öffnungszeit ist eine Zeile, aufgeteilt in 4 Viertelstunden (grau = frei).
// Ein Termin belegt genau seine Viertelstunden und wird als ein farbiger Block darüber gelegt –
// auch über eine volle Stunde hinweg (z. B. 09:45–10:15 reicht sauber über beide Stunden-Zeilen).
function render() {
  buildDaySelect();
  const date = selectedDate;
  const statusFilter = $('#filterStatus').value;
  const dayAppts = appointments.filter(a => a.date === date);
  const active = dayAppts.filter(a => a.status !== 'cancelled').sort((a, b) => a.time.localeCompare(b.time));
  const cancelled = dayAppts.filter(a => a.status === 'cancelled').sort((a, b) => a.time.localeCompare(b.time));

  const list = $('#list');
  list.innerHTML = '';
  if (statusFilter !== 'cancelled') list.appendChild(buildPlan(date, active));
  if (statusFilter !== 'active' && cancelled.length) list.appendChild(buildCancelledList(cancelled));
  if (statusFilter === 'cancelled' && !cancelled.length) {
    list.innerHTML = '<div class="empty-state">Keine abgesagten Termine an diesem Tag.</div>';
  }
}

function buildPlan(date, active) {
  const hours = BUSINESS_HOURS ? BUSINESS_HOURS[dayKeyOf(date)] : ['09:00', '19:00'];
  let start = hours ? toMin(hours[0]) : null;
  let end = hours ? toMin(hours[1]) : null;
  // Termine außerhalb der Öffnungszeit (z. B. manuell verschoben) trotzdem anzeigen
  active.forEach(a => {
    const s = toMin(a.time), e = s + Number(a.duration || 30);
    start = start === null ? s : Math.min(start, s);
    end = end === null ? e : Math.max(end, e);
  });
  if (start === null) {
    const d = document.createElement('div');
    d.className = 'empty-state';
    d.textContent = 'An diesem Tag ist der Salon geschlossen – keine Termine.';
    return d;
  }
  start = Math.floor(start / 60) * 60;
  end = Math.ceil(end / 60) * 60;
  const quarters = (end - start) / 15;
  const totalMin = end - start;

  const wrap = document.createElement('div');
  wrap.className = 'plan-wrap';
  const inner = document.createElement('div');
  inner.className = 'plan-inner';
  inner.innerHTML = `
    <div class="plan-head">
      <div>Uhrzeit</div>
      <div class="plan-cols"><div>Zeit</div><div>Kundenname</div><div>Leistung</div><div>Dauer</div><div style="text-align:right">Aktionen</div></div>
    </div>`;

  const plan = document.createElement('div');
  plan.className = 'plan';
  // Eine Grid-Zeile pro Minute (gleiche Höhe wie vorher: 34px je Viertelstunde),
  // damit jeder Termin exakt bei seiner Start-/Endminute liegt (z. B. 09:07–09:27).
  plan.style.gridTemplateRows = `repeat(${totalMin}, ${PX_PER_MIN}px)`;
  plan.style.gridAutoRows = `${PX_PER_MIN}px`;

  // Stunden-Beschriftung + graue Viertelstunden (nur Orientierung)
  for (let q = 0; q < quarters; q++) {
    const mins = start + q * 15;
    if (q % 4 === 0) {
      const h = document.createElement('div');
      h.className = 'hour';
      h.style.gridRow = `${q * 15 + 1} / span 60`;
      h.textContent = toTime(mins);
      plan.appendChild(h);
    }
    const cell = document.createElement('div');
    cell.className = 'q' + (q % 4 === 0 ? ' h' : '');
    cell.style.gridRow = `${q * 15 + 1} / span 15`;
    cell.textContent = `${toTime(mins)} – ${toTime(mins + 15)}`;
    plan.appendChild(cell);
  }

  // Freie Lücken (ab jetzt) – anklickbar für einen manuellen Termin
  freeGaps(date, active).forEach(([gs, ge]) => plan.appendChild(renderGap(date, gs, ge, start)));

  // Termine als Blöcke, minutengenau
  active.forEach(a => plan.appendChild(renderBlock(a, start)));

  inner.appendChild(plan);
  const endRow = document.createElement('div');
  endRow.className = 'plan-end';
  endRow.innerHTML = `<div>${toTime(end)}</div><div>Feierabend</div>`;
  inner.appendChild(endRow);
  wrap.appendChild(inner);
  return wrap;
}

const PX_PER_MIN = 34 / 15;

// Aktuelle Uhrzeit laut Server (Europe/Berlin), fortgeschrieben seit dem letzten Laden
function serverNowMin() {
  if (!serverNowTime) { const d = new Date(); return d.getHours() * 60 + d.getMinutes(); }
  return toMin(serverNowTime) + Math.floor((Date.now() - serverNowLoadedAt) / 60000);
}

// Freie Lücken eines Tages innerhalb der Öffnungszeiten (heute erst ab jetzt): [[von, bis], ...]
function freeGaps(date, active, excludeId) {
  const hours = BUSINESS_HOURS ? BUSINESS_HOURS[dayKeyOf(date)] : null;
  if (!hours) return [];
  let cursor = toMin(hours[0]);
  const close = toMin(hours[1]);
  const today = serverToday || localTodayISO();
  if (date < today) return [];
  if (date === today) cursor = Math.max(cursor, serverNowMin());
  const busy = active.filter(a => a.id !== excludeId)
    .map(a => [toMin(a.time), toMin(a.time) + Number(a.duration || 0)])
    .sort((x, y) => x[0] - y[0]);
  const gaps = [];
  busy.forEach(([bs, be]) => {
    if (bs > cursor) gaps.push([cursor, Math.min(bs, close)]);
    cursor = Math.max(cursor, be);
  });
  if (cursor < close) gaps.push([cursor, close]);
  return gaps.filter(g => g[1] > g[0]);
}

function renderGap(date, gs, ge, dayStart) {
  const el = document.createElement('div');
  el.className = 'gap-block';
  el.style.gridRow = `${gs - dayStart + 1} / span ${ge - gs}`;
  const label = `Frei ${toTime(gs)} – ${toTime(ge)} · ${ge - gs} Min`;
  el.title = label + ' – klicken, um einen Termin einzutragen';
  if (ge - gs >= 8) el.innerHTML = `<span><i class="fas fa-plus"></i> ${escapeHtml(label)}</span>`;
  el.addEventListener('click', () => openManual(date, toTime(gs)));
  return el;
}

// Verschiebungswunsch aus der App: Hinweis auf den bisherigen Termin
function replacesLabel(a) {
  const old = appointments.find(x => x.id === a.replaces);
  return old ? `Verschiebung von ${fmtDate(old.date)} ${old.time} – Annehmen gibt den alten Termin frei` : 'Verschiebungswunsch';
}

function renderBlock(a, dayStart) {
  const s = toMin(a.time);
  const dur = Number(a.duration || 30);
  const e = s + dur;
  const rowStart = s - dayStart + 1;
  const span = Math.max(1, dur);
  const isBlocked = a.type === 'block';
  const isNoShow = a.status === 'no_show';

  const el = document.createElement('div');
  const isPending = a.status === 'pending';
  el.className = 'appt-block' + (a.checked_in ? ' done' : '') + (isBlocked ? ' blocked' : '') + (isPending ? ' pending' : '') + (isNoShow ? ' noshow' : '');
  el.style.gridRow = `${rowStart} / span ${span}`;
  el.title = `${toTime(s)} – ${toTime(e)} (${dur} Min)`;

  // Termin reicht über eine volle Stunde -> feine Linie an der Stundengrenze im Block
  const nextHour = Math.floor(s / 60) * 60 + 60;
  if (!isBlocked && nextHour > s && nextHour < e) {
    el.classList.add('cross');
    el.style.setProperty('--cut', `${((nextHour - s) / dur) * 100}%`);
  }

  const name = isBlocked ? `Blockiert${a.service_label && a.service_label !== 'Blockiert' ? ' – ' + a.service_label : ''}` : `${a.vorname} ${a.nachname}`;
  const sub = isBlocked ? '' : `${escapeHtml(a.telefon || '')}${isPending ? '<span class="pending-tag">Neu – wartet auf Annahme</span>' : ''}${a.channel === 'app' ? '<span class="app-tag">App</span>' : ''}${isPending && a.replaces ? `<span class="pending-tag">${escapeHtml(replacesLabel(a))}</span>` : ''}${isNoShow ? '<span class="noshow-tag">Nicht erschienen</span>' : ''}${loyaltyBadge(a)}`;
  const svc = isBlocked ? '–' : `${escapeHtml(a.service_label || '')}${a.type === 'kind' ? ' (Kind)' : ''}`;

  el.innerHTML = `
    <div class="plan-cols">
      <div class="t">${escapeHtml(toTime(s))} – ${escapeHtml(toTime(e))}</div>
      <div style="min-width:0"><div class="n">${escapeHtml(name)}</div>${sub ? `<div class="sub">${sub}</div>` : ''}</div>
      <div class="svc">${svc}</div>
      <div class="dur">${escapeHtml(dur)} Min</div>
      <div class="appt-actions"></div>
    </div>`;

  const actions = el.querySelector('.appt-actions');
  if (isPending) {
    const acceptBtn = document.createElement('button');
    acceptBtn.className = 'btn btn-confirm btn-small';
    acceptBtn.innerHTML = '<i class="fas fa-check"></i> Annehmen';
    acceptBtn.addEventListener('click', () => doDecision(a, true, acceptBtn));
    actions.appendChild(acceptBtn);

    const rejectBtn = document.createElement('button');
    rejectBtn.className = 'btn btn-danger btn-small';
    rejectBtn.innerHTML = '<i class="fas fa-xmark"></i> Ablehnen';
    rejectBtn.addEventListener('click', () => doDecision(a, false, rejectBtn));
    actions.appendChild(rejectBtn);
  } else if (isNoShow) {
    const undoBtn = document.createElement('button');
    undoBtn.className = 'btn btn-outline btn-small';
    undoBtn.textContent = 'Rückgängig';
    undoBtn.addEventListener('click', () => doAction('noshow', { id: a.id, undo: 1 }));
    actions.appendChild(undoBtn);
  } else if (!isBlocked) {
    // Termin hat begonnen, Kunde nicht da -> "Nicht erschienen" (fürs Archiv)
    const started = (a.date < (serverToday || localTodayISO())) || (a.date === (serverToday || localTodayISO()) && s <= serverNowMin());
    if (!a.checked_in && started) {
      const nsBtn = document.createElement('button');
      nsBtn.className = 'btn btn-outline btn-small';
      nsBtn.textContent = 'Nicht erschienen';
      nsBtn.addEventListener('click', () => {
        if (confirm(`${a.vorname} ${a.nachname} (${a.time}) als „Nicht erschienen“ markieren?`)) doAction('noshow', { id: a.id });
      });
      actions.appendChild(nsBtn);
    }
    const confirmBtn = document.createElement('button');
    if (a.checked_in) {
      confirmBtn.className = 'btn btn-confirm done btn-small';
      confirmBtn.innerHTML = '<i class="fas fa-check"></i> Bestätigt';
      confirmBtn.disabled = true;
    } else {
      confirmBtn.className = 'btn btn-confirm btn-small';
      confirmBtn.innerHTML = '<i class="fas fa-check"></i> Bestätigen';
      confirmBtn.addEventListener('click', () => doCheckin(a, confirmBtn));
    }
    actions.appendChild(confirmBtn);

    const editBtn = document.createElement('button');
    editBtn.className = 'btn btn-outline btn-small';
    editBtn.textContent = 'Bearbeiten';
    editBtn.addEventListener('click', () => openEdit(a));
    actions.appendChild(editBtn);

    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'btn btn-outline btn-small';
    cancelBtn.textContent = 'Absagen';
    cancelBtn.addEventListener('click', () => {
      if (confirm(`Termin von ${a.vorname} ${a.nachname} um ${a.time} absagen?`)) doAction('cancel', { id: a.id });
    });
    actions.appendChild(cancelBtn);
  }
  const deleteBtn = document.createElement('button');
  deleteBtn.className = 'btn btn-danger btn-small';
  deleteBtn.textContent = 'Löschen';
  deleteBtn.addEventListener('click', () => {
    if (confirm('Diesen Termin endgültig löschen? Der Slot wird wieder frei.')) doAction('delete', { id: a.id });
  });
  actions.appendChild(deleteBtn);
  return el;
}

function buildCancelledList(items) {
  const box = document.createElement('div');
  box.className = 'cancel-list';
  box.innerHTML = '<h3>Abgesagte Termine</h3>';
  items.forEach(a => {
    const row = document.createElement('div');
    row.className = 'cancel-row';
    row.innerHTML = `<b>${escapeHtml(a.time)}</b><span class="n">${escapeHtml(a.vorname + ' ' + a.nachname)}</span><span>${escapeHtml(a.service_label || '')} · ${escapeHtml(a.duration)} Min</span>${a.rejected ? '<span>(abgelehnt)</span>' : ''}`;
    const del = document.createElement('button');
    del.className = 'btn btn-danger btn-small';
    del.textContent = 'Löschen';
    del.addEventListener('click', () => { if (confirm('Diesen Termin endgültig löschen?')) doAction('delete', { id: a.id }); });
    row.appendChild(del);
    box.appendChild(row);
  });
  return box;
}

function loyaltyBadge(a) {
  if (!a.checked_in) return '';
  const v = a.loyalty_visits;
  switch (a.loyalty_event) {
    case 'redeemed':      return `<span class="loy-badge reward">🎁 7 € Rabatt eingelöst</span>`;
    case 'reward_earned': return `<span class="loy-badge reward">🏆 10/10 – nächster Besuch 7 € Rabatt</span>`;
    case 'already_today': return `<span class="loy-badge info">Heute bereits erfasst (${escapeHtml(v)}/10)</span>`;
    case 'stamp':         return `<span class="loy-badge">✓ Treuekarte ${escapeHtml(v)}/10</span>`;
    default:              return `<span class="loy-badge">✓ Besuch erfasst</span>`;
  }
}

// ---- Neue Buchung annehmen / ablehnen -> Kunde bekommt eine SMS ----
function doDecision(a, accept, btn) {
  const q = accept
    ? `Termin von ${a.vorname} ${a.nachname} am ${fmtDate(a.date)} um ${a.time} annehmen?
Der Kunde bekommt eine Bestätigungs-SMS.`
    : `Termin von ${a.vorname} ${a.nachname} am ${fmtDate(a.date)} um ${a.time} ablehnen?
Der Kunde bekommt eine SMS und der Slot wird wieder frei.`;
  if (!confirm(q)) return;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> …';
  const body = new URLSearchParams({ action: accept ? 'accept' : 'reject', id: a.id });
  fetch('admin-api.php', { method: 'POST', body })
    .then(r => r.json())
    .then(d => {
      if (!d.success) {
        alert(d.message || 'Aktion fehlgeschlagen.');
      } else if (d.sms_enabled && !d.sms_sent) {
        alert(`Termin ${accept ? 'angenommen' : 'abgelehnt'}, aber die SMS an ${a.telefon} konnte nicht gesendet werden. Bitte den Kunden anrufen.`);
      } else {
        toast(`Termin ${accept ? 'angenommen' : 'abgelehnt'}${d.sms_sent ? ' – SMS an Kunden gesendet' : ''}`);
      }
      loadList();
    })
    .catch(() => {
      alert('Verbindungsfehler. Bitte erneut versuchen.');
      loadList();
    });
}

// ---- Bestätigen: Besuch auf die Treuekarte buchen ----
function doCheckin(a, btn) {
  if (!confirm(`${a.vorname} ${a.nachname} ist da? Besuch bestätigen und auf der Treuekarte gutschreiben?`)) return;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> …';
  const body = new URLSearchParams({ action: 'checkin', id: a.id });
  fetch('admin-api.php', { method: 'POST', body })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        showLoyaltyResult(a, d);
      } else {
        alert(d.message || 'Aktion fehlgeschlagen.');
      }
      loadList();
    })
    .catch(() => {
      alert('Verbindungsfehler. Bitte erneut versuchen.');
      loadList();
    });
}

function showLoyaltyResult(a, d) {
  const name = `${a.vorname} ${a.nachname}`;
  const reward = d.reward_eur || 7;
  const hl = $('#loyaltyHighlight');
  hl.style.display = 'none';
  let icon = '✓', title = 'Besuch bestätigt', text = '';

  if (d.event === 'redeemed') {
    icon = '🎁'; title = 'Rabatt einlösen!';
    text = `${name} hatte 10 Besuche gesammelt. Ein neuer Zyklus hat begonnen (Besuch 1/10).`;
    hl.textContent = `Bitte jetzt ${reward} € Rabatt auf den Haarschnitt gewähren!`;
    hl.style.display = 'block';
  } else if (d.event === 'reward_earned') {
    icon = '🏆'; title = '10 Besuche erreicht!';
    text = `${name} hat jetzt 10/10 Besuche. Beim nächsten Besuch erhält der Kunde ${reward} € Rabatt auf den Haarschnitt.`;
  } else if (d.event === 'already_today') {
    icon = 'ℹ️'; title = 'Heute bereits erfasst';
    text = `Für ${name} wurde heute schon ein Besuch eingetragen (z. B. per QR-Scan). Es wurde kein weiteres Häkchen vergeben. Stand: ${d.visits}/10.`;
  } else {
    text = `${name}: Besuch ${d.visits} von 10 auf der Treuekarte eingetragen.`;
  }
  if (d.account === 'created') {
    text += ' Für diesen Kunden wurde automatisch eine Treuekarte angelegt (Passwort: letzte 4 Ziffern der Mobilnummer).';
  }

  $('#loyaltyIcon').textContent = icon;
  $('#loyaltyTitle').textContent = title;
  $('#loyaltyText').textContent = text;
  $('#loyaltyModal').classList.add('show');
}
$('#loyaltyOkBtn').addEventListener('click', () => $('#loyaltyModal').classList.remove('show'));

// Automatischer Tageswechsel: um Mitternacht die Liste für den neuen Tag laden
setInterval(() => {
  if (renderedLocalDay && renderedLocalDay !== new Date().toDateString() && $('#app').style.display === 'block') {
    loadList();
  }
}, 60000);

function doAction(action, params) {
  const body = new URLSearchParams({ action, ...params });
  fetch('admin-api.php', { method: 'POST', body }).then(r => r.json()).then(d => {
    if (d.success) { loadList(); } else { alert(d.message || 'Aktion fehlgeschlagen.'); }
  });
}

$('#filterStatus').addEventListener('change', render);
$('#refreshBtn').addEventListener('click', loadList);

// ---- Edit modal (Termin) ----
function populateEditServiceOptions() {
  const sel = $('#editService');
  const current = sel.value;
  sel.innerHTML = '';
  SERVICES.forEach(s => {
    const opt = document.createElement('option');
    opt.value = s.key;
    opt.textContent = `${s.label} (${s.duration} Min)`;
    sel.appendChild(opt);
  });
  if (current) sel.value = current;
}

let editingId = null;
function openEdit(a) {
  editingId = a.id;
  $('#editVorname').value = a.vorname;
  $('#editNachname').value = a.nachname;
  $('#editTelefon').value = a.telefon;
  $('#editDate').value = a.date;
  $('#editTime').value = a.time;
  // Leistung existiert nicht mehr (alter Termin)? Dann bisherige Leistung + Dauer beibehalten.
  const sel = $('#editService');
  sel.querySelectorAll('option[data-keep]').forEach(o => o.remove());
  if (!SERVICES.some(s => s.key === a.service)) {
    const keep = document.createElement('option');
    keep.value = '';
    keep.dataset.keep = '1';
    keep.textContent = `${a.service_label || 'Bisherige Leistung'} (${a.duration} Min, unverändert)`;
    sel.prepend(keep);
  }
  sel.value = SERVICES.some(s => s.key === a.service) ? a.service : '';
  editingAppt = a;
  $('#editErr').textContent = '';
  buildDeltaOptions();
  $('#editModal').classList.add('show');
}

// ---- Dauer anpassen: Minuten zur Grunddauer hinzufügen oder abziehen ----
let editingAppt = null;
// Grunddauer = gespeicherte Dauer des Termins; bei Wechsel der Leistung die Dauer der neuen Leistung
function editBaseDuration() {
  const key = $('#editService').value;
  if (!key || key === editingAppt.service) return Number(editingAppt.duration);
  const svc = SERVICES.find(s => s.key === key);
  return svc ? Number(svc.duration) : Number(editingAppt.duration);
}
function buildDeltaOptions() {
  const base = editBaseDuration();
  const sel = $('#editDelta');
  sel.innerHTML = '';
  const STEPS = { 15: '¼ Stunde', 30: '½ Stunde', 45: '¾ Stunde', 60: '1 Stunde' };
  [-60, -45, -30, -15, 0, 15, 30, 45, 60].forEach(d => {
    if (base + d < 5) return; // Termin darf nicht (fast) auf 0 schrumpfen
    const opt = document.createElement('option');
    opt.value = d;
    opt.textContent = d === 0
      ? `Unverändert (${base} Min)`
      : `${d > 0 ? '+' : '−'} ${STEPS[Math.abs(d)]} → ${base + d} Min`;
    sel.appendChild(opt);
  });
  sel.value = '0';
  updateEditFit();
}
function editNewDuration() { return editBaseDuration() + Number($('#editDelta').value || 0); }
function updateEditFit() {
  if (!editingAppt) return;
  const el = $('#editFit');
  const date = $('#editDate').value, time = $('#editTime').value;
  el.className = 'fit-hint';
  if (!date || !time) { el.textContent = ''; return; }
  const dur = editNewDuration(), s = toMin(time), e = s + dur;
  let text = `Neue Zeit: ${time}–${toTime(e)} Uhr (${dur} Min)`;
  // Freie Zeit um diesen Termin herum (ohne den Termin selbst)
  const others = appointments.filter(x => x.date === date && x.status !== 'cancelled' && x.id !== editingAppt.id);
  const hours = BUSINESS_HOURS ? BUSINESS_HOURS[dayKeyOf(date)] : null;
  const clash = others.find(x => s < toMin(x.time) + Number(x.duration) && e > toMin(x.time));
  if (clash) {
    el.textContent = `${text} – ✗ überschneidet sich mit ${clash.type === 'block' ? 'Blockierung' : (clash.vorname + ' ' + clash.nachname).trim()} (${clash.time}).`;
    el.classList.add('bad');
  } else if (!hours || s < toMin(hours[0]) || e > toMin(hours[1])) {
    el.textContent = `${text} – ✗ außerhalb der Öffnungszeiten.`;
    el.classList.add('bad');
  } else {
    el.textContent = `${text} – ✓ passt.`;
    el.classList.add('ok');
  }
}
$('#editService').addEventListener('change', buildDeltaOptions);
$('#editDelta').addEventListener('change', updateEditFit);
['#editDate', '#editTime'].forEach(sel => $(sel).addEventListener('input', updateEditFit));

$('#editCancelBtn').addEventListener('click', () => $('#editModal').classList.remove('show'));
$('#editSaveBtn').addEventListener('click', () => {
  const params = {
    action: 'edit',
    id: editingId,
    vorname: $('#editVorname').value,
    nachname: $('#editNachname').value,
    telefon: $('#editTelefon').value,
    date: $('#editDate').value,
    time: $('#editTime').value,
    service: $('#editService').value,
  };
  // Dauer nur mitschicken, wenn sie sich tatsächlich ändert (alte Termine bleiben sonst unberührt)
  const dur = editNewDuration();
  if (dur !== Number(editingAppt.duration)) params.duration = dur;
  const btn = $('#editSaveBtn');
  btn.disabled = true;
  fetch('admin-api.php', { method: 'POST', body: new URLSearchParams(params) }).then(r => r.json()).then(d => {
    btn.disabled = false;
    if (d.success) {
      $('#editModal').classList.remove('show');
      toast('Termin gespeichert');
      loadList();
    } else {
      $('#editErr').textContent = d.message || 'Speichern fehlgeschlagen.';
    }
  }).catch(() => { btn.disabled = false; $('#editErr').textContent = 'Verbindungsfehler.'; });
});

// ---- Block modal ----
$('#blockBtn').addEventListener('click', () => {
  $('#blockDate').value = selectedDate || localTodayISO();
  $('#blockModal').classList.add('show');
});
$('#blockCancelBtn').addEventListener('click', () => $('#blockModal').classList.remove('show'));
$('#blockSaveBtn').addEventListener('click', () => {
  doAction('block', {
    date: $('#blockDate').value,
    time: $('#blockTime').value,
    duration: $('#blockDuration').value,
    note: $('#blockNote').value || 'Blockiert',
  });
  $('#blockModal').classList.remove('show');
});

// ---- Manueller Termin (ohne Online-Mindestvorlauf, Überschneidungen prüft der Server) ----
function populateManualServiceOptions() {
  const sel = $('#manService');
  const current = sel.value;
  sel.innerHTML = '';
  [...SERVICES].sort((a, b) => (a.category === b.category ? (a.order || 0) - (b.order || 0) : a.category === 'herr' ? -1 : 1))
    .forEach(s => {
      const opt = document.createElement('option');
      opt.value = s.key;
      opt.textContent = `${s.label} – ${s.duration} Min${s.price !== null && s.price !== undefined ? ' · ' + fmtPrice(s.price) : ''}`;
      sel.appendChild(opt);
    });
  if (current) sel.value = current;
}

function openManual(date, time) {
  $('#manualErr').textContent = '';
  $('#manVorname').value = '';
  $('#manNachname').value = '';
  $('#manTelefon').value = '';
  $('#manDate').value = date || selectedDate || serverToday || localTodayISO();
  $('#manTime').value = time || '';
  updateManualFit();
  $('#manualModal').classList.add('show');
}

// Zeigt an, ob die gewählte Leistung in die freie Lücke ab der gewählten Uhrzeit passt
function updateManualFit() {
  const el = $('#manFit');
  const date = $('#manDate').value, time = $('#manTime').value;
  const svc = SERVICES.find(s => s.key === $('#manService').value);
  el.className = 'fit-hint';
  if (!date || !time || !svc) { el.textContent = ''; return; }
  const s = toMin(time), dur = Number(svc.duration), e = s + dur;
  const active = appointments.filter(a => a.date === date && a.status !== 'cancelled');
  const gap = freeGaps(date, active).find(([gs, ge]) => s >= gs && s < ge);
  if (!gap) {
    el.textContent = '✗ Zu dieser Uhrzeit ist kein freier Platz (belegt, geschlossen oder vorbei).';
    el.classList.add('bad');
  } else if (e > gap[1]) {
    el.textContent = `✗ Passt nicht: freie Lücke ${toTime(gap[0])}–${toTime(gap[1])} (${gap[1] - s} Min ab ${time}), Leistung braucht ${dur} Min.`;
    el.classList.add('bad');
  } else {
    el.textContent = `✓ Passt: ${time}–${toTime(e)} (${dur} Min) in Lücke ${toTime(gap[0])}–${toTime(gap[1])}.`;
    el.classList.add('ok');
  }
}
['#manDate', '#manTime', '#manService'].forEach(sel => {
  $(sel).addEventListener('input', updateManualFit);
  $(sel).addEventListener('change', updateManualFit);
});
$('#manualBtn').addEventListener('click', () => openManual());
$('#manualCancelBtn').addEventListener('click', () => $('#manualModal').classList.remove('show'));
$('#manualSaveBtn').addEventListener('click', () => {
  const svcKey = $('#manService').value;
  const body = new URLSearchParams({
    action: 'manual_book',
    vorname: $('#manVorname').value.trim(),
    nachname: $('#manNachname').value.trim(),
    telefon: $('#manTelefon').value.trim(),
    type: svcKey === 'kind' ? 'kind' : 'mann',
    service: svcKey,
    date: $('#manDate').value,
    time: $('#manTime').value,
  });
  const btn = $('#manualSaveBtn');
  btn.disabled = true;
  fetch('admin-api.php', { method: 'POST', body }).then(r => r.json()).then(d => {
    btn.disabled = false;
    if (d.success) {
      $('#manualModal').classList.remove('show');
      toast(`Termin eingetragen: ${d.appointment.time}–${d.appointment.end} Uhr` + (d.telegram === 'sent' ? ' · Telegram gesendet' : ''));
      loadList();
    } else {
      $('#manualErr').textContent = d.message || 'Speichern fehlgeschlagen.';
    }
  }).catch(() => { btn.disabled = false; $('#manualErr').textContent = 'Verbindungsfehler.'; });
});

// ============================================================
// Archiv (Suche läuft auf dem Server, 50 Einträge pro Seite)
// ============================================================
let archivePage = 1;
let archiveTimer = null;
const ARCHIVE_STATUS = { completed: 'Erledigt', cancelled: 'Abgesagt', no_show: 'Nicht erschienen', past_open: 'Vergangen' };

function loadArchive(page) {
  archivePage = page || 1;
  const params = new URLSearchParams({
    action: 'archive', page: archivePage,
    name: $('#arcName').value.trim(), phone: $('#arcPhone').value.trim(),
    date: $('#arcDate').value, from: $('#arcFrom').value, to: $('#arcTo').value, status: $('#arcStatus').value,
  });
  fetch(`admin-api.php?${params.toString()}`).then(r => r.json()).then(d => {
    if (d.error) { showLogin(); return; }
    if (!d.success) { $('#archiveMeta').textContent = d.message || 'Fehler beim Laden.'; return; }
    renderArchive(d);
  });
}

function renderArchive(d) {
  $('#archiveMeta').textContent = `${d.total} Termin${d.total === 1 ? '' : 'e'} gefunden`;
  const list = $('#archiveList');
  if (!d.items.length) {
    list.innerHTML = '<div class="empty-state">Keine Termine für diese Suche.</div>';
    $('#archivePager').innerHTML = '';
    return;
  }
  const rows = d.items.map(a => {
    let st = ARCHIVE_STATUS[a.archive_status] || a.archive_status;
    if (a.archive_status === 'cancelled' && a.rejected) st = 'Abgelehnt';
    const price = a.has_price ? (a.price === null ? 'n. A.' : fmtPrice(a.price)) : '–';
    return `<tr>
      <td class="c-time">${escapeHtml(fmtDate(a.date))}<small>${escapeHtml(a.time)} – ${escapeHtml(a.end)}</small></td>
      <td class="c-name"><div class="n">${escapeHtml((a.vorname + ' ' + a.nachname).trim() || '–')}</div><div class="p">${escapeHtml(a.telefon || '')}</div></td>
      <td class="c-svc">${escapeHtml(a.service_label)}${a.type === 'kind' ? ' (Kind)' : ''}<small>${a.source === 'admin' ? 'manuell eingetragen' : 'online gebucht'}</small></td>
      <td class="c-dur">${escapeHtml(a.duration)} Min</td>
      <td class="c-dur">${escapeHtml(price)}</td>
      <td><span class="status-tag ${escapeHtml(a.archive_status)}">${escapeHtml(st)}</span></td>
    </tr>`;
  }).join('');
  list.innerHTML = `<div class="book-wrap"><table class="book">
    <thead><tr><th>Datum / Zeit</th><th>Kunde</th><th>Leistung</th><th>Dauer</th><th>Preis</th><th>Status</th></tr></thead>
    <tbody>${rows}</tbody></table></div>`;
  const pager = $('#archivePager');
  pager.innerHTML = '';
  if (d.pages > 1) {
    const prev = document.createElement('button');
    prev.className = 'btn btn-outline btn-small';
    prev.textContent = '‹ Zurück';
    prev.disabled = d.page <= 1;
    prev.addEventListener('click', () => loadArchive(d.page - 1));
    const next = document.createElement('button');
    next.className = 'btn btn-outline btn-small';
    next.textContent = 'Weiter ›';
    next.disabled = d.page >= d.pages;
    next.addEventListener('click', () => loadArchive(d.page + 1));
    const info = document.createElement('span');
    info.textContent = `Seite ${d.page} von ${d.pages}`;
    pager.append(prev, info, next);
  }
}

['#arcName', '#arcPhone'].forEach(sel => $(sel).addEventListener('input', () => {
  clearTimeout(archiveTimer);
  archiveTimer = setTimeout(() => loadArchive(1), 350);
}));
['#arcDate', '#arcFrom', '#arcTo', '#arcStatus'].forEach(sel => $(sel).addEventListener('change', () => loadArchive(1)));
$('#archiveResetBtn').addEventListener('click', () => {
  ['#arcName', '#arcPhone', '#arcDate', '#arcFrom', '#arcTo', '#arcStatus'].forEach(sel => { $(sel).value = ''; });
  loadArchive(1);
});
document.querySelector('.tab-btn[data-tab="archiv"]').addEventListener('click', () => loadArchive(archivePage));

// ============================================================
// Einstellungen: Mindestvorlauf + Telegram
// ============================================================
function loadSettings() {
  fetch('admin-api.php?action=settings_get').then(r => r.json()).then(d => {
    if (d.success) $('#leadMinutes').value = d.booking_lead_minutes;
  });
}
$('#leadSaveBtn').addEventListener('click', () => {
  $('#leadErr').textContent = '';
  const body = new URLSearchParams({ action: 'settings_save', booking_lead_minutes: $('#leadMinutes').value.trim() });
  fetch('admin-api.php', { method: 'POST', body }).then(r => r.json()).then(d => {
    if (d.success) toast(`Mindestvorlauf gespeichert: ${d.booking_lead_minutes} Min`);
    else $('#leadErr').textContent = d.message || 'Speichern fehlgeschlagen.';
  });
});

let tgPollTimer = null;
function renderTelegram(d) {
  const linked = !!d.linked;
  $('#tgStatus').classList.toggle('on', linked);
  $('#tgStatusText').textContent = linked ? 'Telegram verbunden' : (d.pending ? 'Warte auf Bestätigung in Telegram…' : 'Telegram nicht verbunden');
  let hint;
  if (!d.configured) {
    hint = 'Telegram ist auf dem Server noch nicht eingerichtet (Bot-Token fehlt). Buchungen und SMS funktionieren weiterhin normal.';
  } else if (linked) {
    hint = `Verbunden mit ${d.chat_name || 'Telegram'}${d.linked_at ? ' seit ' + new Date(d.linked_at).toLocaleString('de-DE') : ''}. Sie erhalten eine Nachricht bei jeder neuen Online-Buchung und jedem manuell eingetragenen Termin.`;
  } else if (d.pending) {
    hint = 'Telegram wurde geöffnet. Bitte dort im Chat mit dem Bot auf „Starten“ tippen. Der Link ist 15 Minuten gültig.';
  } else {
    hint = 'Noch nicht verbunden – Buchungen und SMS funktionieren trotzdem. Zum Verbinden auf „Telegram verbinden“ klicken und im Bot-Chat auf „Starten“ tippen.';
  }
  $('#tgHint').textContent = hint;
  $('#tgConnectBtn').style.display = d.configured ? '' : 'none';
  $('#tgConnectBtn').innerHTML = linked ? '<i class="fab fa-telegram"></i> Neu verbinden' : '<i class="fab fa-telegram"></i> Telegram verbinden';
  $('#tgTestBtn').style.display = linked ? '' : 'none';
  $('#tgDisconnectBtn').style.display = linked ? '' : 'none';
  clearTimeout(tgPollTimer);
  if (d.pending && !linked) tgPollTimer = setTimeout(loadTelegramStatus, 3000);
  if (d.poll === 'linked') toast('Telegram verbunden ✅');
  if (d.poll === 'expired') toast('Telegram-Link abgelaufen. Bitte erneut verbinden.');
}
function loadTelegramStatus() {
  fetch('admin-api.php?action=telegram_status').then(r => r.json()).then(d => { if (d.success) renderTelegram(d); });
}
$('#tgConnectBtn').addEventListener('click', () => {
  // Fenster sofort öffnen (Popup-Blocker), Link danach setzen
  const win = window.open('about:blank', '_blank');
  fetch('admin-api.php', { method: 'POST', body: new URLSearchParams({ action: 'telegram_connect' }) })
    .then(r => r.json()).then(d => {
      if (!d.ok) { if (win) win.close(); alert(d.message || 'Verbinden nicht möglich.'); return; }
      if (win) win.location.href = d.url; else window.location.href = d.url;
      loadTelegramStatus();
    });
});
$('#tgDisconnectBtn').addEventListener('click', () => {
  if (!confirm('Telegram-Verbindung trennen? Danach kommen keine Telegram-Nachrichten mehr (SMS bleibt unverändert).')) return;
  fetch('admin-api.php', { method: 'POST', body: new URLSearchParams({ action: 'telegram_disconnect' }) })
    .then(r => r.json()).then(d => { renderTelegram(d); toast('Telegram getrennt'); });
});
$('#tgTestBtn').addEventListener('click', () => {
  fetch('admin-api.php', { method: 'POST', body: new URLSearchParams({ action: 'telegram_test' }) })
    .then(r => r.json()).then(d => toast(d.message || (d.success ? 'Gesendet' : 'Fehler')));
});

// ============================================================
// Leistungen
// ============================================================
function loadServices() {
  fetch('admin-api.php?action=services_list').then(r => r.json()).then(d => {
    if (!d.success) return;
    const herr = (d.services || []).filter(s => s.category === 'herr').sort((a, b) => (a.order || 0) - (b.order || 0));
    const kind = (d.services || []).filter(s => s.key === 'kind');
    renderServices(herr.concat(kind));
  });
}

function renderServices(services) {
  const wrap = $('#servicesList');
  wrap.innerHTML = '';
  if (services.length === 0) {
    wrap.innerHTML = '<div class="empty-state">Noch keine Leistungen vorhanden.</div>';
    return;
  }
  services.forEach(s => {
    const card = document.createElement('div');
    card.className = 'svc-card';
    card.innerHTML = `
      <div class="row">
        <div>
          <div class="name">${escapeHtml(s.label)}</div>
          <div class="meta">${escapeHtml(s.duration)} Minuten${s.key === 'kind' ? ' · fester Eintrag (nur Dauer änderbar)' : ''}</div>
          ${s.description ? `<div class="desc">${escapeHtml(s.description)}</div>` : ''}
        </div>
        <div class="price">${fmtPrice(s.price)}</div>
      </div>
      <div class="svc-actions" style="margin-top:12px;"></div>
    `;
    const actions = card.querySelector('.svc-actions');
    const editBtn = document.createElement('button');
    editBtn.className = 'btn btn-outline btn-small';
    editBtn.textContent = 'Bearbeiten';
    editBtn.addEventListener('click', () => openServiceModal(s));
    actions.appendChild(editBtn);
    if (s.key === 'kind') { wrap.appendChild(card); return; }
    const delBtn = document.createElement('button');
    delBtn.className = 'btn btn-danger btn-small';
    delBtn.textContent = 'Löschen';
    delBtn.addEventListener('click', () => {
      if (confirm(`„${s.label}" wirklich löschen?`)) {
        const body = new URLSearchParams({ action: 'services_delete', key: s.key });
        fetch('admin-api.php', { method: 'POST', body }).then(r => r.json()).then(d2 => {
          if (d2.success) { loadServices(); loadList(); } else { alert(d2.message || 'Löschen nicht möglich (mind. eine Leistung muss bestehen bleiben).'); }
        });
      }
    });
    actions.appendChild(delBtn);
    wrap.appendChild(card);
  });
}

function openServiceModal(s) {
  $('#serviceModalErr').textContent = '';
  if (s) {
    $('#serviceModalTitle').textContent = 'Leistung bearbeiten';
    $('#svcKey').value = s.key;
    $('#svcLabel').value = s.label;
    $('#svcPrice').value = s.price;
    $('#svcDuration').value = s.duration;
    $('#svcDescription').value = s.description || '';
  } else {
    $('#serviceModalTitle').textContent = 'Neue Leistung';
    $('#svcKey').value = '';
    $('#svcLabel').value = '';
    $('#svcPrice').value = '';
    $('#svcDuration').value = '30';
    $('#svcDescription').value = '';
  }
  const isKind = !!s && s.key === 'kind';
  document.querySelectorAll('#serviceModal .svc-full').forEach(el => { el.style.display = isKind ? 'none' : ''; });
  if (isKind) $('#serviceModalTitle').textContent = 'Kinderhaarschnitt – Dauer';
  $('#serviceModal').classList.add('show');
}
$('#addServiceBtn').addEventListener('click', () => openServiceModal(null));
$('#serviceCancelBtn').addEventListener('click', () => $('#serviceModal').classList.remove('show'));
$('#serviceSaveBtn').addEventListener('click', () => {
  const body = new URLSearchParams({
    action: 'services_save',
    key: $('#svcKey').value,
    label: $('#svcLabel').value.trim(),
    price: $('#svcPrice').value,
    duration: $('#svcDuration').value,
    description: $('#svcDescription').value.trim(),
  });
  fetch('admin-api.php', { method: 'POST', body }).then(r => r.json()).then(d => {
    if (d.success) {
      $('#serviceModal').classList.remove('show');
      loadServices();
      loadList();
    } else {
      $('#serviceModalErr').textContent = d.message || 'Speichern fehlgeschlagen.';
    }
  });
});

// ============================================================
// FAQ
// ============================================================
function loadFaqs() {
  fetch('admin-api.php?action=faqs_list').then(r => r.json()).then(d => {
    if (!d.success) return;
    const faqs = (d.faqs || []).sort((a, b) => (a.order || 0) - (b.order || 0));
    renderFaqs(faqs);
  });
}

function renderFaqs(faqs) {
  const wrap = $('#faqsList');
  wrap.innerHTML = '';
  if (faqs.length === 0) {
    wrap.innerHTML = '<div class="empty-state">Noch keine Fragen vorhanden.</div>';
    return;
  }
  faqs.forEach(f => {
    const card = document.createElement('div');
    card.className = 'faq-card';
    card.innerHTML = `
      <div class="row">
        <div style="flex:1;">
          <div class="q">${escapeHtml(f.question)}</div>
          <div class="a">${escapeHtml(f.answer)}</div>
        </div>
        <div class="faq-actions"></div>
      </div>
    `;
    const actions = card.querySelector('.faq-actions');
    const editBtn = document.createElement('button');
    editBtn.className = 'btn btn-outline btn-small';
    editBtn.textContent = 'Bearbeiten';
    editBtn.addEventListener('click', () => openFaqModal(f));
    actions.appendChild(editBtn);
    const delBtn = document.createElement('button');
    delBtn.className = 'btn btn-danger btn-small';
    delBtn.textContent = 'Löschen';
    delBtn.addEventListener('click', () => {
      if (confirm('Diese Frage wirklich löschen?')) {
        const body = new URLSearchParams({ action: 'faqs_delete', id: f.id });
        fetch('admin-api.php', { method: 'POST', body }).then(r => r.json()).then(d2 => {
          if (d2.success) loadFaqs(); else alert(d2.message || 'Löschen fehlgeschlagen.');
        });
      }
    });
    actions.appendChild(delBtn);
    wrap.appendChild(card);
  });
}

function openFaqModal(f) {
  $('#faqModalErr').textContent = '';
  if (f) {
    $('#faqModalTitle').textContent = 'Frage bearbeiten';
    $('#faqId').value = f.id;
    $('#faqQuestion').value = f.question;
    $('#faqAnswer').value = f.answer;
  } else {
    $('#faqModalTitle').textContent = 'Neue Frage';
    $('#faqId').value = '';
    $('#faqQuestion').value = '';
    $('#faqAnswer').value = '';
  }
  $('#faqModal').classList.add('show');
}
$('#addFaqBtn').addEventListener('click', () => openFaqModal(null));
$('#faqCancelBtn').addEventListener('click', () => $('#faqModal').classList.remove('show'));
$('#faqSaveBtn').addEventListener('click', () => {
  const body = new URLSearchParams({
    action: 'faqs_save',
    id: $('#faqId').value,
    question: $('#faqQuestion').value.trim(),
    answer: $('#faqAnswer').value.trim(),
  });
  fetch('admin-api.php', { method: 'POST', body }).then(r => r.json()).then(d => {
    if (d.success) {
      $('#faqModal').classList.remove('show');
      loadFaqs();
    } else {
      $('#faqModalErr').textContent = d.message || 'Speichern fehlgeschlagen.';
    }
  });
});

// ============================================================
// Nachrichten
// ============================================================
function loadMessages() {
  fetch('admin-api.php?action=messages_list').then(r => r.json()).then(d => {
    if (!d.success) return;
    renderMessages(d.messages || []);
  });
}

function renderMessages(messages) {
  const wrap = $('#messagesList');
  wrap.innerHTML = '';
  if (messages.length === 0) {
    wrap.innerHTML = '<div class="empty-state">Noch keine Nachrichten.</div>';
    return;
  }
  messages.forEach(m => {
    const card = document.createElement('div');
    card.className = 'msg-card' + (m.status === 'open' ? ' open' : '');
    const when = m.created_at ? new Date(m.created_at).toLocaleString('de-DE') : '';
    const statusLabel = m.status === 'replied' ? 'Beantwortet' : (m.status === 'seen' ? 'Gesehen' : 'Neu');
    card.innerHTML = `
      <div class="top">
        <div>
          <div class="who">${escapeHtml(m.vorname)} ${escapeHtml(m.nachname)}</div>
          <div class="phone">${escapeHtml(m.telefon)}</div>
          ${m.context ? `<div class="context">${escapeHtml(m.context)}</div>` : ''}
        </div>
        <div style="text-align:end;">
          <span class="status-pill ${m.status}">${statusLabel}</span>
          <div class="when">${when}</div>
        </div>
      </div>
      <div class="note">${escapeHtml(m.note)}</div>
      <div class="admin-reply-wrap"></div>
    `;
    const replyWrap = card.querySelector('.admin-reply-wrap');
    if (m.status === 'replied') {
      const box = document.createElement('div');
      box.className = 'admin-reply';
      box.innerHTML = `<b>Ihre Antwort:</b> ${escapeHtml(m.admin_reply)}`;
      replyWrap.appendChild(box);
    } else {
      const box = document.createElement('div');
      box.className = 'reply-box';
      const ta = document.createElement('textarea');
      ta.placeholder = 'Antwort an den Kunden schreiben...';
      const btn = document.createElement('button');
      btn.className = 'btn btn-primary btn-small';
      btn.textContent = 'Antwort senden';
      btn.addEventListener('click', () => {
        const reply = ta.value.trim();
        if (!reply) { alert('Bitte eine Antwort eingeben.'); return; }
        const body = new URLSearchParams({ action: 'messages_reply', id: m.id, reply });
        fetch('admin-api.php', { method: 'POST', body }).then(r => r.json()).then(d => {
          if (d.success) { loadMessages(); loadList(); } else { alert(d.message || 'Senden fehlgeschlagen.'); }
        });
      });
      box.appendChild(ta);
      box.appendChild(btn);
      replyWrap.appendChild(box);

      if (m.status === 'open') {
        const seenBody = new URLSearchParams({ action: 'messages_seen', id: m.id });
        fetch('admin-api.php', { method: 'POST', body: seenBody }).then(() => loadList());
      }
    }
    wrap.appendChild(card);
  });
}

$('#refreshMsgsBtn').addEventListener('click', loadMessages);

// ============================================================
// App-Chats (Konversationen aus der Kunden-App)
// ============================================================
let chatConvs = [];
let activeChatId = null;
let chatOutbox = null; // { convId, clientId, text } – gleiche ID bei Wiederholung = keine Doppelnachricht

function setChatBadge(n) {
  const b = $('#chatTabBadge');
  if (n > 0) { b.textContent = n; b.style.display = 'inline-block'; } else { b.style.display = 'none'; }
}

function newClientId() {
  if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
    const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
  });
}

function fmtDateTime(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  const today = new Date();
  const sameDay = d.toDateString() === today.toDateString();
  return sameDay ? d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
                 : d.toLocaleString('de-DE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function chatTabVisible() {
  return document.visibilityState === 'visible' && $('#tab-appchats').classList.contains('active');
}

function loadConversations() {
  fetch('admin-api.php?action=conv_list').then(r => r.json()).then(d => {
    if (!d.success) return;
    chatConvs = d.conversations || [];
    setChatBadge(d.unread || 0);
    renderChatList();
    if (activeChatId) loadChat(activeChatId);
  }).catch(() => {});
}

function renderChatList() {
  const wrap = $('#chatList');
  wrap.innerHTML = '';
  if (chatConvs.length === 0) {
    wrap.innerHTML = '<div class="empty-state" style="padding:30px 10px;">Noch keine App-Nachrichten.</div>';
    return;
  }
  chatConvs.forEach(c => {
    const btn = document.createElement('button');
    btn.className = 'chat-item' + (c.id === activeChatId ? ' active' : '') + (c.unread > 0 ? ' has-unread' : '');
    const last = c.last_message || {};
    btn.innerHTML = `
      <div class="row1"><span class="nm">${escapeHtml(c.customer.name || c.customer.phone || 'Kunde')}</span>
        <span>${c.unread > 0 ? `<span class="unread">${c.unread}</span> ` : ''}<span class="tm">${escapeHtml(fmtDateTime(c.updated_at))}</span></span></div>
      <div class="sj">${escapeHtml(c.subject)}</div>
      <div class="pv">${last.sender === 'staff' ? 'Sie: ' : ''}${escapeHtml(last.text || '')}</div>`;
    btn.addEventListener('click', () => openChat(c.id));
    wrap.appendChild(btn);
  });
}

function openChat(id) {
  if (activeChatId !== id) {
    activeChatId = id;
    $('#chatThread').innerHTML = '<div class="chat-empty">Wird geladen…</div>';
  }
  $('#chatLayout').classList.add('thread-open');
  renderChatList();
  loadChat(id);
}

function loadChat(id) {
  fetch(`admin-api.php?action=conv_get&id=${encodeURIComponent(id)}`).then(r => r.json()).then(d => {
    if (id !== activeChatId) return;
    if (!d.success) { $('#chatThread').innerHTML = `<div class="chat-empty">${escapeHtml(d.message || 'Fehler beim Laden.')}</div>`; return; }
    renderChat(d.conversation, d.messages || []);
    if (d.conversation.unread > 0 && chatTabVisible()) {
      fetch('admin-api.php', { method: 'POST', body: new URLSearchParams({ action: 'conv_read', id }) });
    }
  }).catch(() => {
    if (id === activeChatId && !$('#chatMsgs')) $('#chatThread').innerHTML = '<div class="chat-empty">Verbindung fehlgeschlagen. Bitte „Aktualisieren“ klicken.</div>';
  });
}

const APPT_STATUS_LABEL = { pending: 'wartet auf Annahme', confirmed: 'bestätigt', cancelled: 'abgesagt', rejected: 'abgelehnt',
  rescheduled: 'verschoben', no_show: 'nicht erschienen', completed: 'erledigt', past: 'vergangen' };

function pushLabel(p) {
  switch (p) {
    case 'sent': return '';
    case 'failed': return 'Push fehlgeschlagen – Kunde sieht die Antwort beim Öffnen der App';
    case 'no_device': case 'not_configured': case 'disabled': return 'ohne Push – sichtbar beim Öffnen der App';
    default: return '';
  }
}

function renderChat(conv, messages) {
  const thread = $('#chatThread');
  let msgsEl = $('#chatMsgs');
  const fresh = !msgsEl || thread.dataset.conv !== conv.id;
  if (fresh) {
    thread.dataset.conv = conv.id;
    thread.innerHTML = `
      <div class="chat-head" id="chatHead"></div>
      <div class="chat-msgs" id="chatMsgs"></div>
      <div class="modal-err" id="chatErr" style="padding:0 12px;margin:6px 0 0;"></div>
      <div class="chat-compose">
        <textarea id="chatInput" placeholder="Antwort schreiben…" maxlength="2000"></textarea>
        <button class="btn btn-primary" id="chatSendBtn"><i class="fas fa-paper-plane"></i> Senden</button>
      </div>`;
    msgsEl = $('#chatMsgs');
    $('#chatSendBtn').addEventListener('click', sendChatReply);
    $('#chatInput').addEventListener('keydown', e => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) sendChatReply(); });
  }
  const c = conv.customer || {};
  const a = conv.appointment;
  $('#chatHead').innerHTML = `
    <button class="btn btn-outline btn-small chat-back" id="chatBackBtn"><i class="fas fa-arrow-left"></i> Alle Chats</button>
    <div class="nm">${escapeHtml(c.name || 'Kunde')}</div>
    <div class="meta">${escapeHtml(c.phone || '')} · ${escapeHtml(conv.subject)}${c.has_device === false ? ' · kein Push-Gerät registriert' : ''}</div>
    ${a ? `<div class="chat-appt"><i class="fas fa-calendar"></i> ${escapeHtml(fmtDate(a.date))}, ${escapeHtml(a.time)}–${escapeHtml(a.end)} Uhr · ${escapeHtml(a.service_label)} · ${escapeHtml(a.name)} · <b>${escapeHtml(APPT_STATUS_LABEL[a.display_status] || a.status)}</b></div>` : ''}`;
  $('#chatBackBtn').addEventListener('click', () => { $('#chatLayout').classList.remove('thread-open'); activeChatId = null; renderChatList(); });

  const nearBottom = msgsEl.scrollHeight - msgsEl.scrollTop - msgsEl.clientHeight < 80;
  msgsEl.innerHTML = '';
  if (messages.length === 0) msgsEl.innerHTML = '<div class="chat-empty">Noch keine Nachrichten.</div>';
  messages.forEach(m => {
    const b = document.createElement('div');
    b.className = 'bubble ' + m.sender;
    const meta = [fmtDateTime(m.created_at)];
    if (m.sender === 'staff') meta.push(m.status === 'read' ? 'gelesen' : 'gesendet');
    const pl = m.sender === 'staff' ? pushLabel(m.push_status) : '';
    b.innerHTML = `${escapeHtml(m.text)}<div class="bm">${escapeHtml(meta.join(' · '))}</div>${pl ? `<div class="bm ${m.push_status === 'failed' ? 'err' : ''}">${escapeHtml(pl)}</div>` : ''}`;
    msgsEl.appendChild(b);
  });
  if (fresh || nearBottom) msgsEl.scrollTop = msgsEl.scrollHeight;
}

function sendChatReply() {
  const input = $('#chatInput');
  const btn = $('#chatSendBtn');
  const text = input.value.trim();
  $('#chatErr').textContent = '';
  if (!text || !activeChatId) return;
  // Gleicher Text in derselben Konversation nach Fehler = gleiche ID (Server erkennt Wiederholung)
  if (!chatOutbox || chatOutbox.convId !== activeChatId || chatOutbox.text !== text) {
    chatOutbox = { convId: activeChatId, clientId: newClientId(), text };
  }
  btn.disabled = true;
  const body = new URLSearchParams({ action: 'conv_reply', id: activeChatId, text, client_msg_id: chatOutbox.clientId });
  fetch('admin-api.php', { method: 'POST', body }).then(r => r.json()).then(d => {
    if (d.success) {
      chatOutbox = null;
      input.value = '';
      if (d.push === 'failed') toast('Antwort gespeichert, aber die Push-Benachrichtigung ist fehlgeschlagen.');
      loadConversations();
    } else {
      $('#chatErr').textContent = d.message || 'Senden fehlgeschlagen.';
    }
  }).catch(() => {
    $('#chatErr').textContent = 'Keine Verbindung. Bitte erneut auf „Senden“ klicken – die Nachricht wird nicht doppelt gesendet.';
  }).finally(() => { btn.disabled = false; });
}

$('#refreshChatsBtn').addEventListener('click', loadConversations);
document.querySelector('.tab-btn[data-tab="appchats"]').addEventListener('click', () => { if (activeChatId) loadChat(activeChatId); });
document.addEventListener('visibilitychange', () => { if (chatTabVisible() && activeChatId) loadChat(activeChatId); });

// ============================================================
// Einstellungen: Passwort ändern
// ============================================================
$('#pwSaveBtn').addEventListener('click', () => {
  const pwErr = $('#pwErr');
  const pwOk = $('#pwOk');
  pwErr.textContent = '';
  pwOk.textContent = '';

  const current = $('#pwCurrent').value;
  const next = $('#pwNew').value;
  const confirmPw = $('#pwNewConfirm').value;

  if (!current || !next || !confirmPw) {
    pwErr.textContent = 'Bitte alle Felder ausfüllen.';
    return;
  }

  const body = new URLSearchParams({ action: 'change_password', current_password: current, new_password: next, new_password_confirm: confirmPw });
  fetch('admin-api.php', { method: 'POST', body }).then(r => r.json()).then(d => {
    if (d.success) {
      pwOk.textContent = d.message || 'Passwort erfolgreich geändert.';
      $('#pwCurrent').value = '';
      $('#pwNew').value = '';
      $('#pwNewConfirm').value = '';
    } else {
      pwErr.textContent = d.message || 'Speichern fehlgeschlagen.';
    }
  });
});

checkLogin();
</script>
</body>
</html>
