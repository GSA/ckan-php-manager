<?php
/**
 * @author Alex Perfilov
 * @date   5/23/14
 *
 */

namespace CKAN\Manager;


require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_CHECK_STAGING_VS_PROD';
mkdir($results_dir);

$CkanManagerProduction = new CkanManager(CKAN_API_URL);
$CkanManagerStaging = new CkanManager(CKAN_STAGING_API_URL);

$CkanManagerStaging->resultsDir = $results_dir;
$CkanManagerProduction->resultsDir = $results_dir;

$groups = $CkanManagerStaging->groupsArray();

foreach ($groups as $category) {
    $CkanManagerStaging->checkGroupAgainstProd($category, $CkanManagerProduction);
}

// show running time on finish
timer();
