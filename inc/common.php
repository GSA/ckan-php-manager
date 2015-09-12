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
define('BACKUP_DIR', ROOT_DIR . '/backup');
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
    $c = new \Colors\Color();
    $minutes_spent = floor((($finish - TIMER_START) / 60));
    $seconds_spent = (($finish - TIMER_START) % 60);
    echo PHP_EOL . $c('Time spent: ')->bold
        . $c($minutes_spent . ' minutes ' . $seconds_spent . ' seconds ')->green->bold . PHP_EOL;
}
