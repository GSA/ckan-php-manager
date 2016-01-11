<?php

namespace CKAN\Manager;

use CKAN\OrganizationList;
use EasyCSV;

require_once dirname(dirname(__DIR__)) . '/inc/common.php';

$start = isset($argv[1]) ? trim($argv[1]) : 0;

/**
 * Create results dir for logs
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_ASSIGN_CLONES';
mkdir($results_dir);

$brothers = [];

function get_dataset_basename($url)
{
    list($basename,) = explode('#', basename(trim($url)));
    return $basename;
}

foreach (glob(CKANMNGR_DATA_DIR . '/brothers*.csv') as $brothers_csv) {
    $csv = new EasyCSV\Reader($brothers_csv, 'r+', false);
    while (true) {
        $row = $csv->getRow();
        if (!$row) {
            break;
        }
        if (1 == sizeof($row)) {
            continue;
        }
        $original = get_dataset_basename(array_shift($row));
        $brothers[$original] = $row;
    }
}

//var_dump($brothers);
//die();

//$CkanManager = new CkanManager(CKAN_API_URL, CKAN_API_KEY);
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);
//$CkanManager = new CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);
$CkanManager = new CkanManager(CKAN_UAT_API_URL, CKAN_UAT_API_KEY);
//$CkanManager = new CkanManager(CKAN_QA_API_URL, CKAN_QA_API_KEY);

/**
 * Sample csv
 * dataset,group,categories
 * https://catalog.data.gov/dataset/food-access-research-atlas,Agriculture,"Natural Resources and Environment"
 * download-crossing-inventory-data-highway-rail-crossing,Agriculture, "Natural Resources and Environment;Plants and Plant Systems Agriculture"
 */

$CkanManager->resultsDir = $results_dir;
foreach (glob(CKANMNGR_DATA_DIR . '/assign*.csv') as $csv_file) {
    $csv_source = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
    echo $CkanManager->color->green($csv_source);

    $basename = str_replace('.csv', '', basename($csv_file));

//    fix wrong END-OF-LINE
    file_put_contents($csv_file, preg_replace('/[\\r\\n]+/', "\n", file_get_contents($csv_file)));

//    file_put_contents($resultsDir . '/' . $basename . '_tags.log', $status, FILE_APPEND | LOCK_EX);

    $csv = new EasyCSV\Reader($csv_file, 'r+', false);
    $output = new EasyCSV\Writer($results_dir.'/'.$basename.'_clones.csv');
    while (true) {
        $row = $csv->getRow();
        if (!$row) {
            break;
        }

//        skip headers
        if (in_array(trim(strtolower($row['0'])), ['link', 'dataset', 'url', 'data.gov url'])) {
            continue;
        }

        if ($start > 0) {
            $start--;
            continue;
        }

//        format group tags
        $categories = isset($row['2'])?trim($row['2']):'';
//        if (isset($row['2']) && $row['2']) {
//            $categories = explode(';', trim($row['2']));
//            $categories = array_map('trim', $categories);
//        }

//        no anchors please
        $dataset = get_dataset_basename($row['0']);

        if (!$dataset) {
            continue;
        }

//        echo "\tOriginal: ".$dataset . PHP_EOL;
//        $CkanManager->assignGroupsAndCategoriesToDatasets(
//            [$dataset],
//            trim($row['1']),
//            $categories,
//            $basename
//        );
        $output->writeRow([$dataset,trim($row['1']),$categories]);
        echo join(' , ',[$dataset,trim($row['1']),$categories]).PHP_EOL;


        if (isset($brothers[$dataset])) {
            foreach ($brothers[$dataset] as $brother) {
                if (!strlen(trim($brother))) {
                    continue;
                }
                $brother = get_dataset_basename($brother);
                if (!$brother) {
                    continue;
                }
                $output->writeRow([$brother,trim($row['1']),$categories]);
                echo join(' , ',[$brother,trim($row['1']),$categories]).PHP_EOL;
//                echo "\tUat (s):" . PHP_EOL;
//                $CkanManager->assignGroupsAndCategoriesToDatasets(
//                    [$brother],
//                    trim($row['1']),
//                    $categories,
//                    $basename
//                );
            }
        }
    }
}

// show running time on finish
timer();
