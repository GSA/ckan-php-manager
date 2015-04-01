<?php

namespace CKAN\Manager;


define('LOG_NAME', 'active_users');

echo "Exporting active users" . PHP_EOL;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs and json results
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_EXPORT_' . LOG_NAME;
mkdir($results_dir);

/**
 * Search for packages by terms found
 */

/**
 * Production
 */
//$CkanManager = new CkanManager(INVENTORY_CKAN_PROD_API_URL, INVENTORY_CKAN_PROD_API_KEY);
$CkanManager = new CkanManager(CKAN_API_URL, CKAN_API_KEY);

/**
 * Staging
 */
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL);
$CkanManager->resultsDir = $results_dir;

$CkanManager->active_users();

// show running time on finish
timer();
