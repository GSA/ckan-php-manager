<?php

namespace CKAN\Manager;


/**
 * http://www.data.gov/app/themes/roots-nextdatagov/assets/Json/fed_agency.json
 */
define('ORGANIZATION_TO_EXPORT', 'Department of Labor');

require_once dirname(__DIR__) . '/inc/common.php';

if (!is_readable($keywords_file_path = DATA_DIR . '/search_organizations.csv')) {
    die($keywords_file_path . ' not readable');
}

$organizations_list = file_get_contents($keywords_file_path);
$organizations_list = preg_replace('/[\\r\\n]+/', "\n", $organizations_list);
$organizations_list = explode("\n", $organizations_list);

/**
 * Create results dir for logs and json results
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_SEARCH_ORGANIZATIONS_' . sizeof($organizations_list);
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

$CkanManager->searchByOrganizations($organizations_list);

// show running time on finish
timer();
