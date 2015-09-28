<?php

namespace CKAN\Manager;

use EasyCSV;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_FIX_METADATA';
mkdir($results_dir);

$CkanManager = new CkanManager(INVENTORY_CKAN_PROD_API_URL, INVENTORY_CKAN_PROD_API_KEY);

$CkanManager->resultsDir = $results_dir;


foreach (glob(CKANMNGR_DATA_DIR . '/metadata*.csv') as $csv_file) {
    $status = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
    echo $status;

//    fix wrong END-OF-LINE
    file_put_contents($csv_file, preg_replace('/[\\r\\n]+/', "\n", file_get_contents($csv_file)));

//    file_put_contents($resultsDir . '/' . $basename . '_tags.log', $status, FILE_APPEND | LOCK_EX);

    $csv = new EasyCSV\Reader($csv_file, 'r+', false);
    while (true) {
        $row = $csv->getRow();
        if (!$row) {
            break;
        }
//        skip headers
        if (in_array(trim(strtolower($row['0'])), ['link', 'dataset', 'url', 'data.gov url'])) {
            continue;
        }

//        no anchors please
        list($dataset,) = explode('#', basename(trim($row['0'])));

        if (!$dataset) {
            continue;
        }

        $CkanManager->fixModified($dataset);
        file_put_contents($results_dir . '/log.csv', $CkanManager->logOutput, FILE_APPEND | LOCK_EX);
        $CkanManager->logOutput = '';
    }
}

// show running time on finish
timer();
