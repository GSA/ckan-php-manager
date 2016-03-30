<?php

namespace CKAN\Manager;


use EasyCSV\Writer;

require_once dirname(__DIR__) . '/inc/common.php';

$prefix = isset($argv[1]) ? trim($argv[1]) : '';

/**
 * Create results dir for logs and json results
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_EXPORT_SHORT'.($prefix?'_'.$prefix:'');
mkdir($results_dir);

$CkanManager = new CkanManager(CKAN_API_URL);
//$CkanManager = new CkanManager(INVENTORY_CKAN_PROD_API_URL);
//$CkanManager = new CkanManager(INVENTORY_CKAN_PROD_API_URL, INVENTORY_CKAN_PROD_API_KEY);
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL);
//$CkanManager = new CkanManager(CKAN_UAT_API_URL);

$csv = new Writer($results_dir . '/export.' . ($prefix?$prefix.'.':"") . date('Y-m-d') . '.csv');

//$csv->writeRow([
//    'ckan id',
//    'title',
//    'name',
//    'url',
//    'identifier',
//    'org title',
//    'org name',
//    'topics',
//    'categories',
//]);

$CkanManager->resultsDir = $results_dir;

//$brief = $CkanManager->exportShort('extras_license:"https\://creativecommons.org/publicdomain/zero/1.0/" AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('','((collection_package_id:* OR *:*) AND license_id:"cc-by-sa" AND license:"https\://creativecommons.org/publicdomain/zero/1.0/") AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('%28%28collection_package_id:*%20OR%20*:*%29+AND+license_id:"cc-by-sa"+AND+license:"https://creativecommons.org/publicdomain/zero/1.0/"%29');
//$brief = $CkanManager->exportShort('organization:wake-county AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('organization:gsa-gov AND harvest_source_title:Open* AND (dataset_type:dataset)',
//$brief = $CkanManager->exportShort('organization:doe-gov AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('organization:dhs-gov AND (harvest_source_title:DHS*) AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('organization:epa-gov AND (harvest_source_title:*Gateway) AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('organization:epa-gov AND (metadata_type:geospatial) AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('organization:nasa-gov AND (harvest_source_title:NASA*) AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('organization:ntsb-gov AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('organization:noaa-gov AND metadata_type:geospatial AND (dataset_type:dataset) AND groups:*');
//$brief = $CkanManager->exportShort('metadata-source:dms AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('organization:doj-gov AND (dataset_type:dataset)');
//    'http://uat-catalog-fe-data.reisys.com/dataset/');
//$brief = $CkanManager->exportShort('(extra_harvest_source_title:Open+*) AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('organization:gsa-gov AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('extras_harvest_source_title:Test ISO WAF AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('organization:doe-gov AND (harvest_source_title:Energy*) AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('organization:state-of-oklahoma AND (dataset_type:dataset)');
//$brief = $CkanManager->exportShort('organization:state-of-oklahoma AND -metadata_modified:[2016-02-24T23:59:59.999Z TO 2016-02-27T00:00:00Z] AND (dataset_type:dataset)');
$brief = $CkanManager->exportShort('organization:noaa-gov AND metadata-source:dms AND (dataset_type:dataset)');

$headers = array_keys($brief[array_keys($brief)[0]]);
$csv->writeRow($headers);
$csv->writeFromArray($brief);

// show running time on finish
timer();
