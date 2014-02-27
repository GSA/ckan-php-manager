<?php

/**
 * http://idm.data.gov/fed_agency.json
 */
define('ORGANIZATION_TO_EXPORT', 'Department of the Treasury');

echo "Exporting " . ORGANIZATION_TO_EXPORT . PHP_EOL;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Get organization terms, including all children, as Array
 */
$OrgList    = new \CKAN\Core\OrganizationList(AGENCIES_LIST_URL);
$termsArray = $OrgList->getTreeArrayFor(ORGANIZATION_TO_EXPORT);

/**
 * Create results dir for logs and json results
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_EXPORT_' . PARENT_TERM;
mkdir($results_dir);

/**
 * Search for packages by terms found
 */
$Importer = new \CKAN\Manager\CkanManager(CKAN_API_URL);
$Importer->export_packages_by_org_terms($termsArray, $results_dir);