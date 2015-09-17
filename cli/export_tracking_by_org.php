<?php

namespace CKAN\Manager;


use CKAN\OrganizationList;


/**
 * http://www.data.gov/app/themes/roots-nextdatagov/assets/Json/fed_agency.json
 */
define('ORGANIZATION_TO_EXPORT', 'National Science Foundation');

echo "Exporting " . ORGANIZATION_TO_EXPORT . PHP_EOL;

require_once dirname(__DIR__) . '/inc/common.php';

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
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_TRACKING_' . PARENT_TERM;
mkdir($results_dir);

/**
 * Search for packages by terms found
 */

/**
 * Production
 */
$CkanManager = new CkanManager(CKAN_API_URL);

/**
 * Staging
 */
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL);

$CkanManager->resultsDir = $results_dir;

$CkanManager->exportTrackingByOrgTerms($termsArray);

// show running time on finish
timer();
