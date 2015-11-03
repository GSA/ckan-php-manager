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
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd') . '_DOJ_REMOVING_DUPLICATES';

if (!is_dir($results_dir)) {
    mkdir($results_dir);
}

echo 'prod.json' . PHP_EOL;
if (!is_file($results_dir . '/prod.json')) {
    $ProdCkanManager = new CkanManager(CKAN_API_URL);
    $ProdCkanManager->resultsDir = $results_dir;

    $prod = $ProdCkanManager->exportBrief('organization:doj-gov AND dataset_type:dataset');
    file_put_contents($results_dir . '/prod.json', json_encode($prod, JSON_PRETTY_PRINT));
    $prod_csv = new Writer($results_dir . '/prod.csv');
    $headers = array_keys($prod[array_keys($prod)[0]]);
    $prod_csv->writeRow($headers);
    $prod_csv->writeFromArray($prod);
    echo PHP_EOL . 'datasets from prod: ' . sizeof($prod) . PHP_EOL . PHP_EOL;
} else {
    $prod = json_decode(file_get_contents($results_dir . '/prod.json'), true);
    echo PHP_EOL . 'datasets from prod: ' . sizeof($prod) . PHP_EOL . PHP_EOL;
}

if (!is_file($results_dir . '/prod_sorted.json')) {
    $prod_sorted = [];
    foreach ($prod as $dataset_array) {
        $index = Dataset::simplifyTitle($dataset_array['title_simple'].'_'.$dataset_array['identifier']);
        if (!isset($prod_sorted[$index])) {
            $prod_sorted[$index] = [];
        }
        $prod_sorted[$indexx][] = $dataset_array;
    }

    file_put_contents($results_dir . '/prod_sorted.json', json_encode($prod_sorted, JSON_PRETTY_PRINT));
} else {
    $prod_sorted = json_decode(file_get_contents($results_dir . '/prod_sorted.json'), true);
    echo PHP_EOL . 'datasets sorted from prod: ' . sizeof($prod) . PHP_EOL . PHP_EOL;
}

if (!is_file($results_dir . '/delete.csv')) {
    $survivors = [];
    $delete = [];
    $delete_full = [];
    $statistics = [];
    foreach ($prod_sorted as $title_simple => $brothers) {
        if (1 == sizeof($brothers)) {
            array_push($survivors, $brothers[0]);
            continue;
        }
        unset($survivor);
        foreach ($brothers as $dataset) {
            if (!isset($survivor)) {
                $survivor = $dataset;
                continue;
            }
            if ($dataset['topics'] && !$survivor['topics']) {
                $survivor = $dataset;
                continue;
            }
            if ($dataset['topics'] == $survivor['topics']
                && $dataset['categories'] == $survivor['categories']
                && strtotime($dataset['metadata_created']) < strtotime($survivor['metadata_created'])
            ) {
                $survivor = $dataset;
                continue;
            }
        }
        array_push($survivors, $survivor);
        foreach ($brothers as $dataset) {
            $d = $dataset;
            $d['status'] = 'active';
            if ($dataset['name'] !== $survivor['name']) {
                $delete[] = $dataset['name'];
                $delete_full[] = $dataset;
                $d['status'] = 'deleted';
            }
            array_push($statistics, $d);
        }
    }
    $delete_csv = new Writer($results_dir . '/delete.csv');
    $delete_csv->writeRow(['url']);
    $delete_csv->writeFromArray($delete);

    $delete_full_csv = new Writer($results_dir . '/delete_full.csv');
    $headers = array_keys($delete_full[0]);
    $delete_full_csv->writeRow($headers);
    $delete_full_csv->writeFromArray($delete_full);

    $stats_csv = new Writer($results_dir . '/statistics.csv');
    $headers = array_keys($statistics[0]);
    $stats_csv->writeRow($headers);
    $stats_csv->writeFromArray($statistics);

    $survivors_csv = new Writer($results_dir . '/prod_survivors.csv');
    $headers = array_keys($survivors[array_keys($survivors)[0]]);
    $survivors_csv->writeRow($headers);
    $survivors_csv->writeFromArray($survivors);

    $stitle = '';
    foreach ($delete_full as $dataset) {
        if ($dataset['title_simple'] !== $stitle) {
            $stitle = $dataset['title_simple'];
//            echo PHP_EOL;
        }
//        echo printf('%20s %20s',$dataset['title_simple'],$dataset['name']).PHP_EOL;
    }
}

// show running time on finish
timer();
