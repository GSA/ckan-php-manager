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
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd') . '_CHECK_PROD_VS_QA';

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

    $prod_commerce = $ProdCkanManager->exportBrief('organization:(doc-gov OR bis-doc-gov OR mbda-doc-gov OR trade-gov OR census-gov ' .
        ' OR eda-doc-gov OR ntia-doc-gov OR ntis-gov OR nws-doc-gov OR bea-gov OR uspto-gov)' .
        ' AND -metadata_type:geospatial AND dataset_type:dataset');
    $prod->writeFromArray($prod_commerce);
} else {
    $prod = new Reader($results_dir . '/prod.csv');
    $prod_commerce = $prod->getAll();
}

echo 'qa.csv' . PHP_EOL;
if (!is_file($results_dir . '/qa.csv')) {
    $qa = new Writer($results_dir . '/qa.csv');

    $qa->writeRow([
        'title',
        'title_simple',
        'name',
        'url',
        'topics',
        'categories',
    ]);

    $UatCkanManager = new CkanManager(CKAN_QA_API_URL);
    $UatCkanManager->resultsDir = $results_dir;

    $qa_commerce = $UatCkanManager->exportBrief('organization:(doc-gov OR bis-doc-gov OR mbda-doc-gov OR trade-gov OR census-gov ' .
        ' OR eda-doc-gov OR ntia-doc-gov OR ntis-gov OR nws-doc-gov OR bea-gov OR uspto-gov)' .
        ' AND -metadata_type:geospatial AND dataset_type:dataset', '',
        'http://qa-catalog-fe-data.reisys.com/dataset/');
    $qa->writeFromArray($qa_commerce);

} else {
    $qa = new Reader($results_dir . '/qa.csv');
    $qa_commerce = $qa->getAll();
}

$qa_commerce_by_title = [];

foreach ($qa_commerce as $name => $dataset) {
    $title = $dataset['title_simple'];

    $qa_commerce_by_title[$title] = isset($qa_commerce_by_title[$title]) ? $qa_commerce_by_title[$title] : [];
    $qa_commerce_by_title[$title][] = $dataset;
}

echo 'prod_vs_qa.csv' . PHP_EOL;
is_file($results_dir . '/prod_vs_qa_commerce.csv') && unlink($results_dir . '/prod_vs_qa_commerce.csv');
$csv = new Writer($results_dir . '/prod_vs_qa_commerce.csv');
$csv->writeRow([
    'Prod Title',
    'Prod URL',
    'Prod Topics',
    'Prod Categories',
    'Matched',
    'QA Title',
    'QA URL',
    'URL Match',
]);

foreach ($prod_commerce as $name => $prod_dataset) {
    if (isset($qa_commerce_by_title[$prod_dataset['title_simple']])) {
        foreach ($qa_commerce_by_title[$prod_dataset['title_simple']] as $qa_dataset) {
            $csv->writeRow([
                $prod_dataset['title'],
                $prod_dataset['url'],
                $prod_dataset['topics'],
                $prod_dataset['categories'],
                true,
                $qa_dataset['title'],
                $qa_dataset['url'],
                true,
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
        false,
    ]);
}

// show running time on finish
timer();
