# RideFlow — Complete Setup Guide

## 🚀 Quick Start (5 Minutes)

```
1. Copy /rideflow/ folder to htdocs (XAMPP) or www (WAMP)
2. Import sql/rideflow.sql into MySQL
3. Edit config/config.php with your settings
4. Visit http://localhost/rideflow/
5. Login: admin@rideflow.lk / password
```

---

## 📋 Requirements

| Requirement | Minimum Version |
|------------|----------------|
| PHP | 7.4+ (8.1+ recommended) |
| MySQL | 5.7+ or MariaDB 10.3+ |
| Apache | 2.4+ (with mod_rewrite) |
| PHP Extensions | PDO, PDO_MySQL (MySQL) or PDO_PGSQL (Supabase), curl, json, session, mbstring |

---

## ⚙️ Step-by-Step Setup

### Step 1 — Install XAMPP / WAMP

**XAMPP (Recommended for Windows/Mac):**
1. Download from https://www.apachefriends.org
2. Install and start Apache + MySQL from XAMPP Control Panel
3. Place the `rideflow` folder inside `C:\xampp\htdocs\`

**WAMP:**
1. Download from https://www.wampserver.com
2. Start WAMP (green icon in taskbar)
3. Place `rideflow` inside `C:\wamp64\www\`

**Linux:**
```bash
sudo apt install apache2 mysql-server php php-mysql php-curl php-mbstring
sudo cp -r rideflow /var/www/html/
sudo a2enmod rewrite
sudo systemctl restart apache2
```

---

### Step 2 — Create the Database

**Option A — phpMyAdmin:**
1. Open http://localhost/phpmyadmin
2. Click "New" → Database name: `rideflow` → Charset: `utf8mb4_unicode_ci` → Create
3. Click the `rideflow` database → Import tab
4. Choose file: `sql/rideflow.sql` → Go

**Option B — Command Line:**
```bash
mysql -u root -p
CREATE DATABASE rideflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;
mysql -u root -p rideflow < sql/rideflow.sql
```

---

### Step 3 — Configure the Application

Edit **`config/config.php`**:

```php
/* ── Database ── */
define('DB_HOST', 'localhost');
define('DB_NAME', 'rideflow');
define('DB_USER', 'root');          // Your MySQL username
define('DB_PASS', '');              // Your MySQL password (empty for XAMPP default)

/* ── Application URL ── */
define('SITE_URL', 'http://localhost/rideflow');
// For production: 'https://yourdomain.com'

