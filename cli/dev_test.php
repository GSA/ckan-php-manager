<?php

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
//$CkanManager = new \CKAN\Manager\CkanManager(CKAN_API_URL, CKAN_API_KEY);

/**
 * Staging
 */
//$CkanManager = new \CKAN\Manager\CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);

/**
 * Dev
 */
$CkanManager = new \CKAN\Manager\CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);

/**
 * UAT
 */
//$CkanManager = new \CKAN\Manager\CkanManager(CKAN_UAT_API_URL, CKAN_UAT_API_KEY);

$CkanManager->results_dir = $results_dir;
$CkanManager->test_dev();

// show running time on finish
timer();