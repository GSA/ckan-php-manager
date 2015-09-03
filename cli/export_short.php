<?php

namespace CKAN\Manager;


use EasyCSV\Writer;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs and json results
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_EXPORT_SHORT';
mkdir($results_dir);

$CkanManager = new CkanManager(CKAN_API_URL);
//$CkanManager = new CkanManager(INVENTORY_CKAN_PROD_API_URL, INVENTORY_CKAN_PROD_API_KEY);
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL);
//$CkanManager = new CkanManager(CKAN_UAT_API_URL);

$csv = new Writer($results_dir . '/export.' . date('Y-m-d') . '.csv');

$csv->writeRow([
    'title',
    'name',
    'url',
    'identifier',
    'topics',
    'categories',
]);

$CkanManager->resultsDir = $results_dir;

//$brief = $CkanManager->exportShort('extras_license:"https\://creativecommons.org/publicdomain/zero/1.0/" AND (dataset_type:dataset)');
$brief = $CkanManager->exportShort('','((collection_package_id:* OR *:*) AND license_id:"cc-by-sa" AND license:"https\://creativecommons.org/publicdomain/zero/1.0/") AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('%28%28collection_package_id:*%20OR%20*:*%29+AND+license_id:"cc-by-sa"+AND+license:"https://creativecommons.org/publicdomain/zero/1.0/"%29');
//$brief = $CkanManager->exportShort('organization:wake-county AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('organization:gsa-gov AND harvest_source_title:Open* AND (dataset_type:dataset)',
//    'http://uat-catalog-fe-data.reisys.com/dataset/');
//$brief = $CkanManager->exportShort('(extra_harvest_source_title:Open+*) AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('organization:gsa-gov AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('extras_harvest_source_title:Test ISO WAF AND (dataset_type:dataset)');
//var_dump($brief);die();
$csv->writeFromArray($brief);

// show running time on finish
timer();
