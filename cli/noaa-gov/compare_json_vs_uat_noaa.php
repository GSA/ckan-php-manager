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
$results_dir = RESULTS_DIR . date('/Ymd') . '_CHECK_JSON_VS_UAT';

if (!is_dir($results_dir)) {
    mkdir($results_dir);
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
        'categories'
    ]);
    $UatCkanManager = new CkanManager(CKAN_UAT_API_URL);
    $UatCkanManager->resultsDir = $results_dir;

    $uat_noaa = $UatCkanManager->exportBrief('organization:noaa-gov AND extras_harvest_source_title:NOAA New CSW AND dataset_type:dataset',
        '', 'http://uat-catalog-fe-data.reisys.com/dataset/');

    file_put_contents($results_dir . '/uat.json', json_encode($uat_noaa, JSON_PRETTY_PRINT));
    $uat->writeFromArray($uat_noaa);
    echo PHP_EOL . 'datasets from prod: ' . sizeof($uat_noaa) . PHP_EOL . PHP_EOL;
} else {
    $uat_noaa = json_decode(file_get_contents($results_dir . '/uat.json'));
    echo PHP_EOL . 'datasets from uat: ' . sizeof($uat_noaa) . PHP_EOL . PHP_EOL;
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

    $json_backup_noaa = $json_backupCkanManager->exportBriefFromJson(DATA_DIR . '/noaa-gov_geospatial_with_tags.json');
    file_put_contents($results_dir . '/json_backup.json', json_encode($json_backup_noaa, JSON_PRETTY_PRINT));
    $json_backup_csv->writeFromArray($json_backup_noaa);
    echo PHP_EOL . 'datasets from json_backup: ' . sizeof($json_backup_noaa) . PHP_EOL . PHP_EOL;
} else {
    $json_backup_noaa = json_decode(file_get_contents($results_dir . '/json_backup.json'));
    echo PHP_EOL . 'datasets from json_backup: ' . sizeof($json_backup_noaa) . PHP_EOL . PHP_EOL;
}

$json_backup_tags = [];
$json_datasets = json_decode(file_get_contents(DATA_DIR . '/noaa-gov_geospatial_with_tags.json'), true);  //assoc
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

$json_backup_noaa_by_title = $json_backup_noaa_by_guid = [];

foreach ($json_backup_noaa as $name => $dataset) {
    $title = $dataset['title_simple'];

    $json_backup_noaa_by_title[$title] = isset($json_backup_noaa_by_title[$title]) ? $json_backup_noaa_by_title[$title] : [];
    $json_backup_noaa_by_title[$title][] = $dataset;

    $guid = trim($dataset['guid']);
    if ($guid) {
        $json_backup_noaa_by_guid[$guid] = isset($json_backup_noaa_by_guid[$guid]) ? $json_backup_noaa_by_guid[$guid] : [];
        $json_backup_noaa_by_guid[$guid][] = $dataset;
    }
}

echo 'prod_vs_json_backup.csv' . PHP_EOL;
is_file($results_dir . '/prod_vs_json_backup_noaa_geospatial.csv') && unlink($results_dir . '/prod_vs_json_backup_noaa_geospatial.csv');
$csv = new Writer($results_dir . '/prod_vs_json_backup_noaa_geospatial.csv');
$csv->writeRow([
    'UAT Title',
    'UAT URL',
    'UAT GUID',
    'UAT Topics',
    'UAT Categories',
    'Matched',
    'JSON Title',
    'JSON URL',
    'JSON GUID',
    'URL Match',
    'GUID Match',
]);

$csv_uat_tagging = new Writer($results_dir . '/uat_tagging.csv');
$csv_uat_tagging->writeRow(['url', 'group', 'tags']);

foreach ($uat_noaa as $name => $uat_dataset) {
    if (isset($json_backup_noaa_by_guid[$uat_dataset['guid']])) {
        foreach ($json_backup_noaa_by_guid[$uat_dataset['guid']] as $json_backup_dataset) {
            $csv->writeRow([
                $uat_dataset['title'],
                $uat_dataset['url'],
                $uat_dataset['guid'],
                $uat_dataset['topics'],
                $uat_dataset['categories'],
                true,
                $json_backup_dataset['title'],
                $json_backup_dataset['url'],
                $json_backup_dataset['guid'],
                (bool)($uat_dataset['name'] == $json_backup_dataset['name']),
                true,
            ]);
            if (isset($json_backup_tags[$json_backup_dataset['title_simple']])) {
                foreach ($json_backup_tags[$json_backup_dataset['title_simple']] as $group => $tags) {
                    $csv_uat_tagging->writeRow([
                        $uat_dataset['url'],
                        $group,
                        join(';', $tags)
                    ]);
                }
            }
        }
        continue;
    }

    if (isset($json_backup_noaa_by_title[$uat_dataset['title_simple']])) {
        foreach ($json_backup_noaa_by_title[$uat_dataset['title_simple']] as $json_backup_dataset) {
            $csv->writeRow([
                $uat_dataset['title'],
                $uat_dataset['url'],
                $uat_dataset['guid'],
                $uat_dataset['topics'],
                $uat_dataset['categories'],
                true,
                $json_backup_dataset['title'],
                $json_backup_dataset['url'],
                $json_backup_dataset['guid'],
                true,
                (bool)($uat_dataset['guid'] == $json_backup_dataset['guid']),
            ]);
            if (isset($json_backup_tags[$json_backup_dataset['title_simple']])) {
                foreach ($json_backup_tags[$json_backup_dataset['title_simple']] as $group => $tags) {
                    $csv_uat_tagging->writeRow([
                        $uat_dataset['url'],
                        $group,
                        join(';', $tags)
                    ]);
                }
            }
        }
        continue;
    }

    $csv->writeRow([
        $uat_dataset['title'],
        $uat_dataset['url'],
        $uat_dataset['guid'],
        $uat_dataset['topics'],
        $uat_dataset['categories'],
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
