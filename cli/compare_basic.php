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
$results_dir = RESULTS_DIR . date('/Ymd') . '_COMPARE_BASIC';

if (!is_dir($results_dir)) {
    mkdir($results_dir);
}

echo 'cmp1.csv' . PHP_EOL;
if (!is_file($results_dir . '/cmp1.csv')) {
    $cmp1_csv = new Writer($results_dir . '/cmp1.csv');

    $cmp1_csv->writeRow([
        'title',
        'title_simple',
        'name',
        'url',
        'identifier',
        'guid',
        'topics',
        'categories',
    ]);

    $CkanManager = new CkanManager(CKAN_API_URL);
    $CkanManager->resultsDir = $results_dir;

    $cmp1 = $CkanManager->exportBrief('organization:((eop-gov) OR (omb-eop-gov) OR (ondcp-eop-gov) OR (ceq-eop-gov) ' .
        'OR (ostp-eop-gov) OR (ustr-eop-gov) OR (wh-eop-gov)) DMS  AND dataset_type:dataset');
    $cmp1_csv->writeFromArray($cmp1);
} else {
    $cmp1_csv = new Reader($results_dir . '/cmp1.csv');
    $cmp1_csv->getHeaders();
    $cmp1 = $cmp1_csv->getAll();
}

echo 'cmp2.csv' . PHP_EOL;
if (!is_file($results_dir . '/cmp2.csv')) {
    $cmp2_csv = new Writer($results_dir . '/cmp2.csv');

    $cmp2_csv->writeRow([
        'title',
        'title_simple',
        'name',
        'url',
        'identifier',
        'guid',
        'topics',
        'categories',
    ]);

    $CkanManager = new CkanManager(CKAN_API_URL);
    $CkanManager->resultsDir = $results_dir;

    $cmp2 = $CkanManager->exportBrief('organization:((eop-gov) OR (omb-eop-gov) OR (ondcp-eop-gov) OR (ceq-eop-gov) ' .
        'OR (ostp-eop-gov) OR (ustr-eop-gov) OR (wh-eop-gov)) -DMS AND dataset_type:dataset');
    $cmp2_csv->writeFromArray($cmp2);

} else {
    $cmp2_csv = new Reader($results_dir . '/cmp2.csv');
    $cmp2 = $cmp2_csv->getAll();
}

$cmp2_by_title = $cmp2_by_guid = [];

foreach ($cmp2 as $name => $dataset) {
    $title = $dataset['title_simple'];

    $cmp2_by_title[$title] = isset($cmp2_by_title[$title]) ? $cmp2_by_title[$title] : [];
    $cmp2_by_title[$title][] = $dataset;

    $guid = trim($dataset['guid']);
    if ($guid) {
        $cmp2_by_guid[$guid] = isset($cmp2_by_guid[$guid]) ? $cmp2_by_guid[$guid] : [];
        $cmp2_by_guid[$guid][] = $dataset;
    }
}

echo 'comparison.csv' . PHP_EOL;
is_file($results_dir . '/comparison.csv') && unlink($results_dir . '/comparison.csv');
$csv = new Writer($results_dir . '/comparison.csv');
$cmp1_header = "DMS";
$cmp2_header = "NON-DMS";
$csv->writeRow([
    $cmp1_header . ' Title',
    $cmp1_header . ' URL',
    $cmp1_header . ' GUID',
    $cmp1_header . ' Topics',
    $cmp1_header . ' Categories',
    'Matched',
    $cmp2_header . ' Title',
    $cmp2_header . ' URL',
    $cmp2_header . ' GUID',
    'URL Match',
    'GUID Match',
]);

foreach ($cmp1 as $name => $cmp1_dataset) {
    if (isset($cmp2_by_guid[$cmp1_dataset['guid']])) {
        foreach ($cmp2_by_guid[$cmp1_dataset['guid']] as $cmp2_dataset) {
            $csv->writeRow([
                $cmp1_dataset['title'],
                $cmp1_dataset['url'],
                $cmp1_dataset['guid'],
                $cmp1_dataset['topics'],
                $cmp1_dataset['categories'],
                true,
                $cmp2_dataset['title'],
                $cmp2_dataset['url'],
                $cmp2_dataset['guid'],
                (bool)($cmp1_dataset['name'] && $cmp1_dataset['name'] == $cmp2_dataset['name']),
                true,
            ]);
        }
        continue;
    }

    if (isset($cmp2_by_title[$cmp1_dataset['title_simple']])) {
        foreach ($cmp2_by_title[$cmp1_dataset['title_simple']] as $cmp2_dataset) {
            $csv->writeRow([
                $cmp1_dataset['title'],
                $cmp1_dataset['url'],
                $cmp1_dataset['guid'],
                $cmp1_dataset['topics'],
                $cmp1_dataset['categories'],
                true,
                $cmp2_dataset['title'],
                $cmp2_dataset['url'],
                $cmp2_dataset['guid'],
                true,
                (bool)($cmp1_dataset['guid'] && $cmp1_dataset['guid'] == $cmp2_dataset['guid']),
            ]);
        }
        continue;
    }

    $csv->writeRow([
        $cmp1_dataset['title'],
        $cmp1_dataset['url'],
        $cmp1_dataset['guid'],
        $cmp1_dataset['topics'],
        $cmp1_dataset['categories'],
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
