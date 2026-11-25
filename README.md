# Wolf Friseur — Salon Website & Online Booking

![PHP](https://img.shields.io/badge/PHP-8-777BB4?logo=php&logoColor=white) ![Booking](https://img.shields.io/badge/Booking-online-success)

> Hair salon website with online appointment booking, SMS notifications and an admin dashboard.

## Overview
A production project built and deployed by **Mahmoud Hasan**. This repository holds the source code only. The client's secrets,
databases, uploaded files and personal data are not included; where the code needs them, they are replaced with placeholders.

**Tech stack:** PHP 8 · JSON storage · Vanilla JS · HTML/CSS

## Features
- Responsive one-page salon website: services, price list, team, reviews, contact
- Online booking that follows opening hours, slot length, lead time and the booking window
- Customer login with one-time codes sent by SMS, plus rate limiting and sessions
- SMS notifications to salon and customers (seven.io or Twilio), optional Telegram alerts
- Admin dashboard: appointments, services, FAQs, settings
- Cron job that deletes old appointments; sitemap and robots.txt
- Impressum and Datenschutz pages

## Project structure
```
index.html          public website
termin.php          booking page
booking-api.php     booking API
app-api.php / app-lib.php   customer accounts, OTP, sessions
admin-dashboard.php / admin-api.php
config.php          settings (no secrets in repo)
cron-cleanup.php    scheduled cleanup
```

## Getting started
Copy the files to a PHP 8 host. Fill in `config.php` (SMS provider key, owner phone, admin password hash) and add the `.htaccess` rules from `htaccess-schutz.txt` to protect the data folders.

## Security & privacy
- No credentials in the repository. API keys, passwords and database settings are placeholders (`CHANGE_ME`, `YOUR_…`, `your_db_password`).
- Runtime data (customers, messages, appointments, logs, uploads) is left out.
- Personal names, phone numbers and addresses in the default content are replaced with sample data (e.g. Max Mustermann, +49 151 00000000, Musterstraße 1).

## Author
**Mahmoud Hasan** · [GitHub](https://github.com/MahmoudHasan2)
