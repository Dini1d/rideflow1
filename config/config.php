<?php
/**
 * RideFlow — Main Configuration
 * Edit this file with your server settings before deploying.
 */

$envFile = dirname(__DIR__).'/.env';
if (is_readable($envFile)) {
	foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $envLine) {
		$envLine = trim($envLine);
		if ($envLine === '' || str_starts_with($envLine, '#') || !str_contains($envLine, '=')) {
			continue;
		}
		[$envName, $envValue] = explode('=', $envLine, 2);
		$envName = trim($envName);
		$envValue = trim($envValue);
		if ($envName !== '' && getenv($envName) === false) {
			putenv($envName.'='.$envValue);
		}
	}
}

/* ── Database ─────────────────────────────────── */
define('DB_HOST', getenv('SUPABASE_DB_HOST') ?: 'aws-0-ap-southeast-1.pooler.supabase.com');
define('DB_PORT', getenv('SUPABASE_DB_PORT') ?: '5432');
define('DB_NAME', getenv('SUPABASE_DB_NAME') ?: 'postgres');
define('DB_USER', getenv('SUPABASE_DB_USER') ?: 'postgres.zqjrznzgaedkpbqcpeol');
define('DB_PASS', getenv('SUPABASE_DB_PASSWORD') ?: '');
define('DB_CHAR', 'utf8mb4');
define('DB_DRIVER', getenv('RIDEFLOW_DB_DRIVER') ?: 'pgsql');
define('SUPABASE_DB_URL', getenv('SUPABASE_DB_URL') ?: 'postgresql://postgres.zqjrznzgaedkpbqcpeol:[Basi@1234tha]@aws-0-ap-southeast-1.pooler.supabase.com:5432/postgres');

/* ── Application ──────────────────────────────── */
define('SITE_URL',  'http://localhost/rideflow');  // NO trailing slash
define('SITE_NAME', 'RideFlow');
define('APP_ENV',   'development');   // 'development' | 'production'
define('TIMEZONE',  'Asia/Colombo');

/* ── Session ──────────────────────────────────── */
define('SESSION_NAME',     'RF_SESSION');
define('SESSION_LIFETIME', 7200);  // 2 hours

/* ── Google OAuth ─────────────────────────────── */
define('GOOGLE_CLIENT_ID',     '');  // your-id.apps.googleusercontent.com
define('GOOGLE_CLIENT_SECRET', '');  // GOCSPX-...
define('GOOGLE_REDIRECT',      SITE_URL.'/auth/google_callback.php');

/* ── Email (PHPMailer/SMTP) ───────────────────── */
define('MAIL_HOST',      'smtp.gmail.com');
define('MAIL_PORT',      587);
define('MAIL_USER',      '');   // your Gmail or SMTP user
define('MAIL_PASS',      '');   // App password
define('MAIL_FROM',      'noreply@rideflow.lk');
define('MAIL_FROM_NAME', 'RideFlow');
define('MAIL_ENABLED',   false);  // set true when SMTP is configured

/* ── Stripe ───────────────────────────────────── */
define('STRIPE_PK', '');  // pk_test_...
define('STRIPE_SK', '');  // sk_test_...

/* ── AI Chatbot (Anthropic Claude) ───────────── */
define('ANTHROPIC_API_KEY', '');   // sk-ant-...

/* ── SMS ──────────────────────────────────────── */
define('SMS_PROVIDER', 'demo');  // 'demo' | 'twilio' | 'esms' | 'dialog'
define('TWILIO_SID',   '');
define('TWILIO_TOKEN', '');
define('TWILIO_FROM',  '');
define('ESMS_API_KEY', '');

/* ── Security ─────────────────────────────────── */
define('BCRYPT_COST', 12);
define('CSRF_TOKEN_LEN', 32);
define('OTP_EXPIRY_MIN', 10);

/* ── Pagination ───────────────────────────────── */
define('PER_PAGE', 20);

/* ── Bootstrap app ───────────────────────────── */
date_default_timezone_set(TIMEZONE);
error_reporting(APP_ENV === 'development' ? E_ALL : 0);
ini_set('display_errors', APP_ENV === 'development' ? 1 : 0);
