<?php

namespace CKAN\Manager;


use CKAN\Exceptions;
use EasyCSV;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_RESTORE_DATASETS';
mkdir($results_dir);

/**
 * Adding Legacy dms tag
 * Production
 */
$ProductionClient = new CkanManager(CKAN_API_URL, CKAN_API_KEY);

/**
 * Staging
 */
$StagingClient = new CkanManager(CKAN_UAT_API_URL);

/**
 * Dev
 */
//$CkanManager = new CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);

/**
 * Sample csv
 * dataset,group,categories
 * https://catalog.data.gov/dataset/food-access-research-atlas,Agriculture,"Natural Resources and Environment"
 * download-crossing-inventory-data-highway-rail-crossing,Agriculture, "Natural Resources and Environment;Plants and Plant Systems Agriculture"
 */

foreach (glob(DATA_DIR . '/*.csv') as $csv_file) {
    $status = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
    echo $status;

//    fix wrong END-OF-LINE
    file_put_contents($csv_file, preg_replace('/[\\r\\n]+/', "\n", file_get_contents($csv_file)));

    file_put_contents($results_dir . '/groups.log', $status, FILE_APPEND | LOCK_EX);

    $csv = new EasyCSV\Reader($csv_file, 'r+', false);
    while (true) {
        $row = $csv->getRow();
        if (!$row) {
            break;
        }
//        skip headers
        if (in_array(trim(strtolower($row['0'])), ['dataset', 'url'])) {
            continue;
        }

        $datasetName = basename($row['0']);

        $StagingClient->say(str_pad($datasetName, 100, ' . '), '');

        try {
            $DatasetArray = $StagingClient->getDataset($datasetName);
//            no exception, cool
            $StagingClient->say(str_pad('Staging OK', 15, ' . '), '');

            $ProductionClient->diffUpdate($datasetName, $DatasetArray);
//            var_dump($DatasetArray);die();
        } catch (Exceptions\NotFoundHttpException $ex) {
            $StagingClient->say(str_pad('Staging 404', 15, ' . '));
        } catch (\Exception $ex) {
            $StagingClient->say(str_pad('Staging Error: ' . $ex->getMessage(), 15, ' . '));
        }

//        debug
//        die();
    }
}

// show running time on finish
timer();
