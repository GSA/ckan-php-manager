<?php

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_ASS_GROUPS';
mkdir($results_dir);

/**
 * Adding Legacy dms tag
 * Production
 */
$Importer = new \CKAN\Manager\CkanManager(CKAN_API_URL, CKAN_API_KEY);

/**
 * Staging
 */
//$Importer = new \CKAN\Manager\CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);

/**
 * Dev
 */
//$Importer = new \CKAN\Manager\CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);

/**
 * Sample csv
 * dataset,group,categories
 * https://catalog.data.gov/dataset/food-access-research-atlas,Agriculture,"Natural Resources and Environment"
 * download-crossing-inventory-data-highway-rail-crossing,Agriculture, "Natural Resources and Environment;Plants and Plant Systems Agriculture"

 */

foreach (glob(DATA_DIR . '/*.csv') as $csv_file) {
    $status = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
    echo $status;

    file_put_contents($results_dir . '/groups.log', $status, FILE_APPEND | LOCK_EX);

    $csv = new EasyCSV\Reader($csv_file, 'r+', false);
    while (true) {
        $row = $csv->getRow();
        if (!$row) {
            break;
        }
//        skip headers
        if ('dataset' == $row['0']) {
            continue;
        }

//        format group tags
        $categories = null;
        if ($row['2']) {
            $categories = '["' . join('","', explode(';', $row['2'])) . '"]';
        }

        $dataset = basename($row['0']);
        $Importer->assign_groups_and_categories_to_datasets([$dataset], $row['1'], $categories, $results_dir);
    }
}

// show running time on finish
timer();