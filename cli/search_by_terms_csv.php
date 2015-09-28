<?php

namespace CKAN\Manager;


/**
 * http://www.data.gov/app/themes/roots-nextdatagov/assets/Json/fed_agency.json
 */
define('ORGANIZATION_TO_EXPORT', 'Department of Labor');

require_once dirname(__DIR__) . '/inc/common.php';

if (!is_readable($keywords_file_path = CKANMNGR_DATA_DIR . '/search_terms.csv')) {
    die($keywords_file_path . ' not readable');
}

$keywords_list = file_get_contents($keywords_file_path);
$keywords_list = preg_replace('/[\\r\\n]+/', "\n", $keywords_list);
$keywords_list = explode("\n", $keywords_list);

/**
 * Create results dir for logs and json results
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_SEARCH_TERMS_' . sizeof($keywords_list);
mkdir($results_dir);

/**
 * Search for packages by terms found
 */

/**
 * Production
 */
$CkanManager = new CkanManager(CKAN_API_URL);

/**
 * Staging
 */
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL);

$CkanManager->resultsDir = $results_dir;

$CkanManager->searchByTerms($keywords_list);

// show running time on finish
timer();
