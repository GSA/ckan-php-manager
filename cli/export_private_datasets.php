<?php

namespace CKAN\Manager;


use CKAN\Core\OrganizationList;

/**
 * @author Alex Perfilov
 * @date   5/23/14
 *
 */

require_once dirname(__DIR__) . '/inc/common.php';

$start = isset($argv[1]) ? trim($argv[1]) : false;
$limit = isset($argv[2]) ? intval($argv[2]) : 1;

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_PRIVATE_DATASETS_' . $start ?: '';
mkdir($results_dir);

$CkanManager = new CkanManager(CKAN_API_URL, CKAN_API_KEY);
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);
//$CkanManager = new CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);

define('ERROR_REPORTING', E_ALL);

/**
 * Get organization terms, including all children, as Array
 */
$OrgList = new OrganizationList(AGENCIES_LIST_URL);
$termsArray = $OrgList->getTreeArray();

$CkanManager->resultsDir = $results_dir;

$CkanManager->getPrivateList($termsArray, $start, $limit);

// show running time on finish
timer();
