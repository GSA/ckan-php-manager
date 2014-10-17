<?php

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_REMOVE_GROUPS';
mkdir($results_dir);

/**
 * Set to `true` if you want to remove topic too like 'Climate' etc.
 */
define('REMOVE_GROUP', false);

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
foreach (glob(DATA_DIR . '/remove*.csv') as $csv_file) {
    $status = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
    echo $status;

    $basename = str_replace('.csv', '', basename($csv_file));

    //    fix wrong END-OF-LINE
    file_put_contents($csv_file, preg_replace('/[\\r\\n]+/', "\n", file_get_contents($csv_file)));

    file_put_contents($results_dir . '/' . $basename . '_remove.log', $status, FILE_APPEND | LOCK_EX);

    $csv = new EasyCSV\Reader($csv_file, 'r+', false);
    while (true) {
        $row = $csv->getRow();
        if (!$row) {
            break;
        }
//        skip headers
        if (in_array(strtolower($row['0']), ['dataset', 'uid', 'uuid', 'name', 'url', 'data.gov url', 'dataset link'])) {
            continue;
        }

        $dataset = basename($row['0']);
        $category = isset($row['1']) ? ($row['1'] ?: '') : '';
        $tags = isset($row['2']) ? ($row['2'] ?: '') : '';
        $CkanManager->remove_tags_and_groups_to_datasets([$dataset], $category, $tags, $basename);
    }
}

// show running time on finish
timer();