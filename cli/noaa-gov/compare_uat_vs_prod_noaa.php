<?php
/**
 * @author Alex Perfilov
 * @date   3/30/14
 *
 */

namespace CKAN\Manager;


use EasyCSV\Writer;

require_once dirname(dirname(__DIR__)) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd') . '_CHECK_UAT_VS_PROD';

if (!is_dir($results_dir)) {
    mkdir($results_dir);
}

echo 'prod.json' . PHP_EOL;
if (!is_file($results_dir . '/prod.json')) {
    $prod = new Writer($results_dir . '/prod.csv');

    $prod->writeRow([
        'title',
        'title_simple',
        'name',
        'url',
        'identifier',
        'guid',
        'topics',
        'categories',
    ]);
    $ProdCkanManager = new CkanManager(CKAN_API_URL);
    $ProdCkanManager->resultsDir = $results_dir;

    $prod_noaa = $ProdCkanManager->exportBrief('organization:noaa-gov AND metadata_type:geospatial AND dataset_type:dataset');
    file_put_contents($results_dir . '/prod.json', json_encode($prod_noaa, JSON_PRETTY_PRINT));
    $prod->writeFromArray($prod_noaa);
    echo PHP_EOL.'datasets from prod: '.sizeof($prod_noaa).PHP_EOL.PHP_EOL;
} else {
    $prod_noaa = json_decode(file_get_contents($results_dir . '/prod.json'));
    echo PHP_EOL.'datasets from prod: '.sizeof($prod_noaa).PHP_EOL.PHP_EOL;
}

echo 'uat.json' . PHP_EOL;
if (!is_file($results_dir . '/uat.json')) {
    $uat = new Writer($results_dir . '/uat.csv');

    $uat->writeRow([
        'title',
        'title_simple',
        'name',
        'url',
        'identifier',
        'guid',
        'topics',
        'categories',
    ]);
    $uatCkanManager = new CkanManager(CKAN_UAT_API_URL);
    $uatCkanManager->resultsDir = $results_dir;

    $uat_noaa = $uatCkanManager->exportBrief('organization:noaa-gov AND extras_harvest_source_title:NOAA New CSW AND dataset_type:dataset',
        '', 'http://uat-catalog-fe-data.reisys.com/dataset/');
    file_put_contents($results_dir . '/uat.json', json_encode($uat_noaa, JSON_PRETTY_PRINT));
    $uat->writeFromArray($uat_noaa);
    echo PHP_EOL.'datasets from uat: '.sizeof($uat_noaa).PHP_EOL.PHP_EOL;
} else {
    $uat_noaa = json_decode(file_get_contents($results_dir . '/uat.json'));
    echo PHP_EOL.'datasets from uat: '.sizeof($uat_noaa).PHP_EOL.PHP_EOL;
}

$uat_noaa_by_title = $uat_noaa_by_guid = [];

foreach ($uat_noaa as $name => $dataset) {
    $title = $dataset['title_simple'];

    $uat_noaa_by_title[$title] = isset($uat_noaa_by_title[$title]) ? $uat_noaa_by_title[$title] : [];
    $uat_noaa_by_title[$title][] = $dataset;

    $guid = trim($dataset['guid']);
    if ($guid) {
        $uat_noaa_by_guid[$guid] = isset($uat_noaa_by_guid[$guid]) ? $uat_noaa_by_guid[$guid] : [];
        $uat_noaa_by_guid[$guid][] = $dataset;
    }
}

echo 'prod_vs_uat.csv' . PHP_EOL;
is_file($results_dir . '/prod_vs_uat_noaa_geospatial.csv') && unlink($results_dir . '/prod_vs_uat_noaa_geospatial.csv');
$csv = new Writer($results_dir . '/prod_vs_uat_noaa_geospatial.csv');
$csv->writeRow([
    'Prod Title',
    'Prod URL',
    'Prod GUID',
    'Prod Topics',
    'Prod Categories',
    'Matched',
    'UAT Title',
    'UAT URL',
    'UAT GUID',
    'URL Match',
    'GUID Match',
]);

foreach ($prod_noaa as $name => $prod_dataset) {
    if (isset($uat_noaa_by_guid[$prod_dataset['guid']])) {
        foreach ($uat_noaa_by_guid[$prod_dataset['guid']] as $uat_dataset) {
            $csv->writeRow([
                $prod_dataset['title'],
                $prod_dataset['url'],
                $prod_dataset['guid'],
                $prod_dataset['topics'],
                $prod_dataset['categories'],
                true,
                $uat_dataset['title'],
                $uat_dataset['url'],
                $uat_dataset['guid'],
                (bool)($prod_dataset['name'] == $uat_dataset['name']),
                true,
            ]);
        }
        continue;
    }

    if (isset($uat_noaa_by_title[$prod_dataset['title_simple']])) {
        foreach ($uat_noaa_by_title[$prod_dataset['title_simple']] as $uat_dataset) {
            $csv->writeRow([
                $prod_dataset['title'],
                $prod_dataset['url'],
                $prod_dataset['guid'],
                $prod_dataset['topics'],
                $prod_dataset['categories'],
                true,
                $uat_dataset['title'],
                $uat_dataset['url'],
                $uat_dataset['guid'],
                true,
                (bool)($prod_dataset['guid'] == $uat_dataset['guid']),
            ]);
        }
        continue;
    }

    $csv->writeRow([
        $prod_dataset['title'],
        $prod_dataset['url'],
        $prod_dataset['guid'],
        $prod_dataset['topics'],
        $prod_dataset['categories'],
        false,
        '',
        '',
        '',
        false,
        false,
    ]);
}

// show running time on finish
timer();
