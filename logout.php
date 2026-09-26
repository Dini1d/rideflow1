<?php
// logout.php
define('RF_ROOT', __DIR__);
require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
logout_user();
redirect(SITE_URL.'/login.php');
