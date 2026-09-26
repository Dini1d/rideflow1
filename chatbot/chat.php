<?php
/**
 * RideFlow Chatbot API
 * POST /chatbot/chat.php
 * Uses Anthropic Claude with FAQ fallback
 */
if (session_status() === PHP_SESSION_NONE) session_start();
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['reply' => 'Method not allowed.']); exit;
}

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$message = trim($body['message'] ?? '');
$history = is_array($body['history'] ?? null) ? $body['history'] : [];

if (!$message) { echo json_encode(['reply' => 'No message provided.']); exit; }

// ── Try FAQ match first (rule-based) ─────────────────────────
$faqs = db_all("SELECT * FROM chatbot_faq WHERE is_active=1 ORDER BY sort_order");
$msgL = strtolower($message);
$faqMatch = null;
foreach ($faqs as $faq) {
    $keywords = array_filter(array_map('trim', explode(',', strtolower($faq['keywords'] ?? ''))));
    foreach ($keywords as $kw) {
        if ($kw && strpos($msgL, $kw) !== false) {
            $faqMatch = $faq;
            break 2;
        }
    }
}
if ($faqMatch) {
    // Log and return FAQ answer
    $uid = $_SESSION['uid'] ?? null;
    $sid = session_id() ?: 'anon_'.uniqid();
    try {
        db_exec("INSERT INTO chatbot_messages(user_id,session_id,role,message) VALUES(?,?,'user',?)", [$uid,$sid,$message]);
        db_exec("INSERT INTO chatbot_messages(user_id,session_id,role,message) VALUES(?,?,'assistant',?)", [$uid,$sid,$faqMatch['answer']]);
    } catch (Exception $e) {}
    echo json_encode(['reply' => $faqMatch['answer'], 'source' => 'faq']);
    exit;
}

// ── Live route context ────────────────────────────────────────
$routeCtx = '';
try {
    $routes = db_all("
        SELECT r.origin, r.destination, t.type_name, r.estimated_duration,
               MIN(s.fare) mf, MAX(s.fare) xf,
               COUNT(s.id) trips
        FROM routes r
        JOIN transport_types t ON t.id=r.type_id
        LEFT JOIN schedules s ON s.route_id=r.id AND s.departure_date>=CURRENT_DATE AND s.status='scheduled'
        WHERE r.status='active'
        GROUP BY r.id ORDER BY t.type_name, r.origin LIMIT 20");
    $lines = [];
    foreach ($routes as $r) {
        $fare = $r['mf'] ? "Rs.{$r['mf']}–Rs.{$r['xf']}" : 'fare varies';
        $lines[] = "• {$r['type_name']}: {$r['origin']} → {$r['destination']} ({$r['estimated_duration']}, {$fare}, {$r['trips']} upcoming trips)";
    }
    $routeCtx = implode("\n", $lines);
} catch (Exception $e) { $routeCtx = 'Route data unavailable.'; }

// User context
$userCtx = '';
if (!empty($_SESSION['uid'])) {
    try {
        $bk = db_row("SELECT b.booking_ref, b.booking_status, r.origin, r.destination, s.departure_date
            FROM bookings b JOIN schedules s ON s.id=b.schedule_id JOIN routes r ON r.id=s.route_id
            WHERE b.user_id=? ORDER BY b.created_at DESC LIMIT 1", [$_SESSION['uid']]);
        if ($bk) {
            $userCtx = "User's latest booking: {$bk['booking_ref']} — {$bk['origin']}→{$bk['destination']} on {$bk['departure_date']} ({$bk['booking_status']})";
        }
    } catch (Exception $e) {}
}

// ── Try AI (Anthropic Claude) ─────────────────────────────────
$apiKey = ANTHROPIC_API_KEY ?: setting('anthropic_api_key');

if (!$apiKey) {
    // Fallback: generic helpful response
    $reply = "I'm sorry, I couldn't find a specific answer for that. For help with bookings, routes, and payments, please visit our website or call +94 11 234 5678. You can also use the Contact page to send us a message.";
    echo json_encode(['reply' => $reply, 'source' => 'fallback']);
    exit;
}

$system = "You are RideFlow Assistant — a helpful AI for Sri Lanka's transport booking platform.

You help with: finding routes, booking tickets, payment methods, OTP issues, cancellations, and general travel advice in Sri Lanka.

LIVE ROUTES RIGHT NOW:
$routeCtx

$userCtx

RULES:
- Be concise (under 100 words unless detail is needed), warm, and helpful
- Use bullet points for steps or lists
- For bookings, direct users to the Search page
- Payment methods: Card (Stripe), PayPal, eZ Cash, Genie, Bank Transfer, Cash on Board
- OTP issues: check phone format (+94...), request new code, check spam
- You cannot make bookings — guide users to do it on the site
- If unsure, say so and offer to connect with support";

$messages = [];
foreach (array_slice($history, -8) as $h) {
    if (isset($h['role'], $h['content']) && in_array($h['role'], ['user','assistant'], true)) {
        $messages[] = ['role' => $h['role'], 'content' => (string)$h['content']];
    }
}
$messages[] = ['role' => 'user', 'content' => $message];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'model'      => 'claude-sonnet-4-20250514',
        'max_tokens' => 450,
        'system'     => $system,
        'messages'   => $messages,
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: '.$apiKey,
        'anthropic-version: 2023-06-01',
    ],
]);
$res  = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($err || $http !== 200) {
    echo json_encode(['reply' => 'I\'m having trouble connecting. Please try again or contact support.', 'source' => 'error']);
    exit;
}

$data  = json_decode($res, true);
$reply = '';
$source = 'ai';

if (json_last_error() !== JSON_ERROR_NONE) {
    $reply = 'I\'m having trouble connecting. Please try again or contact support.';
    $source = 'error';
} elseif (isset($data['content'][0]['text']) && $data['content'][0]['text'] !== '') {
    $reply = (string)$data['content'][0]['text'];
} elseif (isset($data['completion']) && $data['completion'] !== '') {
    $reply = (string)$data['completion'];
} elseif (isset($data['output_text']) && $data['output_text'] !== '') {
    $reply = (string)$data['output_text'];
} else {
    $reply = 'Sorry, I could not generate a response.';
    $source = 'error';
}

// Log
$uid = $_SESSION['uid'] ?? null;
$sid = session_id() ?: 'anon_'.uniqid();
try {
    db_exec("INSERT INTO chatbot_messages(user_id,session_id,role,message) VALUES(?,?,'user',?)",      [$uid,$sid,$message]);
    db_exec("INSERT INTO chatbot_messages(user_id,session_id,role,message) VALUES(?,?,'assistant',?)", [$uid,$sid,$reply]);
} catch (Exception $e) {}

echo json_encode(['reply' => $reply, 'source' => $source]);
