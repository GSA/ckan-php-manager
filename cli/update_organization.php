<?php
///**
// * Created by PhpStorm.
// * User: ykhadilkar
// * Date: 02/10/15
// * Time: 10:40 AM
// */
//require_once dirname(__DIR__) . '/inc/common.php';
//
///**
// * Create results dir for logs
// */
//$results_dir = RESULTS_DIR . date('/Ymd-His') . '_ADD_RESOURCE';
//mkdir($results_dir);
//
//
///**
// * Adding Legacy dms tag
// * Production
// */
////$CkanManager = new \CKAN\Manager\CkanManager(CKAN_API_URL, CKAN_API_KEY);
//
///**
// * Staging
// */
////$CkanManager = new \CKAN\Manager\CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);
//
///**
// * Dev
// */
////$CkanManager = new \CKAN\Manager\CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);
//
///**
// * Local
// */
//$CkanManager = new \CKAN\Manager\CkanManager(CKAN_PRODUCTION_API_URL, CKAN_PRODUCTION_API_KEY);
//
//
//$CkanManager->results_dir = $results_dir;
//foreach (glob(DATA_DIR . '/update_doe_datasets.csv') as $csv_file) {
//    $status = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
//    echo $status;
//
//    $basename = str_replace('.csv', '', basename($csv_file));
//
//    //    fix wrong END-OF-LINE
//    file_put_contents($csv_file, preg_replace('/[\\r\\n]+/', "\n", file_get_contents($csv_file)));
//
//    file_put_contents($results_dir . '/' . $basename . '_update_organization.log', $status, FILE_APPEND | LOCK_EX);
//
//    $csv = new EasyCSV\Reader($csv_file, 'r+', false);
//    while (true) {
//        $row = $csv->getRow();
//        if (!$row) {
//            break;
//        }
////        skip headers
//        if (in_array(strtolower($row['0']), ['url', 'exact match', 'title', 'found by title'])) {
//            continue;
//        }
//
//        $package_id = str_replace("https://inventory.data.gov/dataset/", "", $row[0]);
//        //$organization_id = "ers-usda-gov";
//        $package_name = "1bef2082-a4ca-45c5-b307-3d8bfce384df";
//        $CkanManager->update_dataset_parent($package_id, $package_name, $basename);
//    }
//}
//
//// show running time on finish
//timer();
