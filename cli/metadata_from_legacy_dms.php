<?php

/**
 * http://idm.data.gov/fed_agency.json
 */
define('ORGANIZATION_TO_TAG', 'Small Business Administration');

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
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_TAG_' . PARENT_TERM;
mkdir($results_dir);

/**
 * Search for packages by terms found
 */
$Importer = new \CKAN\Manager\CkanManager(defined('CKAN_DEV_API_URL') ? CKAN_DEV_API_URL : CKAN_API_URL, CKAN_API_KEY);
$Importer->tag_legacy_dms($termsArray, 'metadata_from_legacy_dms', $results_dir);