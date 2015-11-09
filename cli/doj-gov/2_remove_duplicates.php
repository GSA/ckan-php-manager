<?php
/**
 * @author Alex Perfilov
 * @date   3/30/14
 *
 */

namespace CKAN\Manager;


use EasyCSV\Writer;

require_once dirname(dirname(__DIR__)) . '/inc/common.php';

$organization = 'doj-gov';


/**
 * Create results dir for logs
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd_') . strtoupper($organization) . '_REMOVING_DUPLICATES';

if (!is_dir($results_dir)) {
    mkdir($results_dir);
}

echo $organization . '_export.json' . PHP_EOL;
if (!is_file($results_dir . '/' . $organization . '_export.json')) {
    $ProdCkanManager = new CkanManager(CKAN_API_URL);
    $ProdCkanManager->resultsDir = $results_dir;

    $prod = $ProdCkanManager->exportFiltered('organization:' . $organization . ' AND dataset_type:dataset','',
        [
            'title',
            'title_simple',
            'name',
            'url',
            'identifier',
            'guid',
            'metadata_created',
            'metadata_modified',
            'extras_modified',
            'topics',
            'categories',
            'tagging'
        ]);
    file_put_contents($results_dir . '/' . $organization . '_export.json', json_encode($prod, JSON_PRETTY_PRINT));
    $prod_csv = new Writer($results_dir . '/' . $organization . '_export.csv');
    $headers = array_keys($prod[array_keys($prod)[0]]);
    $prod_csv->writeRow($headers);
    $prod_csv->writeFromArray($prod);
    echo PHP_EOL . 'datasets from prod: ' . sizeof($prod) . PHP_EOL . PHP_EOL;
} else {
    $prod = json_decode(file_get_contents($results_dir . '/' . $organization . '_export.json'), true);
    echo PHP_EOL . 'datasets from prod: ' . sizeof($prod) . PHP_EOL . PHP_EOL;
}

if (!is_file($results_dir . '/' . $organization . '_export_sorted.json')) {
    $prod_sorted = [];
    foreach ($prod as $dataset_array) {
        $index = Dataset::simplifyTitle($dataset_array['title_simple'] . '_' . $dataset_array['identifier']);
        if (!isset($prod_sorted[$index])) {
            $prod_sorted[$index] = [];
        }
        $prod_sorted[$index][] = $dataset_array;
    }

    file_put_contents($results_dir . '/' . $organization . '_export_sorted.json',
        json_encode($prod_sorted, JSON_PRETTY_PRINT));
} else {
    $prod_sorted = json_decode(file_get_contents($results_dir . '/' . $organization . '_export_sorted.json'), true);
    echo PHP_EOL . 'datasets sorted from prod: ' . sizeof($prod) . PHP_EOL . PHP_EOL;
}

if (!is_file($results_dir.'/'.$organization.'_tagging.csv')) {
    $empty_tags = serialize([]);
    $tagging_csv = new Writer($results_dir.'/'.$organization.'_tagging.csv');
    $tagging_csv->writeRow(['url','topic','tags']);
    foreach($prod_sorted as $title_simple=>$brothers) {
        $tags = $empty_tags;
        foreach($brothers as $dataset) {
            if ($dataset['tagging'] > $tags) {
                $tags = $dataset['tagging'];
            }
        }
        if ($tags !== $empty_tags) {
            $tags = unserialize($tags);
            foreach($brothers as $dataset) {
                foreach($tags as $topic => $topic_tags) {
                    $tagging_csv->writeRow([$dataset['name'],$topic,$topic_tags]);
                }
            }
        }


    }
}

if (!is_file($results_dir . '/delete_'.$organization.'.csv')) {
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
            if(strtotime($dataset['metadata_created']) > strtotime($survivor['metadata_created'])) {
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
    $delete_csv = new Writer($results_dir . '/delete_'.$organization.'.csv');
    $delete_csv->writeRow(['url']);
    $delete_csv->writeFromArray($delete);

    $delete_full_csv = new Writer($results_dir . '/'.$organization.'_delete_full.csv');
    $headers = array_keys($delete_full[0]);
    $delete_full_csv->writeRow($headers);
    $delete_full_csv->writeFromArray($delete_full);

    $stats_csv = new Writer($results_dir . '/'.$organization.'_statistics.csv');
    $headers = array_keys($statistics[0]);
    $stats_csv->writeRow($headers);
    $stats_csv->writeFromArray($statistics);

    $survivors_csv = new Writer($results_dir . '/'.$organization.'_survivors.csv');
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
