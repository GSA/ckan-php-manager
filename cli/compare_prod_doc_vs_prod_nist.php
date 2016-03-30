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
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd') . '_CHECK_PROD_NIST_VS_DOC';

if (!is_dir($results_dir)) {
    mkdir($results_dir);
}

echo 'nist.csv' . PHP_EOL;
if (!is_file($results_dir . '/nist.csv')) {
    $nist = new Writer($results_dir . '/nist.csv');

    $nist->writeRow([
        'title',
        'title_simple',
        'name',
        'url',
        'topics',
        'categories',
    ]);

    $ProdNistCkanManager = new CkanManager(CKAN_API_URL);
    $ProdNistCkanManager->resultsDir = $results_dir;

    $prod_nist = $ProdNistCkanManager->exportBrief('organization:(nist-gov) AND dataset_type:dataset');
    $nist->writeFromArray($prod_nist);
} else {
    $nist = new Reader($results_dir . '/nist.csv');
    $prod_nist = $nist->getAll();
}

echo 'doc.csv' . PHP_EOL;
if (!is_file($results_dir . '/doc.csv')) {
    $doc = new Writer($results_dir . '/doc.csv');

    $doc->writeRow([
        'title',
        'title_simple',
        'name',
        'url',
        'topics',
        'categories',
    ]);

    $ProdDocCkanManager = new CkanManager(CKAN_API_URL);
    $ProdDocCkanManager->resultsDir = $results_dir;

    $prod_doc = $ProdDocCkanManager->exportBrief('organization:(doc-gov) AND dataset_type:dataset');
    $doc->writeFromArray($prod_doc);

} else {
    $doc = new Reader($results_dir . '/doc.csv');
    $prod_doc = $doc->getAll();
}

$prod_doc_by_title = [];

foreach ($prod_doc as $name => $dataset) {
    $title = $dataset['title_simple'];

    $prod_doc_by_title[$title] = isset($prod_doc_by_title[$title]) ? $prod_doc_by_title[$title] : [];
    $prod_doc_by_title[$title][] = $dataset;
}

echo 'nist_vs_doc.csv' . PHP_EOL;
is_file($results_dir . '/nist_vs_doc.csv') && unlink($results_dir . '/nist_vs_doc.csv');
$csv = new Writer($results_dir . '/nist_vs_doc.csv');
$csv->writeRow([
    'NIST Title',
    'NIST URL',
    'NIST Topics',
    'NIST Categories',
    'Matched',
    'DOC Title',
    'DOC URL',
    'URL Match',
]);

foreach ($prod_nist as $name => $prod_nist_dataset) {
    if (isset($prod_doc_by_title[$prod_nist_dataset['title_simple']])) {
        foreach ($prod_doc_by_title[$prod_nist_dataset['title_simple']] as $prod_doc_dataset) {
            $csv->writeRow([
                $prod_nist_dataset['title'],
                $prod_nist_dataset['url'],
                $prod_nist_dataset['topics'],
                $prod_nist_dataset['categories'],
                true,
                $prod_doc_dataset['title'],
                $prod_doc_dataset['url'],
                true,
            ]);
        }
        continue;
    }

    $csv->writeRow([
        $prod_nist_dataset['title'],
        $prod_nist_dataset['url'],
        $prod_nist_dataset['topics'],
        $prod_nist_dataset['categories'],
        false,
        '',
        '',
        false,
    ]);
}

// show running time on finish
timer();
