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
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd') . '_CHECK_JSON_VS_PROD_EPA';

if (!is_dir($results_dir)) {
    mkdir($results_dir);
}

echo 'json.csv' . PHP_EOL;
if (!is_file($results_dir . '/json.csv')) {
    $json = new Writer($results_dir . '/json.csv');

    $json->writeRow([
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

    $json_backup_epa = $ProdCkanManager->exportBrief('organization:epa-gov AND metadata_type:geospatial');
    $json->writeFromArray($json_backup_epa);
} else {
    $json = new Reader($results_dir . '/json.csv');
    $json_backup_epa = $json->getAll();
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

    $QaCkanManager = new CkanManager(CKAN_UAT_API_URL);
    $QaCkanManager->resultsDir = $results_dir;

    $prod_epa = $QaCkanManager->exportBrief('organization:epa-gov AND metadata_type:geospatial');
    $prod->writeFromArray($prod_epa);

} else {
    $prod = new Reader($results_dir . '/prod.csv');
    $prod_epa = $prod->getAll();
}

$prod_epa_by_title = $prod_epa_by_guid = [];

foreach ($prod_epa as $name => $dataset) {
    $title = $dataset['title_simple'];

    $prod_epa_by_title[$title] = isset($prod_epa_by_title[$title]) ? $prod_epa_by_title[$title] : [];
    $prod_epa_by_title[$title][] = $dataset;

    $guid = trim($dataset['guid']);
    if ($guid) {
        $prod_epa_by_guid[$guid] = isset($prod_epa_by_guid[$guid]) ? $prod_epa_by_guid[$guid] : [];
        $prod_epa_by_guid[$guid][] = $dataset;
    }
}

echo 'json_vs_prod.csv' . PHP_EOL;
is_file($results_dir . '/json_vs_prod_epa.csv') && unlink($results_dir . '/json_vs_prod_epa.csv');
$csv = new Writer($results_dir . '/json_vs_prod_epa.csv');
$csv->writeRow([
    'Backup Title',
    'Backup URL',
    'Backup GUID',
    'Backup Topics',
    'Backup Categories',
    'Matched',
    'Prod Title',
    'Prod URL',
    'Prod GUID',
    'URL Match',
    'GUID Match',
]);

foreach ($json_backup_epa as $name => $backup_dataset) {
    if (isset($prod_epa_by_guid[$backup_dataset['guid']])) {
        foreach ($prod_epa_by_guid[$backup_dataset['guid']] as $prod_dataset) {
            $csv->writeRow([
                $backup_dataset['title'],
                $backup_dataset['url'],
                $backup_dataset['guid'],
                $backup_dataset['topics'],
                $backup_dataset['categories'],
                true,
                $prod_dataset['title'],
                $prod_dataset['url'],
                $prod_dataset['guid'],
                (bool)($backup_dataset['name'] == $prod_dataset['name']),
                true,
            ]);
        }
        continue;
    }

    if (isset($prod_epa_by_title[$backup_dataset['title_simple']])) {
        foreach ($prod_epa_by_title[$backup_dataset['title_simple']] as $prod_dataset) {
            $csv->writeRow([
                $backup_dataset['title'],
                $backup_dataset['url'],
                $backup_dataset['guid'],
                $backup_dataset['topics'],
                $backup_dataset['categories'],
                true,
                $prod_dataset['title'],
                $prod_dataset['url'],
                $prod_dataset['guid'],
                true,
                (bool)($backup_dataset['guid'] == $prod_dataset['guid']),
            ]);
        }
        continue;
    }

    $csv->writeRow([
        $backup_dataset['title'],
        $backup_dataset['url'],
        $backup_dataset['guid'],
        $backup_dataset['topics'],
        $backup_dataset['categories'],
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
