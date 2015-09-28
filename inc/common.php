<?php

// debug mode on
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 30 minutes
set_time_limit(60 * 30);
ini_set('memory_limit', '1500M');

date_default_timezone_set('EST');

if (!defined('CKANMNGR_ROOT_DIR')) {
    define('CKANMNGR_ROOT_DIR', dirname(__DIR__));
}
if (!defined('CKANMNGR_DATA_DIR')) {
    define('CKANMNGR_DATA_DIR', CKANMNGR_ROOT_DIR . '/data');
}
if (!defined('CKANMNGR_BACKUP_DIR')) {
    define('CKANMNGR_BACKUP_DIR', CKANMNGR_ROOT_DIR . '/backup');
}
if (!defined('CKANMNGR_RESULTS_DIR')) {
    define('CKANMNGR_RESULTS_DIR', CKANMNGR_ROOT_DIR . '/results');
}

define('CKANMNGR_TIMER_START', time());

if (is_dir(CKANMNGR_ROOT_DIR . '/vendor')) {
    require CKANMNGR_ROOT_DIR . '/vendor/autoload.php';
}

if (!class_exists('\CKAN\CkanClient')) {
    throw new Exception('Install dependencies via composer');
}

require 'config.php';

function timer()
{
    $finish = time();
    $clr = new \Colors\Color();
    $minutes_spent = floor((($finish - CKANMNGR_TIMER_START) / 60));
    $seconds_spent = (($finish - CKANMNGR_TIMER_START) % 60);
    echo PHP_EOL . $clr('Time spent: ')->bold
        . $clr($minutes_spent . ' minutes ' . $seconds_spent . ' seconds ')->green->bold . PHP_EOL;
}
