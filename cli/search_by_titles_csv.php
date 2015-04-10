<?php

namespace CKAN\Manager;

use EasyCSV;

/**
 * http://idm.data.gov/fed_agency.json
 */

define('ORGANIZATION_TO_EXPORT', 'Department of Labor');

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs and json results
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_SEARCH_TITLES';
mkdir($results_dir);

/**
 * Production
 */
$CkanManager = new CkanManager(CKAN_API_URL);

/**
 * Staging
 */
//$CkanManager = new CkanManager(CKAN_STAGING_API_URL);

foreach (glob(DATA_DIR . '/find_*.csv') as $csv_file) {
    $status = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
    echo $status;

    $basename = str_replace('.csv', '', basename($csv_file));

    $csv_source = new EasyCSV\Reader($csv_file, 'r+', false);
    $csv_destination = new EasyCSV\Writer($results_dir . '/' . $basename . '_results.csv');

    $csv_destination->writeRow(['url', 'exact match', 'title', 'found by title']);

    $i = 0;
    while (true) {
        if (!($i++ % 10)) {
            echo $i . PHP_EOL;
        }
        $row = $csv_source->getRow();
        if (!$row) {
            break;
        }
//        skip headers
        if (in_array(trim(strtolower($row[0])), ['url', 'from', 'source url'])) {
            continue;
        }

        $title = $row[0];

        /**
         * Search for packages by terms found
         */
        $CkanManager->searchByTitle($title, $csv_destination);
    }

//    fix wrong END-OF-LINE
    file_put_contents($csv_file, preg_replace('/[\\r\\n]+/', "\n", file_get_contents($csv_file)));
}

// show running time on finish
timer();
