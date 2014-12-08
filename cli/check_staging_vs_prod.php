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
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_CHECK_STAGING_VS_PROD';
mkdir($results_dir);

/**
 * Adding Legacy dms tag
 * Production
 */
$CkanManagerProduction = new CkanManager(CKAN_API_URL);

/**
 * Staging
 */
$CkanManagerStaging = new CkanManager(CKAN_STAGING_API_URL);

$groups = $CkanManagerStaging->groups_array();

foreach ($groups as $category) {
    $CkanManagerStaging->check_group_against_prod($category, $CkanManagerProduction, $results_dir);
}

// show running time on finish
timer();