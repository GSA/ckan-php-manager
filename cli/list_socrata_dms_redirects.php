<?php

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_REDIRECTS';
mkdir($results_dir);

if (!is_readable($socrata_file_path = DATA_DIR . '/socrata.txt')) {
    die($socrata_file_path . ' not readable');
}

if (!is_readable($dms_csv_path = DATA_DIR . '/dms.csv')) {
    die($dms_csv_path . ' not readable');
}

$socrata_list = file_get_contents($socrata_file_path);
$socrata_list = preg_replace('/[\\r\\n]+/', "\n", $socrata_list);
$socrata_list = explode("\n", $socrata_list);

$csv       = new EasyCSV\Reader($dms_csv_path, 'r+', false);
$ckan_urls = [];

while (true) {
    $row = $csv->getRow();
    if (!$row) {
        break;
    }
//        skip headers
    if (in_array(trim(strtolower($row['0'])), ['id'])) {
        continue;
    }

    $ckan_urls[trim($row['0'])] = [
        'private_url' => trim($row['3']),
        'public_url'  => trim($row['2']),
    ];
}

$socrata_redirects = [];
$ckan_redirects    = [];
foreach ($socrata_list as $socrata_line) {
    if (!strlen($socrata_line = trim($socrata_line))) {
        continue;
    }
    list($socrata_id, $ckan_id) = explode(': ', $socrata_line);
    $socrata_id = trim($socrata_id);
    if (isset($ckan_urls[$ckan_id = trim($ckan_id)])) {
        $ckan_dataset = $ckan_urls[$ckan_id];

        $rename_ckan = strlen($ckan_dataset['private_url']) && !strpos($ckan_dataset['private_url'], '_legacy');

        $working_ckan_url = $rename_ckan ? $ckan_dataset['private_url'] : $ckan_dataset['public_url'];

        $socrata_redirects[] = $socrata_id . ',' . $working_ckan_url;

        if ($rename_ckan) {
            $ckan_redirects_legacy[] = $ckan_dataset['private_url'] . ',' . $ckan_dataset['private_url'] . '_legacy';
            $ckan_redirects_public[] = $ckan_dataset['public_url'] . ',' . $ckan_dataset['private_url'];

            $ckan_redirects[] = $ckan_dataset['public_url'] . ',' . $ckan_dataset['private_url'];
        }

        $ckan_redirects[] = 'http://catalog.data.gov/dataset/' . $ckan_id . ',' . $working_ckan_url;
    }
}

$ckan_redirects_legacy = join("\n", $ckan_redirects_legacy);
file_put_contents($results_dir . '/rename_private_datasets_legacy.csv', $ckan_redirects_legacy);

$ckan_redirects_public = join("\n", $ckan_redirects_public);
file_put_contents($results_dir . '/rename_public_datasets.csv', $ckan_redirects_public);

$ckan_redirects = join("\n", $ckan_redirects);
file_put_contents($results_dir . '/redirects_ckan.csv', $ckan_redirects);

$socrata_redirects = join("\n", $socrata_redirects);
file_put_contents($results_dir . '/redirects_socrata.csv', $socrata_redirects);

// show running time on finish
timer();