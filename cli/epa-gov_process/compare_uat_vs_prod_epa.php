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
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd') . '_CHECK_UAT_VS_PROD_EPA';

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

    $prod_epa = $ProdCkanManager->exportBrief('organization:epa-gov AND metadata_type:geospatial');
    $prod->writeFromArray($prod_epa);
} else {
    $prod = new Reader($results_dir . '/prod.csv');
    $prod_epa = $prod->getAll();
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

    $uat_epa = $QaCkanManager->exportBrief('organization:epa-gov AND metadata_type:geospatial', '', 'http://uat-catalog-fe-data.reisys.com/dataset/');
    $uat->writeFromArray($uat_epa);

} else {
    $uat = new Reader($results_dir . '/uat.csv');
    $uat_epa = $uat->getAll();
}

$uat_epa_by_title = $uat_epa_by_guid = [];

foreach ($uat_epa as $name => $dataset) {
    $title = $dataset['title_simple'];

    $uat_epa_by_title[$title] = isset($uat_epa_by_title[$title]) ? $uat_epa_by_title[$title] : [];
    $uat_epa_by_title[$title][] = $dataset;

    $guid = trim($dataset['guid']);
    if ($guid) {
        $uat_epa_by_guid[$guid] = isset($uat_epa_by_guid[$guid]) ? $uat_epa_by_guid[$guid] : [];
        $uat_epa_by_guid[$guid][] = $dataset;
    }
}

echo 'prod_vs_uat.csv' . PHP_EOL;
is_file($results_dir . '/prod_vs_uat_epa.csv') && unlink($results_dir . '/prod_vs_uat_epa.csv');
$csv = new Writer($results_dir . '/prod_vs_uat_epa.csv');
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

foreach ($prod_epa as $name => $prod_dataset) {
    if (isset($uat_epa_by_guid[$prod_dataset['guid']])) {
        foreach ($uat_epa_by_guid[$prod_dataset['guid']] as $uat_dataset) {
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

    if (isset($uat_epa_by_title[$prod_dataset['title_simple']])) {
        foreach ($uat_epa_by_title[$prod_dataset['title_simple']] as $uat_dataset) {
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
