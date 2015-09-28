<?php

namespace CKAN\Manager;


/**
 * http://www.data.gov/app/themes/roots-nextdatagov/assets/Json/fed_agency.json
 */

$topic = isset($argv[1]) ? trim($argv[1]) : false;

if (!$topic) {
    die('You should indicate topic to export, as first argument in shell' . PHP_EOL);
}

$topic = preg_replace("/[^a-zA-Z0-9\\ ]+/i", '', $topic);

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs and json results
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_EXPORT_BY_TOPIC_' . $topic;
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

$CkanManager->exportDatasetsWithTagsByGroup($topic);

// show running time on finish
timer();
