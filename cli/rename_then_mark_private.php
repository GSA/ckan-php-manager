<?php

/**
 * First run validation script, to find matches against CKAN, to get _legacy.csv file
 */

namespace CKAN\Manager;


use EasyCSV;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_RENAME_DATASETS';
mkdir($results_dir);

$CkanManager = new CkanManager(CKAN_API_URL, CKAN_API_KEY);
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);
//$CkanManager = new CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);
//$CkanManager = new CkanManager(INVENTORY_CKAN_PROD_API_URL, INVENTORY_CKAN_PROD_API_KEY);

/**
 * CSV
 * datasetName, newDatasetName
 */

$CkanManager->resultsDir = $results_dir;

foreach (glob(CKANMNGR_DATA_DIR . '/prename*.csv') as $csv_file) {
    $status = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
    echo $status;

//    fix wrong END-OF-LINE
    file_put_contents($csv_file, preg_replace('/[\\r\\n]+/', "\n", file_get_contents($csv_file)));

    $basename = str_replace('.csv', '', basename($csv_file));
    file_put_contents($results_dir . '/' . $basename . '_rename.log', $status, FILE_APPEND | LOCK_EX);

    $csv = new EasyCSV\Reader($csv_file, 'r+', false);
    $i = 1;
    while (true) {
        $row = $csv->getRow();
        if (!$row) {
            break;
        }
//        skip headers
        if (in_array(trim(strtolower($row['0'])), ['dataset', 'url', 'old dataset url', 'from'])) {
            continue;
        }

        $datasetName = trim(basename($row['0']));
        $newDatasetName = substr($datasetName, 0, 70) . $i . '_legacy';
//        $newDatasetName = basename($row['1']);

        printf('[%04d] ', $i++);
        $CkanManager->renameDataset($datasetName, $newDatasetName, $basename);
        $CkanManager->makeDatasetPrivate($newDatasetName, $basename);
    }
}

// show running time on finish
timer();