<?php

// debug mode on
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 30 minutes
set_time_limit(60 * 30);
ini_set('memory_limit', '1500M');

define('ROOT_DIR', dirname(__DIR__));

if (!is_dir(ROOT_DIR . '/vendor')) {
    die('Update dependencies via composer');
}

require ROOT_DIR . '/vendor/autoload.php';

require 'config.php';