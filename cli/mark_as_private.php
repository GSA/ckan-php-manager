<?php

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_MAKE_PRIVATE';
mkdir($results_dir);
$basename = "private";
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
//$CkanManager = new \CKAN\Manager\CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);

/**
 * local
 */
$CkanManager = new \CKAN\Manager\CkanManager(CKAN_PRODUCTION_API_URL, CKAN_PRODUCTION_API_KEY);

$CkanManager->results_dir = $results_dir;


$list = $CkanManager->try_package_list();

foreach($list as $dataset_id){
    $CkanManager->make_dataset_private($dataset_id, $results_dir, $basename);
}


// show running time on finish
timer();