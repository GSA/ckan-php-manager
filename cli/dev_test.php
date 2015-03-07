<?php

namespace CKAN\Manager;


require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_DEV_TEST';
mkdir($results_dir);

/**
 * Adding Legacy dms tag
 * Production
 */
//$CkanManager = new CkanManager(CKAN_API_URL, CKAN_API_KEY);

/**
 * Staging
 */
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);

/**
 * Dev
 */
$CkanManager = new CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);

/**
 * UAT
 */
//$CkanManager = new CkanManager(CKAN_UAT_API_URL, CKAN_UAT_API_KEY);

$CkanManager->resultsDir = $results_dir;
$CkanManager->test_dev();

// show running time on finish
timer();
