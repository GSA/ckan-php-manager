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
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd') . '_CHECK_UAT_VS_PROD_NTSB';

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

    $prod_ntsb = $ProdCkanManager->exportBrief('organization:ntsb-gov AND dataset_type:dataset');
    $prod->writeFromArray($prod_ntsb);
} else {
    $prod = new Reader($results_dir . '/prod.csv');
    $prod_ntsb = $prod->getAll();
}

echo 'uat.csv' . PHP_EOL;
if (!is_file($results_dir . '/uat.csv')) {
    $uat = new Writer($results_dir . '/uat.csv');

    $uat->writeRow([
        'title',
        'title_simple',
        'name',
        'url',
        'guid',
        'topics',
        'categories',
    ]);

    $QaCkanManager = new CkanManager(CKAN_UAT_API_URL);
    $QaCkanManager->resultsDir = $results_dir;

    $uat_ntsb = $QaCkanManager->exportBrief('organization:ntsb-gov AND (harvest_source_title:NTSB*) AND dataset_type:dataset',
        '', 'http://uat-catalog-fe-data.reisys.com/dataset/');
    $uat->writeFromArray($uat_ntsb);

} else {
    $uat = new Reader($results_dir . '/uat.csv');
    $uat_ntsb = $uat->getAll();
}

$uat_ntsb_by_title = $uat_ntsb_by_guid = [];

foreach ($uat_ntsb as $name => $dataset) {
    $title = $dataset['title_simple'];

    $uat_ntsb_by_title[$title] = isset($uat_ntsb_by_title[$title]) ? $uat_ntsb_by_title[$title] : [];
    $uat_ntsb_by_title[$title][] = $dataset;

    $guid = trim($dataset['guid']);
    if ($guid) {
        $uat_ntsb_by_guid[$guid] = isset($uat_ntsb_by_guid[$guid]) ? $uat_ntsb_by_guid[$guid] : [];
        $uat_ntsb_by_guid[$guid][] = $dataset;
    }
}

echo 'prod_vs_uat.csv' . PHP_EOL;
is_file($results_dir . '/prod_vs_uat_ntsb.csv') && unlink($results_dir . '/prod_vs_uat_ntsb.csv');
$csv = new Writer($results_dir . '/prod_vs_uat_ntsb.csv');
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

$matched = [];

foreach ($prod_ntsb as $name => $prod_dataset) {
    if (isset($uat_ntsb_by_guid[$prod_dataset['guid']])) {
        foreach ($uat_ntsb_by_guid[$prod_dataset['guid']] as $uat_dataset) {
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
            $matched[] = $uat_dataset['title_simple'];
        }
        continue;
    }

    if (isset($uat_ntsb_by_title[$prod_dataset['title_simple']])) {
        foreach ($uat_ntsb_by_title[$prod_dataset['title_simple']] as $uat_dataset) {
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
            $matched[] = $uat_dataset['title_simple'];
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

foreach ($uat_ntsb as $name => $uat_dataset) {
    if (!in_array($uat_dataset['title_simple'], $matched)) {
        $csv->writeRow([
            '',
            '',
            '',
            '',
            '',
            false,
            $uat_dataset['title'],
            $uat_dataset['url'],
            $uat_dataset['guid'],
            false,
            false,
        ]);
    }
}

// show running time on finish
timer();
