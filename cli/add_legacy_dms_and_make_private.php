<?php

/**
 * http://idm.data.gov/fed_agency.json
 */
define('ORGANIZATION_TO_TAG', 'US Equal Employment Opportunity Commission');

/**
 * Just list those datasets, no need to edit anything
 */
define('LIST_ONLY', true);

/**
 * Make it TRUE, if you want datasets to be marked as PRIVATE
 */
define('MARK_PRIVATE', true);

echo "Tagging " . ORGANIZATION_TO_TAG . PHP_EOL;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Get organization terms, including all children, as Array
 */
$OrgList    = new \CKAN\Core\OrganizationList(AGENCIES_LIST_URL);
$termsArray = $OrgList->getTreeArrayFor(ORGANIZATION_TO_TAG);

/**
 * sometimes there is no parent term (ex. Department of Labor)
 */
if (!defined('PARENT_TERM')) {
    die('PARENT_TERM not found');
}

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_LEGACY_' . PARENT_TERM;
mkdir($results_dir);

/**
 * Adding Legacy dms tag
 * Production
 */
$CkanManager = new \CKAN\Manager\CkanManager(CKAN_API_URL, LIST_ONLY ? null : CKAN_API_KEY);

/**
 * Staging
 */
//$CkanManager = new \CKAN\Manager\CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);

$CkanManager->results_dir = $results_dir;
$CkanManager->tag_legacy_dms($termsArray, 'metadata_from_legacy_dms');

// show running time on finish
timer();