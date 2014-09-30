<?php

/**
 * http://idm.data.gov/fed_agency.json
 */

$search = isset($argv[1]) ? trim($argv[1]) : false;

if (!$search) {
    die('Please define search by first param' . PHP_EOL);
}

$strip_search = preg_replace("/[^a-zA-Z0-9\\ ]+/i", '', $search);

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs and json results
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_EXPORT_BY_SEARCH_' . $strip_search;
mkdir($results_dir);

/**
 * Search for packages by terms found
 */

/**
 * Production
 */
$Importer = new \CKAN\Manager\CkanManager(CKAN_API_URL);

/**
 * Staging
 */
//$CkanManager = new \CKAN\Manager\CkanManager(CKAN_STAGING_API_URL);

$Importer->export_datasets_by_search($search, $results_dir);

// show running time on finish
timer();