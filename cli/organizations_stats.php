<?php

namespace CKAN\Manager;


echo "Organizations stats" . PHP_EOL;

require_once dirname(__DIR__) . '/inc/common.php';

define('START', isset($argv[1]) ? trim($argv[1]) : false);
define('STOP', isset($argv[2]) ? trim($argv[2]) : false);

//define('LIST_ORGS_ONLY', true);

/**
 * Create results dir for logs and json results
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_ORG_STATS';
mkdir($results_dir);

/**
 * Production
 */
$CkanManager = new CkanManager(INVENTORY_CKAN_PROD_API_URL, INVENTORY_CKAN_PROD_API_KEY);
//$CkanManager = new CkanManager(CKAN_API_URL, CKAN_API_KEY);

/**
 * Staging
 */
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL);

$CkanManager->resultsDir = $results_dir;

$CkanManager->organizations_stats();

if ($CkanManager->logOutput) {
    file_put_contents($results_dir . '/log.csv', $CkanManager->logOutput);
}

// show running time on finish
timer();
