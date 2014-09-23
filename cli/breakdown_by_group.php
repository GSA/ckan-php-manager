<?php

/**
 * http://idm.data.gov/fed_agency.json
 */
define('GROUP_TO_EXPORT', 'aapi0916');
// http://catalog.data.gov/api/3/action/package_search?fq=aapi0916

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs and json results
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_BREAKDOWN_' . GROUP_TO_EXPORT;
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
//$Importer = new \CKAN\Manager\CkanManager(CKAN_STAGING_API_URL);

$csv_agencies   = new EasyCSV\Writer($results_dir . '/' . GROUP_TO_EXPORT . '_by_agency.csv');
$csv_categories = new EasyCSV\Writer($results_dir . '/' . GROUP_TO_EXPORT . '_by_category.csv');

$Importer->breakdown_by_group($csv_agencies, $csv_categories);

// show running time on finish
timer();