<?php

namespace CKAN\Manager;


use CKAN\Core\OrganizationList;

/**
 * http://idm.data.gov/fed_agency.json
 */
define('ORGANIZATION_TO_EXPORT', 'Department of Commerce');

require_once dirname(dirname(__DIR__)) . '/inc/common.php';

/**
 * Get organization terms, including all children, as Array
 */
$OrgList = new OrganizationList(AGENCIES_LIST_URL);
$termsArray = $OrgList->getTreeArrayFor(ORGANIZATION_TO_EXPORT);

/**
 * sometimes there is no parent term (ex. Department of Labor)
 */
if (!defined('PARENT_TERM')) {
    define('PARENT_TERM', '_');
}

/**
 * Create results dir for logs and json results
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_EXPORT_' . PARENT_TERM;
mkdir($results_dir);

$CkanManager = new CkanManager(CKAN_API_URL);
//$CkanManager = new CkanManager(CKAN_QA_API_URL);
//$CkanManager = new CkanManager(INVENTORY_CKAN_PROD_API_URL, INVENTORY_CKAN_PROD_API_KEY);
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL);

$CkanManager->resultsDir = $results_dir;

/**
 * We are skipping noaa-gov and nist-gov within current process
 */
unset($termsArray['noaa-gov']);
unset($termsArray['nist-gov']);

$CkanManager->exportPackagesByOrgTerms($termsArray);

// show running time on finish
timer();
