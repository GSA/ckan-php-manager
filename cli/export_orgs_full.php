<?php

namespace CKAN\Manager;

use EasyCSV;

require_once dirname(__DIR__) . '/inc/common.php';

$results_dir = RESULTS_DIR . date('/Ymd-His') . '_EXPORT_ORGS_FULL';
mkdir($results_dir);

/**
 * Production
 */
//$CkanManager             = new CkanManager(CKAN_API_URL, CKAN_API_KEY);
$CkanManager = new CkanManager(INVENTORY_CKAN_PROD_API_URL, INVENTORY_CKAN_PROD_API_KEY);
$CkanManager->resultsDir = $results_dir;


foreach (glob(DATA_DIR . '/export_*.csv') as $csv_file) {
    $status = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
    echo $status;

//    fix wrong END-OF-LINE
    file_put_contents($csv_file, preg_replace('/[\\r\\n]+/', "\n", file_get_contents($csv_file)));

    $basename = str_replace('.csv', '', basename($csv_file));
    $logFile  = $results_dir . '/' . $basename . '.log';
//    file_put_contents($logFile, $status, FILE_APPEND | LOCK_EX);

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

        $organization = basename($row['0']);

        printf('[%04d] ', $i++);
//        Options available:
//        CkanManager::EXPORT_PUBLIC_ONLY
//        CkanManager::EXPORT_PRIVATE_ONLY
//        CkanManager::EXPORT_DMS_ONLY
//        CkanManager::EXPORT_DMS_ONLY | CkanManager::EXPORT_PRIVATE_ONLY
//        CkanManager::EXPORT_DMS_ONLY | CkanManager::EXPORT_PUBLIC_ONLY
        $CkanManager->full_organization_export($organization,
//            CkanManager::EXPORT_DMS_ONLY | CkanManager::EXPORT_PUBLIC_ONLY);
            CkanManager::EXPORT_PRIVATE_ONLY);
    }

    file_put_contents($logFile, $CkanManager->logOutput, FILE_APPEND | LOCK_EX);
}

// show running time on finish
timer();
