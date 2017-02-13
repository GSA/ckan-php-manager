<?php

namespace CKAN\Manager;

use EasyCSV\Writer;

require_once dirname(dirname(__DIR__)) . '/inc/common.php';

/**
 * Create results dir for logs and json results
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_EXPORT_FAA';
mkdir($results_dir);

$CkanManager = new CkanManager(CKAN_API_URL);

$csv = new Writer($results_dir . '/export.faa.' . date('Y-m-d') . '.csv');

$CkanManager->resultsDir = $results_dir;

$brief = $CkanManager->exportShort('organization:dot-gov AND (dataset_type:dataset) AND publisher:"Federal Aviation Administration"');

$headers = array_keys($brief[array_keys($brief)[0]]);
$csv->writeRow($headers);
$csv->writeFromArray($brief);

// show running time on finish
timer();