/* ── Google OAuth (optional) ── */
define('GOOGLE_CLIENT_ID',     'your-id.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'GOCSPX-your-secret');

/* ── AI Chatbot (optional) ── */
define('ANTHROPIC_API_KEY', 'sk-ant-...');

/* ── Email (optional — set MAIL_ENABLED=true after configuring) ── */
define('MAIL_ENABLED', false);
define('MAIL_HOST',    'smtp.gmail.com');
define('MAIL_USER',    'your@gmail.com');
define('MAIL_PASS',    'your-app-password');  // Google App Password

/* ── Stripe (optional) ── */
define('STRIPE_PK', 'pk_test_...');
define('STRIPE_SK', 'sk_test_...');
```

### Supabase PostgreSQL connection

The application can open a Supabase PostgreSQL connection through PDO. In
`config/config.php`, use the Supabase connection pooler URL from **Project
Settings → Database → Connection string → URI**:

```php
define('DB_DRIVER', 'pgsql');
define('SUPABASE_DB_URL', 'postgresql://postgres.PROJECT_REF:PASSWORD@aws-0-REGION.pooler.supabase.com:6543/postgres');
```

Keep the password URL-encoded if it contains characters such as `@`, `:`, or
`#`. Alternatively set `RIDEFLOW_DB_DRIVER=pgsql` and `SUPABASE_DB_URL` as
Apache/PHP environment variables. Enable PHP's `pdo_pgsql` extension and
restart Apache before testing the site.

Import `sql/rideflow.sql` in the Supabase SQL Editor before switching the
application to `DB_DRIVER = 'pgsql'`. The PostgreSQL connection mode skips the
MySQL-only automatic migrations. A few admin queries still need PostgreSQL
syntax updates, including `ON DUPLICATE KEY UPDATE` and `DATE_FORMAT`, before
those admin screens are used with Supabase.

---

### Step 4 — Enable Apache mod_rewrite

**XAMPP:**
1. Open `C:\xampp\apache\conf\httpd.conf`
2. Find and uncomment: `LoadModule rewrite_module modules/mod_rewrite.so`
3. Find `<Directory "C:/xampp/htdocs">` and change `AllowOverride None` to `AllowOverride All`
4. Restart Apache

**Ubuntu/Linux:**
```bash
sudo a2enmod rewrite
sudo nano /etc/apache2/apache2.conf
# Change AllowOverride None to AllowOverride All for /var/www/html
sudo systemctl restart apache2
```

---

### Step 5 — Test the Installation

Open your browser:

| URL | Description |
|-----|-------------|
| http://localhost/rideflow/ | Homepage |
| http://localhost/rideflow/login.php | Sign in |
| http://localhost/rideflow/admin/dashboard.php | Admin panel |
| http://localhost/rideflow/api/v1/ | REST API health check |

**Demo Accounts:**

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@rideflow.lk | password |
| Customer | john@example.com | password |
| Driver | driver@rideflow.lk | password |

---

## 🔑 API Integration Guide

### Google Sign-In Setup

1. Go to https://console.cloud.google.com
2. Create a project → Enable "People API"
3. APIs & Services → Credentials → Create OAuth 2.0 Client
4. Application type: **Web application**
5. Add Authorized Redirect URIs:
   ```
   http://localhost/rideflow/auth/google_callback.php
   https://yourdomain.com/auth/google_callback.php
   ```
6. Copy Client ID and Client Secret
7. Add to `config/config.php`:
   ```php
   define('GOOGLE_CLIENT_ID',     'xxx.apps.googleusercontent.com');
   define('GOOGLE_CLIENT_SECRET', 'GOCSPX-xxx');
   ```
   OR via Admin Panel → Integrations → Google Sign-In

### AI Chatbot Setup (Anthropic Claude)

1. Sign up at https://console.anthropic.com
2. Go to API Keys → Create Key
3. Copy the `sk-ant-...` key
4. Add to config or Admin → Integrations → AI Chatbot
   ```php
   define('ANTHROPIC_API_KEY', 'sk-ant-api03-xxx');
   ```
5. The chatbot appears as a floating widget on all pages

**How it works:**
- First checks FAQ database for keyword matches (instant, no API cost)
- Falls back to Claude claude-sonnet-4 for complex questions
- Knows live routes, fares, and logged-in user's bookings
- Admin can manage FAQ at Admin → Chatbot FAQ

### Stripe Payment Setup

1. Create account at https://stripe.com
2. Dashboard → Developers → API Keys
3. Copy Publishable key (`pk_test_...`) and Secret key (`sk_test_...`)
4. Add to config or Admin → Integrations → Stripe
5. For live payments, use live keys and enable webhooks

### Email (SMTP) Setup with Gmail

1. Enable 2-Factor Authentication on your Gmail account
2. Go to https://myaccount.google.com/apppasswords
3. Create App Password for "Mail"
4. Use in config:
   ```php
   define('MAIL_ENABLED', true);
   define('MAIL_HOST',    'smtp.gmail.com');
   define('MAIL_PORT',    587);
   define('MAIL_USER',    'your@gmail.com');
   define('MAIL_PASS',    'xxxx xxxx xxxx xxxx');  // 16-char app password
   ```
5. Install PHPMailer (optional — improves reliability):
   ```bash
   composer require phpmailer/phpmailer
   ```

---

## 📱 Mobile REST API

**Base URL:** `http://localhost/rideflow/api/v1/`

**Authentication:** `Authorization: Bearer <token>`

### Quick Test

```bash
# Health check
curl http://localhost/rideflow/api/v1/

# Login
curl -X POST http://localhost/rideflow/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"password"}'

# Search trips
curl "http://localhost/rideflow/api/v1/search?from=Colombo&to=Kandy&date=$(date +%Y-%m-%d)"

# Get my bookings (with token)
curl http://localhost/rideflow/api/v1/bookings \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### All Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | /auth/register | No | Register + OTP |
| POST | /auth/login | No | Email/phone login |
| POST | /auth/social | No | Google/social login |
| POST | /auth/logout | Yes | Logout |
| GET | /routes | No | List routes |
| GET | /routes/{id} | No | Route + stops |
| GET | /schedules | No | List schedules |
| GET | /search | No | Search trips |
| GET | /bookings | Yes | My bookings |
| POST | /bookings | Yes | Create booking |
| GET | /bookings/{id} | Yes | Booking detail |
| DELETE | /bookings/{id} | Yes | Cancel booking |
| POST | /payments | Yes | Pay for booking |
| GET | /profile | Yes | Get profile |
| PUT | /profile | Yes | Update profile |
| POST | /chat | Yes* | AI assistant |
| GET | /live | No | Live stats |
| POST | /feedback | Yes | Submit review |

---

## 🗂️ File Structure

```
rideflow/
├── .htaccess                 ← Apache rewrite rules & security
├── index.php                 ← Homepage
├── login.php                 ← Sign in (Email/Phone/OTP/Google)
├── register.php              ← Registration
├── forgot_password.php       ← Password reset request
├── reset_password.php        ← Password reset form
├── verify_email.php          ← Email verification
├── otp_verify.php            ← OTP code verification
├── search.php                ← Trip search
├── book.php                  ← Booking form
├── contact.php               ← Contact page
├── logout.php                ← Sign out
│
├── config/
│   ├── config.php            ← ⭐ EDIT THIS FIRST — all settings
│   └── database.php          ← PDO connection + helpers
│
├── includes/
│   ├── helpers.php           ← Utility functions, CSRF, sessions
│   ├── auth.php              ← Login, register, OTP functions
│   ├── header.php            ← Public page header
│   └── footer.php            ← Footer + chatbot widget
│
├── assets/
│   ├── css/style.css         ← Complete design system
│   └── js/app.js             ← Chatbot, UI interactions
│
├── auth/
│   ├── google_login.php      ← Initiates Google OAuth
│   ├── google_callback.php   ← Handles Google response
│   └── google_process.php    ← Creates/logs in user
│
├── payment/
│   └── checkout.php          ← Payment gateway selection
│
├── user/
│   ├── dashboard.php         ← Customer dashboard
│   ├── bookings.php          ← Booking history
│   ├── profile.php           ← Edit profile
│   └── ticket.php            ← E-ticket with QR code
│
├── driver/
│   └── dashboard.php         ← Driver trip management
│
├── admin/
│   ├── dashboard.php         ← Analytics & revenue
│   ├── bookings.php          ← Manage all bookings
│   ├── users.php             ← User management
│   ├── vehicles.php          ← Fleet management
│   ├── routes.php            ← Route management
│   ├── schedules.php         ← Schedule management
│   ├── reports.php           ← Revenue reports
│   ├── messages.php          ← Contact messages
│   ├── newsletter.php        ← Send newsletters
│   ├── chatbot.php           ← FAQ management
│   ├── settings.php          ← General settings
│   └── integrations.php      ← API keys & integrations
│
├── chatbot/
│   └── chat.php              ← AI chat API endpoint
│
├── api/
│   ├── v1/index.php          ← Mobile REST API
│   ├── newsletter.php        ← Newsletter subscribe
│   └── notifications.php     ← Live notification count
│
├── mail/
│   └── mailer.php            ← Email functions
│
└── sql/
    └── rideflow.sql          ← Complete database schema + data
```

---

## 🎨 UI Design

**Theme:** Dark editorial — deep slate backgrounds with electric orange accents

**Fonts:** Fraunces (display/headings) + DM Sans (body) from Google Fonts

**Colors:**
- Background: `#0c0d10` (deep slate)
- Accent: `#ff6a00` (electric orange)
- Success: `#22c55e` | Error: `#ef4444` | Info: `#38bdf8`
- Text: `#f0ede6` (warm chalk)

**Key Pages:**
- **Homepage** — Hero with animated mesh + search box + live schedule cards
- **Search** — Sort by price/time, seat count selector
- **Booking** — Multi-step flow with seat picker and gateway selector
- **Ticket** — Printable e-ticket with QR code from Google Charts API
- **Dashboard** — KPI cards, revenue chart (Chart.js), booking table
- **Login** — Split-screen with animated hero + Google Sign-In button

---

## 🔒 Security Features

- PDO prepared statements — no SQL injection
- `htmlspecialchars()` on all output — XSS protection
- CSRF tokens on all POST forms
- bcrypt password hashing (cost=12)
- Session regeneration on login
- HTTP-only, SameSite=Lax cookies
- Rate limiting on admin login (5 attempts → 15 min lockout)
- `.htaccess` blocks direct access to `/includes/`, `/config/`, `/mail/`
- Security headers: X-Frame-Options, X-Content-Type-Options, XSS Protection

---

## 🐛 Troubleshooting

**"No database connection" error:**
- Check MySQL is running in XAMPP/WAMP
- Verify DB_USER and DB_PASS in config.php

**"500 Internal Server Error":**
- Enable display_errors temporarily: set `APP_ENV = 'development'` in config.php
- Check Apache error log: `C:\xampp\apache\logs\error.log`

**"Page Not Found" for /admin/dashboard:**
- mod_rewrite not enabled — follow Step 4 above
- AllowOverride must be set to All

**Google Sign-In not working:**
- Check redirect URI matches exactly (http vs https, trailing slash)
- Google Client ID must be in config.php or Admin → Integrations

**OTP not received:**
- In demo mode, the code appears on screen (orange box)
- For real SMS, set SMS_PROVIDER in config.php and add API keys

**Email not sending:**
- Set `MAIL_ENABLED = true` in config.php
- Use Gmail App Password (not your regular Gmail password)
- Install PHPMailer: `composer require phpmailer/phpmailer`

---

## 🚢 Production Deployment

```bash
# 1. Upload files to server
scp -r rideflow/ user@yourserver.com:/var/www/html/

# 2. Set correct permissions
chmod -R 755 /var/www/html/rideflow
chmod 644 /var/www/html/rideflow/.htaccess

# 3. Import database
mysql -u dbuser -p rideflow < sql/rideflow.sql

# 4. Update config.php
define('SITE_URL', 'https://yourdomain.com');
define('APP_ENV', 'production');
define('MAIL_ENABLED', true);

# 5. Enable HTTPS — uncomment in .htaccess:
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# 6. Point Nginx/Apache to rideflow/
# 7. Set up SSL certificate (Let's Encrypt)
```

---

## 📞 Support

- **Demo:** All passwords are `password`
- **Admin:** admin@rideflow.lk / password
- **Customer:** john@example.com / password
- **SMS Demo:** OTP code shown on screen (no real SMS sent)
- **AI Demo:** Set ANTHROPIC_API_KEY to enable Claude-powered chatbot
