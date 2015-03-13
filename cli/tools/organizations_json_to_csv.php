<?php

namespace EasyCSV;

require_once dirname(dirname(__DIR__)) . '/inc/common.php';

foreach (glob(DATA_DIR . '/organizations.json') as $json_file) {
    $json = json_decode(file_get_contents($json_file), true);  //  decode as assoc array

    $basename = str_replace('.json', '', basename($json_file));
    $writer = new Writer(DATA_DIR . '/' . $basename . '.csv');

//    $writer->writeRow([
//        'from',
//        'to'
//    ]);

    foreach ($json['result'] as $organization) {
        $writer->writeRow([$organization['name']]);
    }
}

// show running time on finish
timer();
