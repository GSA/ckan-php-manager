<?php

namespace CKAN\Manager;


use EasyCSV;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_DELETE_DATASETS';
mkdir($results_dir);

/**
 * Production
 */
$CkanManager = new CkanManager(CKAN_API_URL, CKAN_API_KEY);
//$CkanManager = new CkanManager(CKAN_UAT_API_URL, CKAN_UAT_API_KEY);
//$CkanManager = new CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);

/**
 * Staging
 */
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);

/**
 * Dev
 */
//$CkanManager = new CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);


$CkanManager->resultsDir = $results_dir;

/**
 * CSV
 * datasetName, orgId
 */

foreach (glob(DATA_DIR . '/delete*.csv') as $csv_file) {
    $status = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
    echo $status;

//    fix wrong END-OF-LINE
    file_put_contents($csv_file, preg_replace('/[\\r\\n]+/', "\n", file_get_contents($csv_file)));

    $basename = str_replace('.csv', '', basename($csv_file));
    $logFile = $results_dir . '/' . $basename . '_log.csv';

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

        $datasetName = basename($row['0']);
        // $organizationName = basename($row['1']);

        printf('[%04d] ', $i++);
        $CkanManager->deleteDataset($datasetName);//, $organizationName
        file_put_contents($logFile, $CkanManager->logOutput, FILE_APPEND | LOCK_EX);
        $CkanManager->logOutput = '';
    }
}

// show running time on finish
timer();
