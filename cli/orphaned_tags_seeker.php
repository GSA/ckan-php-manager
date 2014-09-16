<?php

require_once dirname(__DIR__) . '/inc/common.php';

$start = isset($argv[1]) ? intval($argv[1]) : 0;
$limit = isset($argv[2]) ? intval($argv[2]) : 10000;


/**
 * Create results dir for logs
 */
//$results_dir = RESULTS_DIR . date('/Ymd-His') . '_MISSING_GROUPS_DATASETS';
$results_dir = RESULTS_DIR . date('/Ymd-1') . '_MISSING_GROUPS_DATASETS';
is_dir($results_dir) || mkdir($results_dir);

/**
 * Production
 */
$Ckan = new \CKAN\Manager\CkanManager(CKAN_API_URL, CKAN_API_KEY);

/**
 * Staging
 */
//$Ckan = new \CKAN\Manager\CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);

/**
 * Dev
 */
//$Ckan = new \CKAN\Manager\CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);

$Ckan->orphaned_tags_seek($results_dir, $limit, $start);

// show running time on finish
timer();