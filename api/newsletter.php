<?php
// api/newsletter.php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'message'=>'POST required']); exit; }

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim(strtolower($body['email'] ?? $_POST['email'] ?? ''));
$name  = trim($body['name'] ?? $_POST['name'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok'=>false,'message'=>'Invalid email address.']); exit;
}

$exists = db_val("SELECT id FROM subscriptions WHERE email=?", [$email]);
if ($exists) {
    // Reactivate if previously unsubscribed
    db_exec("UPDATE subscriptions SET is_active=1 WHERE email=?", [$email]);
    echo json_encode(['ok'=>true,'message'=>'You are already subscribed!']); exit;
}

db_insert("INSERT INTO subscriptions(email,name) VALUES(?,?)", [$email, $name ?: null]);
echo json_encode(['ok'=>true,'message'=>'Subscribed successfully!']);
