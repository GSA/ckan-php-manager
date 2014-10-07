<?php

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_ASSIGN_GROUPS';
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

/**
 * UAT
 */
//$CkanManager = new \CKAN\Manager\CkanManager(CKAN_UAT_API_URL, CKAN_UAT_API_KEY);

/**
 * Sample csv
 * dataset,group,categories
 * https://catalog.data.gov/dataset/food-access-research-atlas,Agriculture,"Natural Resources and Environment"
 * download-crossing-inventory-data-highway-rail-crossing,Agriculture, "Natural Resources and Environment;Plants and Plant Systems Agriculture"

 */

$CkanManager->results_dir = $results_dir;
foreach (glob(DATA_DIR . '/assign*.csv') as $csv_file) {
    $status = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
    echo $status;

    $basename = str_replace('.csv', '', basename($csv_file));

//    fix wrong END-OF-LINE
    file_put_contents($csv_file, preg_replace('/[\\r\\n]+/', "\n", file_get_contents($csv_file)));

//    file_put_contents($results_dir . '/' . $basename . '_tags.log', $status, FILE_APPEND | LOCK_EX);

    $csv = new EasyCSV\Reader($csv_file, 'r+', false);
    $i   = 0;
    while (true) {
        $row = $csv->getRow();
        if (!$row) {
            break;
        }
//        skip headers
        if (in_array(trim(strtolower($row['0'])), ['link', 'dataset', 'url', 'data.gov url'])) {
            continue;
        }

//        format group tags
        $categories = [];
        if (isset($row['2']) && $row['2']) {
            $categories = explode(';', trim($row['2']));
            $categories = array_map('trim', $categories);

        }

        $dataset = basename(trim($row['0']));
        if (!$dataset) {
            continue;
        }

//        double trouble check
        if (strpos($row['0'], '://')) {
            if (!strpos($row['0'], '/dataset/')) {
                file_put_contents(
                    $results_dir . '/' . $basename . '_tags.log.csv',
                    $row['0'] . ',WRONG URL' . PHP_EOL,
                    FILE_APPEND | LOCK_EX
                );
                continue;
            }
        }

        $CkanManager->assign_groups_and_categories_to_datasets(
            [$dataset],
            trim($row['1']),
            $categories,
            $basename
        );
    }
}

// show running time on finish
timer();