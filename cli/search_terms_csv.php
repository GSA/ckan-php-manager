<?php

/**
 * http://idm.data.gov/fed_agency.json
 */
define('ORGANIZATION_TO_EXPORT', 'Department of Labor');

require_once dirname(__DIR__) . '/inc/common.php';

if (!is_readable($keywords_file_path = DATA_DIR . '/keywords.csv')) {
    die($keywords_file_path . ' not readable');
}

$keywords_list = file_get_contents($keywords_file_path);
$keywords_list = preg_replace('/[\\r\\n]+/', "\n", $keywords_list);
$keywords_list = explode("\n", $keywords_list);

/**
 * Create results dir for logs and json results
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_SEARCH_' . sizeof($keywords_list);
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

$Importer->search_terms($keywords_list, $results_dir);

// show running time on finish
timer();