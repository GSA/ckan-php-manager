<?php

/**
 * First run validation script, to find matches against CKAN, to get _legacy.csv file
 */

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_RENAME_DATASETS';
mkdir($results_dir);

/**
 * Production
 */
$CkanManager = new \CKAN\Manager\CkanManager(CKAN_API_URL, CKAN_API_KEY);

/**
 * Staging
 */
//$CkanManager = new \CKAN\Manager\CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);

/**
 * Dev
 */
//$CkanManager = new \CKAN\Manager\CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);

/**
 * CSV
 * datasetName, newDatasetName_legacy
 */

foreach (glob(DATA_DIR . '/rename_*.csv') as $csv_file) {
    $status = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
    echo $status;

//    fix wrong END-OF-LINE
    file_put_contents($csv_file, preg_replace('/[\\r\\n]+/', "\n", file_get_contents($csv_file)));

    $basename = str_replace('.csv', '', basename($csv_file));
    file_put_contents($results_dir . '/' . $basename . '_rename.log', $status, FILE_APPEND | LOCK_EX);

    $csv = new EasyCSV\Reader($csv_file, 'r+', false);
    $i   = 1;
    while (true) {
        $row = $csv->getRow();
        if (!$row) {
            break;
        }
//        skip headers
        if (in_array(trim(strtolower($row['0'])), ['dataset', 'url', 'old dataset url', 'from'])) {
            continue;
        }

        $datasetName    = basename($row['0']);
        $newDatasetName = basename($row['1']);

        printf('[%04d] ', $i++);
        $CkanManager->renameDataset($datasetName, $newDatasetName, $results_dir, $basename);
    }
}

// show running time on finish
timer();