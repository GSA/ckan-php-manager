<?php

namespace CKAN\Manager;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_IMPORT';
mkdir($results_dir);

/**
 * Import datasets from json
 * Production
 */
$CkanManager = new CkanManager(CKAN_API_URL, LIST_ONLY ? null : CKAN_API_KEY);

/**
 * Staging
 */
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);

$CkanManager->resultsDir = $results_dir;

foreach (glob(DATA_DIR . '/import_*.json') as $json_file) {
    $status = PHP_EOL . PHP_EOL . basename($json_file) . PHP_EOL . PHP_EOL;
    echo $status;

    $basename = str_replace('.json', '', basename($json_file));

//    fix wrong END-OF-LINE
//    file_put_contents($json_file, preg_replace('/[\\r\\n]+/', "\n", file_get_contents($json_file)));
    $json = file_get_contents($json_file);

    $CkanManager->import_json($json, $basename);

}

// show running time on finish
timer();
