<?php

namespace CKAN\Manager;

use EasyCSV;

require_once dirname(dirname(__DIR__)) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_REMOVE_GROUPS';
mkdir($results_dir);


$CkanManager = new CkanManager(CKAN_API_URL, CKAN_API_KEY);
//$CkanManager = new CkanManager(CKAN_UAT_API_URL, CKAN_UAT_API_KEY);
//$CkanManager = new CkanManager(CKAN_QA_API_URL, CKAN_QA_API_KEY);
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);
//$CkanManager = new CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);

$CkanManager->resultsDir = $results_dir;
foreach (glob(CKANMNGR_DATA_DIR . '/remove_*.csv') as $csv_file) {
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
        if (in_array(strtolower($row['0']),
            ['dataset', 'uid', 'uuid', 'name', 'url', 'data.gov url', 'dataset link'])) {
            continue;
        }

//        no anchors please
        list($dataset,) = explode('#', basename(trim($row['0'])));
        $category = trim(isset($row['1']) ? ($row['1'] ?: '') : '');
        $tags = trim(isset($row['2']) ? ($row['2'] ?: '') : '');
        $CkanManager->removeTagsAndGroupsFromDatasets([$dataset], $category, $tags, $basename);
    }
}

// show running time on finish
timer();
