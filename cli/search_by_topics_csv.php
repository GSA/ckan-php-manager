<?php

namespace CKAN\Manager;


/**
 * http://idm.data.gov/fed_agency.json
 */
define('ORGANIZATION_TO_EXPORT', 'Department of Labor');

require_once dirname(__DIR__) . '/inc/common.php';

if (!is_readable($keywords_file_path = DATA_DIR . '/search_topics.csv')) {
    die($keywords_file_path . ' not readable');
}

$topics_list = file_get_contents($keywords_file_path);
$topics_list = preg_replace('/[\\r\\n]+/', "\n", $topics_list);
$topics_list = explode("\n", $topics_list);

/**
 * Create results dir for logs and json results
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_SEARCH_TOPICS_' . sizeof($topics_list);
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

$CkanManager->search_by_topics($topics_list);

// show running time on finish
timer();
