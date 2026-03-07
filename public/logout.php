<?php

require_once dirname(__DIR__) . '/bootstrap.php';
App\Auth::init();
App\Auth::logout();
header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/');
exit;
