# RideFlow — What was added / fixed in this pass

## Starting point
You uploaded the top-level pages only (`login.php`, `register.php`, `book.php`,
`search.php`, `index.php`, `contact.php`, `forgot_password.php`,
`reset_password.php`, `verify_email.php`, `otp_verify.php`, `logout.php`,
`_auth_check.php`, plus `.htaccess`, `robots.txt`, and the two markdown docs).
None of the files they `require` — `config/`, `includes/`, `mail/`, the
dashboards, `payment/checkout.php`, CSS, or `sql/rideflow.sql` — were present,
so nothing could actually run. Your `login.php` itself was already a complete,
polished implementation (role tabs, email/phone/OTP methods, throttling,
CSRF, show/hide password, remember-me, loading state) — it didn't need a
rebuild, just the supporting code around it.

## Files added (new)
- `config/config.php`, `config/database.php` — PDO connection + `db_row/db_all/db_val/db_insert/db_exec`, `setting()`
- `includes/helpers.php` — secure session bootstrap, CSRF, flash messages, `e()`, login throttling, phone/status/booking-ref helpers
- `includes/auth.php` — all `auth_login_*()` functions, registration, forgot/reset password, email verification, OTP, session login/logout with **session regeneration on login** and a hashed remember-me token, and `require_customer()/require_driver()/require_admin()` role guards
- `includes/header.php`, `includes/footer.php` — shared chrome, nav (role-aware), flash rendering, chat widget stub
- `assets/css/style.css`, `assets/css/auth.css` — the full design system your pages already reference (dark editorial navy/orange theme, cards, buttons, form fields, trip cards, the login split-screen)
- `mail/mailer.php` — verification/reset emails (demo mode logs instead of sending until `MAIL_ENABLED` is set)
- `sql/rideflow.sql` — complete schema + demo seed data (3 demo accounts, routes, vehicles, schedules)
- `user/dashboard.php`, `user/ticket.php` — customer dashboard + QR e-ticket
- `driver/dashboard.php`, `driver/manifest.php`, `driver/status.php` — driver trip list, passenger manifest, departure/arrival status update (ownership-checked)
- `admin/dashboard.php`, `admin/bookings.php`, `admin/users.php`, `admin/vehicles.php`, `admin/routes.php`, `admin/reports.php` — KPI dashboard + management pages
- `payment/checkout.php` — demo checkout that confirms a booking (no real gateway wired in — see below)
- `chatbot/chat.php` — keyword FAQ endpoint (Claude API call left as a clear extension point)

## Bug fixed in your existing `book.php`
The booking `INSERT` had its column list and value list out of sync:
```
INSERT INTO bookings(...,passenger_name,passenger_phone,passenger_email,booking_status,payment_status)
VALUES(?,?,?,?,?,'pending','unpaid',?,?,?)
```
Because `'pending'`/`'unpaid'` were placed where `passenger_name`/`passenger_phone`
should have been, every real booking would have stored the phone number in
`booking_status`, the email in `payment_status`, and the name/phone fields
would be junk. Fixed to `VALUES(?,?,?,?,?,?,?,?,'pending','unpaid')` so the
literals land in the right columns.

## Security checklist against your requirements
- ✅ PDO + prepared statements everywhere (no string-built SQL)
- ✅ `password_hash()`/`password_verify()` (bcrypt, cost 12)
- ✅ Session ID regenerated on every successful login (`session_regenerate_id(true)`)
- ✅ Role re-verified from the database on every login path — the selected tab is never trusted
- ✅ CSRF token on every POST form, checked via `csrf_guard()`
- ✅ Login throttling (5 attempts / 15 min lockout) per role scope
- ✅ `require_customer/driver/admin()` guards block cross-role dashboard access with a 403
- ✅ httponly, SameSite=Lax session cookies; remember-me stored as a hashed validator, not a raw token
- ✅ `.htaccess` blocks direct access to `config/`, `includes/`, `mail/`

## What's still a stub / out of scope for this pass
- **Payments**: `payment/checkout.php` marks a booking paid immediately — no real Stripe/PayPal/eZ Cash/Genie integration. Wiring a live gateway needs API keys and webhook endpoints I can add on request.
- **Google Sign-In**: the login/register pages already hide the Google button unless `GOOGLE_CLIENT_ID` is set — `auth/google_login.php`, `google_callback.php` aren't implemented yet.
- **AI chatbot**: FAQ keyword matching works; the Claude API fallback is stubbed but not wired to a live key.
- **REST API** (`/api/v1/`): not implemented — only referenced in `.htaccess`/docs.
- **Admin pages** (`bookings.php`, `users.php`, `vehicles.php`, `routes.php`, `reports.php`) are functional but intentionally simple — no pagination/filtering yet.

## Testing
I don't have PHP or MySQL in this sandbox, so I couldn't execute `_auth_check.php`
or run `php -l` myself. On your WAMP/XAMPP box:
1. Import `sql/rideflow.sql`, set your DB credentials in `config/config.php`
2. `php _auth_check.php` from the project root — it exercises exactly the
   role-verification and cross-role-rejection scenarios you asked for, against
   the `auth.php` I wrote
3. `php -l` each modified/added file to confirm no syntax errors
4. Manually try: correct login per role, wrong password, admin tab with
   customer credentials, direct URL access to `/admin/dashboard.php` while
   logged in as customer (should 403), logout, then back-button to a
   dashboard (should redirect to login)
