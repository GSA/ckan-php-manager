<?php

namespace CKAN\Manager;


/**
 * http://www.data.gov/app/themes/roots-nextdatagov/assets/Json/fed_agency.json
 */

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_TAG_BY_identifier';
mkdir($results_dir);

$CkanManager = new CkanManager(CKAN_API_URL, CKAN_API_KEY);
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);
//$CkanManager = new CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);
//$CkanManager = new CkanManager(CKAN_DEV2_API_URL, CKAN_DEV2_API_KEY);

$CkanManager->resultsDir = $results_dir;

$CkanManager->tagByExtraField('identifier', 'source_datajson_identifier');

// show running time on finish
timer();
