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
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd') . '_CHECK_PROD_VS_PROD';

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
        ' AND -metadata_type:geospatial AND dataset_type:dataset AND -harvest_source_id:[\'\' TO *]');
    $prod->writeFromArray($prod_commerce);
} else {
    $prod = new Reader($results_dir . '/prod.csv');
    $prod_commerce = $prod->getAll();
}

echo 'new.csv' . PHP_EOL;
if (!is_file($results_dir . '/new.csv')) {
    $new = new Writer($results_dir . '/new.csv');

    $new->writeRow([
        'title',
        'title_simple',
        'name',
        'url',
        'topics',
        'categories',
    ]);

    $UatCkanManager = new CkanManager(CKAN_API_URL);
    $UatCkanManager->resultsDir = $results_dir;

    $new_commerce = $UatCkanManager->exportBrief('extras_harvest_source_title:Commerce Non Spatial Data.json Harvest Source');
    $new->writeFromArray($new_commerce);

} else {
    $new = new Reader($results_dir . '/new.csv');
    $new_commerce = $new->getAll();
}

$new_commerce_by_title = [];

foreach ($new_commerce as $name => $dataset) {
    $title = $dataset['title_simple'];

    $new_commerce_by_title[$title] = isset($new_commerce_by_title[$title]) ? $new_commerce_by_title[$title] : [];
    $new_commerce_by_title[$title][] = $dataset;
}

echo 'prod_vs_new.csv' . PHP_EOL;
is_file($results_dir . '/prod_vs_prod_commerce.csv') && unlink($results_dir . '/prod_vs_prod_commerce.csv');
$csv = new Writer($results_dir . '/prod_vs_prod_commerce.csv');
$csv->writeRow([
    'Prod Title',
    'Prod URL',
    'Prod Topics',
    'Prod Categories',
    'Matched',
    'NEW Title',
    'NEW URL',
    'URL Match',
]);

foreach ($prod_commerce as $name => $prod_dataset) {
    if (isset($new_commerce_by_title[$prod_dataset['title_simple']])) {
        foreach ($new_commerce_by_title[$prod_dataset['title_simple']] as $new_dataset) {
            $csv->writeRow([
                $prod_dataset['title'],
                $prod_dataset['url'],
                $prod_dataset['topics'],
                $prod_dataset['categories'],
                true,
                $new_dataset['title'],
                $new_dataset['url'],
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
