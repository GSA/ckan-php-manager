<?php

/**
 * http://idm.data.gov/fed_agency.json
 */
define('ORGANIZATION_TO_EXPORT', 'Department of Labor');

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Get organization terms, including all children, as Array
 */
$OrgList    = new \CKAN\Core\OrganizationList(AGENCIES_LIST_URL);
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

/**
 * Search for packages by terms found
 */

/**
 * Production
 */
$CkanManager = new \CKAN\Manager\CkanManager(CKAN_API_URL);

/**
 * Staging
 */
//$CkanManager = new \CKAN\Manager\CkanManager(CKAN_STAGING_API_URL);

$CkanManager->export_packages_by_org_terms($termsArray, $results_dir);

// show running time on finish
timer();