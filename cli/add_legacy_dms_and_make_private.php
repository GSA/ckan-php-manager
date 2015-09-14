<?php

namespace CKAN\Manager;


use CKAN\OrganizationList;


/**
 * http://idm.data.gov/fed_agency.json
 */
define('ORGANIZATION_TO_TAG', 'Nuclear Regulatory Commission');

/**
 * Just list those datasets, no need to edit anything
 */
define('LIST_ONLY', false);

/**
 * Make it TRUE, if you want datasets to be marked as PRIVATE
 * LIST_ONLY must be true
 */
define('MARK_PRIVATE', true);

/**
 * Rename adding __legacy to the end of dataset name (url will be changed too)
 * LIST_ONLY must be true
 */
define('RENAME_TO_LEGACY', true);

echo "Tagging " . ORGANIZATION_TO_TAG . PHP_EOL;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Get organization terms, including all children, as Array
 */
$OrgList = new OrganizationList(AGENCIES_LIST_URL);
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
 */
$CkanManager = new CkanManager(CKAN_API_URL, LIST_ONLY ? null : CKAN_API_KEY);
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);

$CkanManager->resultsDir = $results_dir;
$CkanManager->tagLegacyDms($termsArray, 'metadata_from_legacy_dms');

// show running time on finish
timer();
