<?php

namespace CKAN\Manager;

require_once dirname(__DIR__) . '/inc/common.php';


$CkanManager = new CkanManager(CKAN_API_URL, CKAN_API_KEY);
//$CkanManager = new CkanManager(CKAN_UAT_API_URL, CKAN_UAT_API_KEY);
//$CkanManager = new CkanManager(CKAN_QA_API_URL, CKAN_QA_API_KEY);
//$CkanManager = new CkanManager(INVENTORY_CKAN_PROD_API_URL, INVENTORY_CKAN_PROD_API_KEY);


$results_dir = RESULTS_DIR . date('/Ymd-His') . '_RESOURCE_CREATE';
mkdir($results_dir);
$CkanManager->resultsDir = $results_dir;

$logFile = $results_dir . '/_log.csv';

$CkanManager->resourceCreate([
    'package_id' => 'department-of-the-interior-enterprise-data-inventory',
//    'package_id' => 'u-s-widget-manufacturing-statistics-92174',
    'url'        => 'http://data.doi.gov/WAF/edi.json',
    'name'       => 'EDI Json',
    'format'     => 'application/json'
]);

file_put_contents($logFile, $CkanManager->logOutput, FILE_APPEND | LOCK_EX);
//$CkanManager->logOutput = '';

// show running time on finish
timer();
