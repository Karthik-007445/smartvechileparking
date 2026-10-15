# ParkSmart — Smart Vehicle Parking System

A complete parking management web app built with **PHP** (frontend + server-side logic) and **MongoDB** (database).
Drivers register, add vehicles, pick a slot from a live map, park, and pay on exit. Admins manage slots, bookings, users and see revenue analytics.

## Features

**For drivers**
- Register / login (hashed passwords, CSRF protection, login throttling)
- Garage: add and remove cars, bikes and EVs
- Live colour-coded slot map (auto-refreshes every 10 s)
- Book a slot (only slots matching your vehicle type are offered; slots are claimed atomically, so no double booking)
- Live running timer and fee on active parkings
- One-click "Pay & exit" with automatic billing (per started hour, free grace period, GST)
- Printable receipts with a QR code, full booking history, profile and password change

**For admins**
- Dashboard with revenue (7 days), slot status, vehicle mix charts and KPIs
- Manage slots: bulk-add, change hourly rate, maintenance mode, delete
- All bookings with search and force check-out
- User list with promote / demote

## Requirements

| Item | Version |
|------|---------|
| PHP | 8.1 or newer |
| PHP extension `mongodb` | installed and enabled |
| PHP extension `mbstring` | enabled (default in most builds) |
| Composer | any recent version |
| MongoDB server | 5.0+ (local install **or** a free MongoDB Atlas cluster) |

## Setup (5 steps)

1. **Install the MongoDB PHP extension**
   - *Windows / XAMPP:* download the matching `php_mongodb.dll` from the PECL page for your PHP version (match the version, thread-safety and x64/x86), copy it into `xampp/php/ext`, add `extension=mongodb` to `php.ini`, restart Apache.
   - *Linux:* `sudo pecl install mongodb`, then add `extension=mongodb.so` to `php.ini`.
   - *macOS (Homebrew):* `pecl install mongodb`.
2. **Install dependencies** — open a terminal in this folder and run:
   ```
   composer install
   ```
3. **Start MongoDB** (`mongod` / the MongoDB Windows service). Using Atlas instead? Put your connection string in `config/config.php` (`mongo_uri`) or set the `MONGO_URI` environment variable.
4. **Check your environment** — open `setup/check.php` in the browser. Every row should be green.
5. **Create demo data** — open `setup/seed.php` (or run `php setup/seed.php`). This creates indexes, 28 parking slots, the admin and demo accounts, and sample booking history.

### Running the app

*Quick way (no Apache needed):*
```
php -S localhost:8000
```
then visit http://localhost:8000

*XAMPP / WAMP:* copy this folder to `htdocs/smart-parking` and visit http://localhost/smart-parking

## Demo accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@parksmart.com | Admin@123 |
| Driver | demo@parksmart.com | Demo@1234 |

Change these passwords (and delete the `setup/` folder) before putting the site anywhere public.

## Configuration

Everything lives in `config/config.php`: MongoDB URI and database name, currency symbol, tax percentage, grace minutes, default hourly rates and timezone.

## Project structure

```
smart-parking/
├── index.php              Landing page
├── login.php / register.php / logout.php
├── dashboard.php          Driver dashboard + live slot map
├── book.php               Choose vehicle + slot, confirm booking
├── vehicles.php           Manage vehicles
├── bookings.php           Booking history
├── checkout.php           Pay & exit (POST handler)
├── receipt.php            Printable receipt with QR
├── profile.php
├── admin/                 index (analytics), slots, bookings, users
├── api/slots.php          JSON feed used by the live map
├── includes/              bootstrap, helpers, layout
├── config/config.php
├── assets/css, assets/js  Styling and behaviour
└── setup/                 check.php, seed.php
```

## MongoDB collections

| Collection | Purpose | Key fields |
|-----------|---------|-----------|
| `users` | Accounts | name, email (unique), phone, password (hash), role, created_at, last_login |
| `vehicles` | Driver vehicles | user_id, plate, type (car/bike/ev), model, color |
| `slots` | Parking slots | code (unique), floor, type, rate, status (available/occupied/maintenance), current_booking |
| `bookings` | Parking sessions | booking_no, user_id, vehicle_id, slot_id, entry_time, exit_time, status, duration_minutes, subtotal, tax, total, payment_method |

## Billing rule

- Leaving within the grace period (default 10 min) is free.
- Otherwise billed per started hour at the slot's hourly rate, plus tax (default 18% GST).
- Example: 1 h 05 min in a ₹40/hr car slot → 2 hours = ₹80 + ₹14.40 tax = **₹94.40**.

Payment is simulated (UPI / Card / Cash / Wallet selection is recorded, but no real gateway is called).

## Troubleshooting

| Problem | Fix |
|---------|-----|
| "Dependencies are missing" | Run `composer install` in the project folder |
| `Class "MongoDB\Client" not found` | The `mongodb` extension is not enabled. Check `setup/check.php`, and make sure the CLI and Apache use the same `php.ini` |
| "Cannot reach MongoDB" | Start the MongoDB service and verify `mongo_uri` in `config/config.php` |
| CSS/links broken under a sub-folder | Set `'base_path' => '/smart-parking'` in `config/config.php` |
| Want error details | Set `'debug' => true` in `config/config.php` while developing |

## Tech stack

PHP 8, MongoDB (`mongodb/mongodb` library), Bootstrap 5, Bootstrap Icons, Chart.js, QRCode.js, vanilla JavaScript.
