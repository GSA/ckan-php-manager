<?php

namespace CKAN\Manager;


echo "Organizations stats" . PHP_EOL;

require_once dirname(__DIR__) . '/inc/common.php';

define('START', isset($argv[1]) ? trim($argv[1]) : false);
define('STOP', isset($argv[2]) ? trim($argv[2]) : false);

/**
 * Create results dir for logs and json results
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_ORG_STATS';
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

$CkanManager->organizations_stats($results_dir);

// show running time on finish
timer();
