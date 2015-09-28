<?php

namespace CKAN\Manager;

use CKAN\OrganizationList;
use EasyCSV;

require_once dirname(dirname(__DIR__)) . '/inc/common.php';

$start = isset($argv[1]) ? trim($argv[1]) : 0;

/**
 * Create results dir for logs
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_ASSIGN_GROUPS';
mkdir($results_dir);

$CkanManager = new CkanManager(CKAN_API_URL, CKAN_API_KEY);
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);
//$CkanManager = new CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);
//$CkanManager = new CkanManager(CKAN_UAT_API_URL, CKAN_UAT_API_KEY);
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
        $categories = [];
        if (isset($row['2']) && $row['2']) {
            $categories = explode(';', trim($row['2']));
            $categories = array_map('trim', $categories);

        }

//        no anchors please
        list($dataset,) = explode('#', basename(trim($row['0'])));

        if (!$dataset) {
            continue;
        }

//        double trouble check
        if (strpos($row['0'], '://')) {
            if (!strpos($row['0'], '/dataset/')) {
                if (strpos($row['0'], 'dataset?q=')) {
                    parse_str(parse_url($row['0'], PHP_URL_QUERY), $query_array);
                    if (isset($query_array['q'])) {
                        $query = $query_array['q'];
                        if (isset($query_array['organization'])) {
                            $org = $query_array['organization'];
                            $organizationList = new OrganizationList();
                            $org = $organizationList->getTreeArrayFor($organizationList->getNameFor($org));
                            if (!is_array($org) || !sizeof($org)) {
                                continue;
                            }
                            $org = join(' OR ', array_keys($org));
//                            var_dump($organizationList->getTreeArrayFor($organizationList->getNameFor($org)));
//                            continue;
                            $query = "$query AND organization:($org)";


//                            echo $query.PHP_EOL;
                        }
                        $packages = $CkanManager->tryPackageSearch($query, '', 200);
                        $CkanManager->say(sizeof($packages) . " found searching: $query,API SEARCH");
                        file_put_contents(
                            $results_dir . '/' . $basename . '_tags.log.csv',
                            sizeof($packages) . " found searching: $query,API SEARCH" . PHP_EOL,
                            FILE_APPEND | LOCK_EX
                        );
//                        print $query_array['q'];
                        if (!sizeof($packages)) {
                            continue;
                        }

                        foreach ($packages as $package) {
                            $CkanManager->assignGroupsAndCategoriesToDatasets(
                                [$package['name']],
                                trim($row['1']),
                                $categories,
                                $basename
                            );
                            continue;
                        }
                    }
                    continue;
                }


                continue;
            }
        }

        $CkanManager->assignGroupsAndCategoriesToDatasets(
            [$dataset],
            trim($row['1']),
            $categories,
            $basename
        );
    }
}

// show running time on finish
timer();
