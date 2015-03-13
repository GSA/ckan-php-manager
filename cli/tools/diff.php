<?php

namespace EasyCSV;

require_once dirname(dirname(__DIR__)) . '/inc/common.php';

$metadms = explode(PHP_EOL, file_get_contents(DATA_DIR.'/metadms.csv'));
$publicdms = explode(PHP_EOL, file_get_contents(DATA_DIR.'/publicdms.csv'));
$diff = array_diff($metadms, $publicdms);
file_put_contents(DATA_DIR.'/diff.csv', join(PHP_EOL, $diff));

// show running time on finish
timer();
