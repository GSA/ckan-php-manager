<?php

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_MAKE_PRIVATE';
mkdir($results_dir);

/**
 * Adding Legacy dms tag
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

$CkanManager->results_dir = $results_dir;
foreach (glob(DATA_DIR . '/private*.csv') as $csv_file) {
    $status = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
    echo $status;

    $basename = str_replace('.csv', '', basename($csv_file));

    //    fix wrong END-OF-LINE
    file_put_contents($csv_file, preg_replace('/[\\r\\n]+/', "\n", file_get_contents($csv_file)));

    file_put_contents($results_dir . '/' . $basename . '.log', $status, FILE_APPEND | LOCK_EX);

    $csv = new EasyCSV\Reader($csv_file, 'r+', false);
    while (true) {
        $row = $csv->getRow();
        if (!$row) {
            break;
        }
//        skip headers
        if (in_array(strtolower($row['0']), ['dataset', 'uid', 'uuid', 'name', 'url', 'data.gov url'])) {
            continue;
        }

        $dataset = basename($row['0']);
        $CkanManager->make_dataset_private($dataset, $results_dir, $basename);
    }
}

// show running time on finish
timer();