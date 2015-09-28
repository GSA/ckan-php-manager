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
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd') . '_CHECK_QA_VS_PROD_EPA';

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
        'guid',
        'topics',
        'categories',
    ]);

    $ProdCkanManager = new CkanManager(CKAN_API_URL);
    $ProdCkanManager->resultsDir = $results_dir;

    $prod_epa = $ProdCkanManager->exportBrief('organization:epa-gov');
    $prod->writeFromArray($prod_epa);
} else {
    $prod = new Reader($results_dir . '/prod.csv');
    $prod_epa = $prod->getAll();
}

echo 'qa.csv' . PHP_EOL;
if (!is_file($results_dir . '/qa.csv')) {
    $qa = new Writer($results_dir . '/qa.csv');

    $qa->writeRow([
        'title',
        'title_simple',
        'name',
        'url',
        'guid',
        'topics',
        'categories',
    ]);

    $QaCkanManager = new CkanManager(CKAN_QA_API_URL);
    $QaCkanManager->resultsDir = $results_dir;

    $qa_epa = $QaCkanManager->exportBrief('organization:epa-gov', '', 'http://qa-catalog-fe-data.reisys.com/dataset/');
    $qa->writeFromArray($qa_epa);

} else {
    $qa = new Reader($results_dir . '/qa.csv');
    $qa_epa = $qa->getAll();
}

$qa_epa_by_title = $qa_epa_by_guid = [];

foreach ($qa_epa as $name => $dataset) {
    $title = $dataset['title_simple'];

    $qa_epa_by_title[$title] = isset($qa_epa_by_title[$title]) ? $qa_epa_by_title[$title] : [];
    $qa_epa_by_title[$title][] = $dataset;

    $guid = trim($dataset['guid']);
    if ($guid) {
        $qa_epa_by_guid[$guid] = isset($qa_epa_by_guid[$guid]) ? $qa_epa_by_guid[$guid] : [];
        $qa_epa_by_guid[$guid][] = $dataset;
    }
}

echo 'prod_vs_qa.csv' . PHP_EOL;
is_file($results_dir . '/prod_vs_qa_epa.csv') && unlink($results_dir . '/prod_vs_qa_epa.csv');
$csv = new Writer($results_dir . '/prod_vs_qa_epa.csv');
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

foreach ($prod_epa as $name => $prod_dataset) {
    if (isset($qa_epa_by_guid[$prod_dataset['guid']])) {
        foreach ($qa_epa_by_guid[$prod_dataset['guid']] as $qa_dataset) {
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

    if (isset($qa_epa_by_title[$prod_dataset['title_simple']])) {
        foreach ($qa_epa_by_title[$prod_dataset['title_simple']] as $qa_dataset) {
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
