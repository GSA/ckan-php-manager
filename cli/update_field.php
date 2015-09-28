<?php

namespace CKAN\Manager;

use EasyCSV;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_UPDATE_EXTRA';
mkdir($results_dir);

//$CkanManager = new CkanManager(CKAN_API_URL, CKAN_API_KEY);
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);
$CkanManager = new CkanManager(CKAN_API_URL, CKAN_PROD_API_KEY);
//$CkanManager = new CkanManager(CKAN_UAT_API_URL, CKAN_UAT_API_KEY);

/**
 * Sample csv
 * dataset,group,categories
 * https://catalog.data.gov/dataset/food-access-research-atlas,Agriculture,"Natural Resources and Environment"
 * download-crossing-inventory-data-highway-rail-crossing,Agriculture, "Natural Resources and Environment;Plants and Plant Systems Agriculture"
 */

$CkanManager->resultsDir = $results_dir;
foreach (glob(CKANMNGR_DATA_DIR . '/license_update*.csv') as $csv_file) {
    $status = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
    echo $status;

    $basename = str_replace('.csv', '', basename($csv_file));

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
        if (in_array(trim(strtolower($row['0'])), ['title','name','url','identifier','topics','categories'])) {
            continue;
        }

//        no anchors please
        list($dataset,) = explode('#', basename(trim($row['0'])));

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
        $package_id = $row['1'];
        $license_id = "cc-zero";
        $CkanManager->updateLicenseId($package_id, $license_id);
    }
}

// show running time on finish
timer();
