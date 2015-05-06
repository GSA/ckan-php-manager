<?php
/**
 * @author Alex Perfilov
 * @date   3/30/14
 *
 */

namespace CKAN\Manager;


use EasyCSV\Reader;
use EasyCSV\Writer;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd') . '_CHECK_PROD_VS_UAT_NRC';

if (!is_dir($results_dir)) {
    mkdir($results_dir);
}

echo 'prod.csv' . PHP_EOL;
if (!is_file($results_dir . '/prod.csv')) {
    $prod = new Writer($results_dir . '/prod.csv');

    $prod->writeRow([
        'title',
        'title_simple',
        'name',
        'url',
        'topics',
        'categories',
    ]);

    $ProdCkanManager = new CkanManager(CKAN_API_URL);
    $ProdCkanManager->resultsDir = $results_dir;

    $prod_nuclear = $ProdCkanManager->exportBrief('organization:(nrc-gov)' .
        ' AND -metadata_type:geospatial AND dataset_type:dataset');
    $prod->writeFromArray($prod_nuclear);
} else {
    $prod = new Reader($results_dir . '/prod.csv');
    $prod_nuclear = $prod->getAll();
}

echo 'uat.csv' . PHP_EOL;
if (!is_file($results_dir . '/uat.csv')) {
    $uat = new Writer($results_dir . '/uat.csv');

    $uat->writeRow([
        'title',
        'title_simple',
        'name',
        'url',
        'topics',
        'categories',
    ]);

    $UatCkanManager = new CkanManager(CKAN_UAT_API_URL);
    $UatCkanManager->resultsDir = $results_dir;

    $uat_nuclear = $UatCkanManager->exportBrief('extras_harvest_source_title:NRC data.json',
        'http://uat-catalog-fe-data.reisys.com/dataset/');
    $uat->writeFromArray($uat_nuclear);

} else {
    $uat = new Reader($results_dir . '/uat.csv');
    $uat_nuclear = $uat->getAll();
}

$uat_nuclear_by_title = [];

foreach ($uat_nuclear as $name => $dataset) {
    $title = $dataset['title_simple'];

    $uat_nuclear_by_title[$title] = isset($uat_nuclear_by_title[$title]) ? $uat_nuclear_by_title[$title] : [];
    $uat_nuclear_by_title[$title][] = $dataset;
}

echo 'prod_vs_uat.csv' . PHP_EOL;
is_file($results_dir . '/prod_vs_uat_nuclear_geospatial.csv') && unlink($results_dir . '/prod_vs_uat_nuclear_geospatial.csv');
$csv = new Writer($results_dir . '/prod_vs_uat_nuclear_geospatial.csv');
$csv->writeRow([
    'Prod Title',
    'Prod URL',
    'Prod Topics',
    'Prod Categories',
    'Matched',
    'UAT Title',
    'UAT URL',
]);

foreach ($prod_nuclear as $name => $prod_dataset) {
    if (isset($uat_nuclear_by_title[$prod_dataset['title_simple']])) {
        foreach ($uat_nuclear_by_title[$prod_dataset['title_simple']] as $uat_dataset) {
            $csv->writeRow([
                $prod_dataset['title'],
                $prod_dataset['url'],
                $prod_dataset['topics'],
                $prod_dataset['categories'],
                true,
                $uat_dataset['title'],
                $uat_dataset['url'],
            ]);
        }
        continue;
    }

    $csv->writeRow([
        $prod_dataset['title'],
        $prod_dataset['url'],
        $prod_dataset['topics'],
        $prod_dataset['categories'],
        false,
        '',
        '',
    ]);
}

// show running time on finish
timer();
