<?php
/**
 * Created by PhpStorm.
 * User: alexandr.perfilov
 * Date: 3/7/16
 * Time: 9:38 PM
 */

namespace CKAN\Manager;


require_once dirname(dirname(__DIR__)) . '/inc/common.php';

/**
 * Create results dir for logs and json results
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_REDACTED_STATS';
mkdir($results_dir);

$CkanManager = new CkanManager(INVENTORY_CKAN_PROD_API_URL, INVENTORY_CKAN_PROD_API_KEY);

$CkanManager->resultsDir = $results_dir;

$organization_list = $CkanManager->organization_list(true);
//foreach ($organization_list as $organization) {
//    $members = $CkanManager->
//}

var_dump($organization_list);
//
//$headers = array_keys($brief[array_keys($brief)[0]]);
//$csv->writeRow($headers);
//$csv->writeFromArray($brief);

// show running time on finish
timer();
