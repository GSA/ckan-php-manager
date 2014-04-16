<?php

/**
 * http://idm.data.gov/fed_agency.json
 */
define('ORGANIZATION_TO_TAG', 'Department of Education');

/**
 * Make it TRUE, if you want datasets to be marked as PRIVATE
 */
define('MARK_PRIVATE', false);

echo "Tagging " . ORGANIZATION_TO_TAG . PHP_EOL;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Get organization terms, including all children, as Array
 */
$OrgList    = new \CKAN\Core\OrganizationList(AGENCIES_LIST_URL);
$termsArray = $OrgList->getTreeArrayFor(ORGANIZATION_TO_TAG);

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_LEGACY_' . PARENT_TERM;
mkdir($results_dir);

/**
 * Adding Legacy dms tag
 * Production
 */
$Importer = new \CKAN\Manager\CkanManager(CKAN_API_URL, CKAN_API_KEY);

/**
 * Staging
 */
//$Importer = new \CKAN\Manager\CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);

$Importer->tag_legacy_dms($termsArray, 'metadata_from_legacy_dms', $results_dir);

// show running time on finish
timer();