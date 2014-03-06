<?php

// debug mode on
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 30 minutes
set_time_limit(60 * 30);
ini_set('memory_limit', '1500M');

date_default_timezone_set('EST');

define('ROOT_DIR', dirname(__DIR__));
define('DATA_DIR', ROOT_DIR . '/data');
define('RESULTS_DIR', ROOT_DIR . '/results');

define('TIMER_START', time());

if (!is_dir(ROOT_DIR . '/vendor')) {
    throw new Exception('Install dependencies via composer');
}

require ROOT_DIR . '/vendor/autoload.php';

require 'config.php';

function timer()
{
    $finish = time();
    echo PHP_EOL . 'Time spent ' .
        floor((($finish - TIMER_START) / 60)) . ' minutes ' . (($finish - TIMER_START) % 60) . ' seconds ' . PHP_EOL;
}