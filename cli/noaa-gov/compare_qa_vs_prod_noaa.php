<?php
/**
 * @author Alex Perfilov
 * @date   3/30/14
 *
 */

namespace CKAN\Manager;


use EasyCSV\Reader;
use EasyCSV\Writer;

require_once dirname(dirname(__DIR__)) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd') . '_CHECK_QA_VS_PROD';

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
    $prod->writeFromArray($prod_noaa);
    file_put_contents($results_dir . '/prod.json', json_encode($prod_noaa, JSON_PRETTY_PRINT));
} else {
    $prod_noaa = json_decode(file_get_contents($results_dir . '/prod.json'));
}

echo 'qa.json' . PHP_EOL;
if (!is_file($results_dir . '/qa.json')) {
    $qa = new Writer($results_dir . '/qa.csv');

    $qa->writeRow([
        'title',
        'title_simple',
        'name',
        'url',
        'identifier',
        'guid',
        'topics',
        'categories',
    ]);
    $QaCkanManager = new CkanManager(CKAN_QA_API_URL);
    $QaCkanManager->resultsDir = $results_dir;

    $qa_noaa = $QaCkanManager->exportBrief('organization:noaa-gov', '',
        'http://qa-catalog-fe-data.reisys.com/dataset/');
    $qa->writeFromArray($qa_noaa);
    file_put_contents($results_dir . '/qa.json', json_encode($qa_noaa, JSON_PRETTY_PRINT));
} else {
    $qa_noaa = json_decode(file_get_contents($results_dir . '/qa.json'));
}

$qa_noaa_by_title = $qa_noaa_by_guid = [];

foreach ($qa_noaa as $name => $dataset) {
    $title = $dataset['title_simple'];

    $qa_noaa_by_title[$title] = isset($qa_noaa_by_title[$title]) ? $qa_noaa_by_title[$title] : [];
    $qa_noaa_by_title[$title][] = $dataset;

    $guid = trim($dataset['guid']);
    if ($guid) {
        $qa_noaa_by_guid[$guid] = isset($qa_noaa_by_guid[$guid]) ? $qa_noaa_by_guid[$guid] : [];
        $qa_noaa_by_guid[$guid][] = $dataset;
    }
}

echo 'prod_vs_qa.csv' . PHP_EOL;
is_file($results_dir . '/prod_vs_qa_noaa_geospatial.csv') && unlink($results_dir . '/prod_vs_qa_noaa_geospatial.csv');
$csv = new Writer($results_dir . '/prod_vs_qa_noaa_geospatial.csv');
$csv->writeRow([
    'Prod Title',
    'Prod URL',
    'Prod GUID',
    'Prod Topics',
    'Prod Categories',
    'Matched',
    'QA Title',
    'QA URL',
    'QA GUID',
    'URL Match',
    'GUID Match',
]);

foreach ($prod_noaa as $name => $prod_dataset) {
    if (isset($qa_noaa_by_guid[$prod_dataset['guid']])) {
        foreach ($qa_noaa_by_guid[$prod_dataset['guid']] as $qa_dataset) {
            $csv->writeRow([
                $prod_dataset['title'],
                $prod_dataset['url'],
                $prod_dataset['guid'],
                $prod_dataset['topics'],
                $prod_dataset['categories'],
                true,
                $qa_dataset['title'],
                $qa_dataset['url'],
                $qa_dataset['guid'],
                (bool)($prod_dataset['name'] == $qa_dataset['name']),
                true,
            ]);
        }
        continue;
    }

    if (isset($qa_noaa_by_title[$prod_dataset['title_simple']])) {
        foreach ($qa_noaa_by_title[$prod_dataset['title_simple']] as $qa_dataset) {
            $csv->writeRow([
                $prod_dataset['title'],
                $prod_dataset['url'],
                $prod_dataset['guid'],
                $prod_dataset['topics'],
                $prod_dataset['categories'],
                true,
                $qa_dataset['title'],
                $qa_dataset['url'],
                $qa_dataset['guid'],
                true,
                (bool)($prod_dataset['guid'] == $qa_dataset['guid']),
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
