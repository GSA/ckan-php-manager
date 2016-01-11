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
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd') . '_CHECK_JSON_VS_PROD';

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
        'categories'
    ]);
    $ProdCkanManager = new CkanManager(CKAN_API_URL);
    $ProdCkanManager->resultsDir = $results_dir;

    $prod_epa = $ProdCkanManager->exportBrief('organization:epa-gov AND metadata_type:geospatial AND dataset_type:dataset');
    file_put_contents($results_dir . '/prod.json', json_encode($prod_epa, JSON_PRETTY_PRINT));
    $prod->writeFromArray($prod_epa);
    echo PHP_EOL . 'datasets from prod: ' . sizeof($prod_epa) . PHP_EOL . PHP_EOL;
} else {
    $prod_epa = json_decode(file_get_contents($results_dir . '/prod.json'));
    echo PHP_EOL . 'datasets from prod: ' . sizeof($prod_epa) . PHP_EOL . PHP_EOL;
}

echo 'json_backup.json' . PHP_EOL;
if (!is_file($results_dir . '/json_backup.json')) {
    $json_backup_csv = new Writer($results_dir . '/json_backup.csv');

    $json_backup_csv->writeRow([
        'title',
        'title_simple',
        'name',
        'url',
        'identifier',
        'guid',
        'topics',
        'categories'
    ]);
    $json_backupCkanManager = new CkanManager(CKAN_UAT_API_URL);
    $json_backupCkanManager->resultsDir = $results_dir;

    $json_backup_epa = $json_backupCkanManager->exportBriefFromJson(CKANMNGR_DATA_DIR . '/epa-gov.json');
    file_put_contents($results_dir . '/json_backup.json', json_encode($json_backup_epa, JSON_PRETTY_PRINT));
    $json_backup_csv->writeFromArray($json_backup_epa);
    echo PHP_EOL . 'datasets from json_backup: ' . sizeof($json_backup_epa) . PHP_EOL . PHP_EOL;
} else {
    $json_backup_epa = json_decode(file_get_contents($results_dir . '/json_backup.json'));
    echo PHP_EOL . 'datasets from json_backup: ' . sizeof($json_backup_epa) . PHP_EOL . PHP_EOL;
}

$json_backup_tags = [];
$json_datasets = json_decode(file_get_contents(CKANMNGR_DATA_DIR . '/epa-gov.json'), true);  //assoc
foreach ($json_datasets as $dataset_array) {
    $dataset = new Dataset($dataset_array);
    $groups_tags = $dataset->get_groups_and_tags();
    if (!$groups_tags) {
        unset($dataset);
        continue;
    }
    $title_simple = Dataset::simplifyTitle($dataset_array['title']);
    if (!isset($json_backup_tags[$title_simple])) {
        $json_backup_tags[$title_simple] = [];
    }
    foreach ($groups_tags as $group => $tags) {
        if (isset($json_backup_tags[$title_simple][$group])) {
            $json_backup_tags[$title_simple][$group] = array_merge($json_backup_tags[$title_simple][$group], $tags);
        } else {
            $json_backup_tags[$title_simple][$group] = $tags;
        }
    }
    unset($dataset);
}

$json_backup_epa_by_title = $json_backup_epa_by_guid = [];

foreach ($json_backup_epa as $name => $dataset) {
    $title = $dataset['title_simple'];

    $json_backup_epa_by_title[$title] = isset($json_backup_epa_by_title[$title]) ? $json_backup_epa_by_title[$title] : [];
    $json_backup_epa_by_title[$title][] = $dataset;

    $guid = trim($dataset['guid']);
    if ($guid) {
        $json_backup_epa_by_guid[$guid] = isset($json_backup_epa_by_guid[$guid]) ? $json_backup_epa_by_guid[$guid] : [];
        $json_backup_epa_by_guid[$guid][] = $dataset;
    }
}

echo 'prod_vs_json_backup.csv' . PHP_EOL;
is_file($results_dir . '/prod_vs_json_backup_epa_geospatial.csv') && unlink($results_dir . '/prod_vs_json_backup_epa_geospatial.csv');
$csv = new Writer($results_dir . '/prod_vs_json_backup_epa_geospatial.csv');
$csv->writeRow([
    'Prod Title',
    'Prod URL',
    'Prod GUID',
    'Prod Topics',
    'Prod Categories',
    'Matched',
    'JSON Title',
    'JSON URL',
    'JSON GUID',
    'URL Match',
    'Title Match',
    'GUID Match',
]);

$csv_prod_tagging = new Writer($results_dir . '/prod_tagging.csv');
$csv_prod_tagging->writeRow(['url', 'group', 'tags', 'old_url', 'new_title', 'old_title', 'match_by']);

foreach ($prod_epa as $name => $prod_dataset) {
    if (isset($json_backup_epa_by_guid[$prod_dataset['guid']])) {
        foreach ($json_backup_epa_by_guid[$prod_dataset['guid']] as $json_backup_dataset) {
            $csv->writeRow([
                $prod_dataset['title'],
                $prod_dataset['url'],
                $prod_dataset['guid'],
                $prod_dataset['topics'],
                $prod_dataset['categories'],
                true,
                $json_backup_dataset['title'],
                $json_backup_dataset['url'],
                $json_backup_dataset['guid'],
                (bool)($prod_dataset['name'] && $prod_dataset['name'] == $json_backup_dataset['name']),
                (bool)($prod_dataset['title_simple'] && $prod_dataset['title_simple'] == $json_backup_dataset['title_simple']),
                true,
            ]);
            if (isset($json_backup_tags[$json_backup_dataset['title_simple']])) {
                foreach ($json_backup_tags[$json_backup_dataset['title_simple']] as $group => $tags) {
                    $csv_prod_tagging->writeRow([
                        $prod_dataset['url'],
                        $group,
                        join(';', $tags),
                        $json_backup_dataset['name'],
                        $prod_dataset['title_simple'],
                        $json_backup_dataset['title_simple'],
                        'guid: '.$prod_dataset['guid']
                    ]);
                }
            }
        }
        continue;
    }

    if (isset($json_backup_epa_by_title[$prod_dataset['title_simple']])) {
        foreach ($json_backup_epa_by_title[$prod_dataset['title_simple']] as $json_backup_dataset) {
            $csv->writeRow([
                $prod_dataset['title'],
                $prod_dataset['url'],
                $prod_dataset['guid'],
                $prod_dataset['topics'],
                $prod_dataset['categories'],
                true,
                $json_backup_dataset['title'],
                $json_backup_dataset['url'],
                $json_backup_dataset['guid'],
                (bool)($prod_dataset['name'] && $prod_dataset['name'] == $json_backup_dataset['name']),
                true,
                (bool)($prod_dataset['guid'] == $json_backup_dataset['guid']),
            ]);
            if (isset($json_backup_tags[$json_backup_dataset['title_simple']])) {
                foreach ($json_backup_tags[$json_backup_dataset['title_simple']] as $group => $tags) {
                    $csv_prod_tagging->writeRow([
                        $prod_dataset['url'],
                        $group,
                        join(';', $tags),
                        $json_backup_dataset['name'],
                        $prod_dataset['title_simple'],
                        $json_backup_dataset['title_simple'],
                        'title'
                    ]);
                }
            }
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
