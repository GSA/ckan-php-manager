<?php

namespace CKAN\Manager;


//use CKAN\OrganizationList;

/**
 * http://www.data.gov/app/themes/roots-nextdatagov/assets/Json/fed_agency.json
 */
define('ORGANIZATION_TO_EXPORT', 'Pension Benefit Guaranty Corporation');

require_once dirname(dirname(__DIR__)) . '/inc/common.php';

/**
 * Get organization terms, including all children, as Array
 */
//$OrgList = new OrganizationList(AGENCIES_LIST_URL);
//$termsArray = $OrgList->getTreeArrayFor(ORGANIZATION_TO_EXPORT);

$termsArray = ['pbgc-gov' => 'Pension Benefit Guaranty Corporation'];

/**
 * sometimes there is no parent term (ex. Department of Labor)
 */
if (!defined('PARENT_TERM')) {
    define('PARENT_TERM', '_');
}

/**
 * Create results dir for logs and json results
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_EXPORT_PBGC' . PARENT_TERM;
mkdir($results_dir);

/**
 * Search for packages by terms found
 */

/**
 * Production
 */
$CkanManager = new CkanManager(CKAN_API_URL);
//$CkanManager = new CkanManager(CKAN_UAT_API_URL);
//$CkanManager = new CkanManager(CKAN_QA_API_URL);
//$CkanManager = new CkanManager(INVENTORY_CKAN_PROD_API_URL, INVENTORY_CKAN_PROD_API_KEY);

/**
 * Staging
 */
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL);

$CkanManager->resultsDir = $results_dir;

$CkanManager->exportPackagesByOrgTerms($termsArray);

// show running time on finish
timer();
