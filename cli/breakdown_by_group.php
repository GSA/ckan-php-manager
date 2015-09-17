<?php

namespace CKAN\Manager;


use EasyCSV;


/**
 * http://www.data.gov/app/themes/roots-nextdatagov/assets/Json/fed_agency.json
 */

define('GROUP_TO_EXPORT', 'aapi0916');
// http://catalog.data.gov/api/3/action/package_search?fq=aapi0916

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs and json results
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_BREAKDOWN_' . GROUP_TO_EXPORT;
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

$csv_agencies = new EasyCSV\Writer($results_dir . '/breakdown_' . GROUP_TO_EXPORT . '_by_agency_' . date(
        'Ymd-His'
    ) . '.csv');
$csv_categories = new EasyCSV\Writer($results_dir . '/breakdown_' . GROUP_TO_EXPORT . '_by_category_' . date(
        'Ymd-His'
    ) . '.csv');

$CkanManager->breakdownByGroup($csv_agencies, $csv_categories);

// show running time on finish
timer();
