<?php

namespace CKAN\Manager;

use EasyCSV;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_UPDATE_HARVEST';
mkdir($results_dir);

//$CkanManager = new CkanManager(CKAN_API_URL, CKAN_API_KEY);
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);
$CkanManager = new CkanManager(CKAN_API_URL, CKAN_API_KEY);
//$CkanManager = new CkanManager(CKAN_UAT_API_URL, CKAN_UAT_API_KEY);

/**
 * JSON source expected is standard CKAN API output
 */

$CkanManager->resultsDir = $results_dir;

$harvest_sources = file_get_contents(CKANMNGR_DATA_DIR . '/harvest_sources_automated_remainders-c.json');
$harvest_sources = json_decode($harvest_sources, true);

$time = time();
$log_file = "$time.log";

foreach ($harvest_sources['result']['results'] as $harvest_source) {
    $CkanManager->updateHarvest($harvest_source['name'], 'frequency', 'MANUAL');
}

file_put_contents($results_dir  . '/' . $log_file, $CkanManager->logOutput);

// show running time on finish
timer();