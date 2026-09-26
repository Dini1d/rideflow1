<?php
// api/notifications.php — Live notification count for navbar badge
define('RF_ROOT', dirname(__DIR__));
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';

header('Content-Type: application/json');
if (!is_logged_in()) { echo json_encode(['ok'=>true,'data'=>['count'=>0]]); exit; }

$count = db_table_exists('notifications')
	? (int) db_val("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0", [current_uid()])
	: 0;
echo json_encode(['ok'=>true,'data'=>['count'=>$count]]);
