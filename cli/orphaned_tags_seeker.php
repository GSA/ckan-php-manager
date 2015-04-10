<?php

namespace CKAN\Manager;


require_once dirname(__DIR__) . '/inc/common.php';

$start = isset($argv[1]) ? intval($argv[1]) : 0;
$limit = isset($argv[2]) ? intval($argv[2]) : 10000;

/**
 * Create results dir for logs
 */
//$resultsDir = RESULTS_DIR . date('/Ymd-His') . '_MISSING_GROUPS_DATASETS';
$results_dir = RESULTS_DIR . date('/Ymd-1') . '_MISSING_GROUPS_DATASETS';
is_dir($results_dir) || mkdir($results_dir);

/**
 * Production
 */
$CkanManager = new CkanManager(CKAN_API_URL, CKAN_API_KEY);

/**
 * Staging
 */
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);

/**
 * Dev
 */
//$CkanManager = new CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);
$CkanManager->resultsDir = $results_dir;

$CkanManager->orphanedTagsSeek($limit, $start);

// show running time on finish
timer();
