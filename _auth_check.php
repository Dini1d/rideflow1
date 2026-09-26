<?php
define('RF_ROOT', __DIR__);
require 'config/database.php';
require 'includes/helpers.php';
require 'includes/auth.php';

function expect($ok, $msg) {
    echo ($ok ? 'PASS' : 'FAIL') . ' — ' . $msg . PHP_EOL;
    if (!$ok) exit(1);
}

$c = auth_login_email('john@example.com', 'password', 'customer');
expect(!empty($c['ok']) && ($c['user']['role'] ?? '') === 'customer', 'customer email login');

$bad = auth_login_email('john@example.com', 'wrong', 'customer');
expect(empty($bad['ok']), 'customer wrong password rejected: '.$bad['msg']);

$cross = auth_login_email('john@example.com', 'password', 'admin');
expect(empty($cross['ok']), 'customer cannot use admin portal: '.$cross['msg']);

$drvTab = auth_login_email('john@example.com', 'password', 'driver');
expect(empty($drvTab['ok']), 'customer cannot use driver portal');

$d = auth_login_driver('driver@rideflow.lk', 'password');
expect(!empty($d['ok']) && ($d['user']['role'] ?? '') === 'driver', 'driver email login');

$dx = auth_login_driver('john@example.com', 'password');
expect(empty($dx['ok']), 'customer credentials fail driver login');

$a = auth_login_admin('admin@rideflow.lk', 'password');
expect(!empty($a['ok']) && ($a['user']['role'] ?? '') === 'admin', 'admin email login');

$ax = auth_login_admin('john@example.com', 'password');
expect(empty($ax['ok']), 'customer credentials fail admin login');

$ph = auth_login_phone('+94779876543', 'password');
expect(!empty($ph['ok']), 'customer phone login');

expect(str_contains(auth_dashboard_url(['role'=>'customer']), '/user/dashboard.php'), 'customer dashboard URL');
expect(str_contains(auth_dashboard_url(['role'=>'driver']), '/driver/dashboard.php'), 'driver dashboard URL');
expect(str_contains(auth_dashboard_url(['role'=>'admin']), '/admin/dashboard.php'), 'admin dashboard URL');

echo "ALL AUTH CHECKS PASSED\n";
