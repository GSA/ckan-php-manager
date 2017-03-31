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
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd') . '_CHECK_PROD_VS_PROD';

if (!is_dir($results_dir)) {
    mkdir($results_dir);
}

$prod1_org = 'fs-fed-us';
$prod2_org = 'usda-gov';

$prod1_csv_path = $results_dir . '/' . $prod1_org . '.csv';
$prod2_csv_path = $results_dir . '/' . $prod2_org . '.csv';

$comparison_csv_path = $results_dir . '/' . $prod1_org . '_VS_' . $prod2_org . '.csv';

echo $prod1_org . '.csv' . PHP_EOL;
if (!is_file($prod1_csv_path)) {
    $prod1 = new Writer($prod1_csv_path);

    $prod1->writeRow([
        'title',
        'title_simple',
        'name',
        'url',
        'topics',
        'categories',
    ]);

    $Prod1CkanManager = new CkanManager(CKAN_API_URL);
    $Prod1CkanManager->resultsDir = $results_dir;

    $prod1_data = $Prod1CkanManager->exportBrief('organization:(' . $prod1_org . ') AND dataset_type:dataset');
    $prod1->writeFromArray($prod1_data);
} else {
    $prod1 = new Reader($prod1_csv_path);
    $prod1_data = $prod1->getAll();
}

echo $prod2_org . '.csv' . PHP_EOL;
if (!is_file($prod2_csv_path)) {
    $prod2 = new Writer($prod2_csv_path);

    $prod2->writeRow([
        'title',
        'title_simple',
        'name',
        'url',
        'topics',
        'categories',
    ]);

    $Prod2CkanManager = new CkanManager(CKAN_API_URL);
    $Prod2CkanManager->resultsDir = $results_dir;

    $prod2_data = $Prod2CkanManager->exportBrief('organization:(' . $prod2_org . ') AND dataset_type:dataset');
    $prod2->writeFromArray($prod2_data);

} else {
    $prod2 = new Reader($prod2_csv_path);
    $prod2_data = $prod2->getAll();
}


$prod2_by_title = [];

foreach ($prod2_data as $name => $dataset) {
    $title = $dataset['title_simple'];

    $prod2_by_title[$title] = isset($prod2_by_title[$title]) ? $prod2_by_title[$title] : [];
    $prod2_by_title[$title][] = $dataset;
}

echo $prod1_org . '_VS_' . $prod2_org . '.csv' . PHP_EOL;
is_file($comparison_csv_path) && unlink($comparison_csv_path);
$csv = new Writer($comparison_csv_path);
$csv->writeRow([
    $prod1_org . ' Title',
    $prod1_org . ' URL',
    $prod1_org . ' Topics',
    $prod1_org . ' Categories',
    'Matched',
    $prod2_org . ' Title',
    $prod2_org . ' URL',
    'URL Match',
]);

foreach ($prod1_data as $name => $prod1_dataset) {
    if (isset($prod2_by_title[$prod1_dataset['title_simple']])) {
        foreach ($prod2_by_title[$prod1_dataset['title_simple']] as $prod2_dataset) {
            $csv->writeRow([
                $prod1_dataset['title'],
                $prod1_dataset['url'],
                $prod1_dataset['topics'],
                $prod1_dataset['categories'],
                true,
                $prod2_dataset['title'],
                $prod2_dataset['url'],
                true,
            ]);
        }
        continue;
    }

    $csv->writeRow([
        $prod1_dataset['title'],
        $prod1_dataset['url'],
        $prod1_dataset['topics'],
        $prod1_dataset['categories'],
        false,
        '',
        '',
        false,
    ]);
}

// show running time on finish
timer();
