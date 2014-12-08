<?php

namespace EasyCSV;

require_once dirname(dirname(__DIR__)) . '/inc/common.php';

foreach (glob(DATA_DIR . '/de*.json') as $json_file) {
    $dataset_names = json_decode(file_get_contents($json_file), true);  //  decode as assoc array

    $basename = str_replace('.json', '', basename($json_file));
    $writer = new Writer(DATA_DIR . '/' . $basename . '.csv');

    $writer->writeRow([
        'from',
        'to'
    ]);

    foreach ($dataset_names['name'] as $name => $count) {
        $newName = preg_replace("/^deleted-/",'',$name);
        $writer->writeRow([
            $name,
            $newName
        ]);
    }
}

// show running time on finish
timer();