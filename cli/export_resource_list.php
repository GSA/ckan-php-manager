<?php

namespace CKAN\Manager;


    /**
     * http://www.data.gov/app/themes/roots-nextdatagov/assets/Json/fed_agency.json
     */

//$search = isset($argv[1]) ? trim($argv[1]) : false;
//
//if (!$search) {
//    die('Please define search by first param' . PHP_EOL);
//}
//
//$strip_search = preg_replace("/\\(([a-z]+-[a-z\\-]*[a-z]+)\\)/", '"${1}"', $search);
//
//$filename_strip_search = preg_replace("/[^a-zA-Z0-9\\ ]+/i", '', $search);

//die($strip_search.PHP_EOL);

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs and json results
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_EXPORT_RESOURCE_LIST';
mkdir($results_dir);

$CkanManager = new CkanManager(CKAN_API_URL);
//$CkanManager = new CkanManager(INVENTORY_CKAN_PROD_API_URL, INVENTORY_CKAN_PROD_API_KEY);
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL);

$CkanManager->resultsDir = $results_dir;
$CkanManager->exportResourceList(500);

// show running time on finish
timer();
