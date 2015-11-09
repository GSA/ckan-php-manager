<?php

namespace CKAN\Manager;


use EasyCSV\Reader;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs and json results
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_EXPORT_SHORT';
mkdir($results_dir);

$start = isset($argv[1]) ? trim($argv[1]) : 0;

$CkanManager = new CkanManager(CKAN_API_URL, CKAN_API_KEY);
//$CkanManager = new CkanManager(INVENTORY_CKAN_PROD_API_URL, INVENTORY_CKAN_PROD_API_KEY);
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL);


$CkanManager->resultsDir = $results_dir;
foreach (glob(CKANMNGR_DATA_DIR . '/export_*.csv') as $csv_file) {
    $status = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
    echo $status;

    $basename = str_replace('.csv', '', basename($csv_file));

//    fix wrong END-OF-LINE
    file_put_contents($csv_file, preg_replace('/[\\r\\n]+/', "\n", file_get_contents($csv_file)));


    $csv = new Reader($csv_file, 'r+', false);
    $return = [];
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

//        no anchors please
        list($dataset_name,) = explode('#', basename(trim($row['0'])));

        if (!$dataset_name) {
            continue;
        }

//        double trouble check
        if (strpos($row['0'], '://')) {
            if (!strpos($row['0'], '/dataset/')) {
                file_put_contents(
                    $results_dir . '/' . $basename . '_export.log.csv',
                    $row['0'] . ',WRONG URL' . PHP_EOL,
                    FILE_APPEND | LOCK_EX
                );
                continue;
            }
        }

        printf('%50s',$dataset_name);
        $dataset = $CkanManager->tryPackageShow($dataset_name);
        if ($dataset) {
            printf('%10s','OK');
            $return[] = $dataset;
        } else {
            printf('%10s','FAIL');
        }
        echo PHP_EOL;
    }

    file_put_contents(
        $results_dir . '/' . $basename . '.json',
        json_encode($return, JSON_PRETTY_PRINT),
        FILE_APPEND | LOCK_EX
    );
}


//$brief = $CkanManager->exportShort('extras_harvest_source_title:Test ISO WAF AND (dataset_type:dataset)');
//$csv->writeFromArray($brief);

// show running time on finish
timer();
