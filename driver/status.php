<?php
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';
require_driver();

$scheduleId = (int)($_GET['schedule'] ?? 0);
$set        = $_GET['set'] ?? '';
$allowed    = ['departed','arrived'];

if ($scheduleId && in_array($set, $allowed, true)) {
    // Ownership check — a driver may only update their own assigned trips.
    $owns = db_val("SELECT s.id FROM schedules s JOIN drivers d ON d.vehicle_id=s.vehicle_id WHERE s.id=? AND d.user_id=?", [$scheduleId, current_uid()]);
    if ($owns) {
        db_exec("UPDATE schedules SET status=? WHERE id=?", [$set, $scheduleId]);
        flash('ok', 'Trip status updated to '.$set.'.');
    } else {
        flash('bad', 'You are not assigned to that trip.');
    }
} else {
    flash('bad', 'Invalid request.');
}
redirect(SITE_URL.'/driver/dashboard.php');
