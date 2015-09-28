<?php

namespace CKAN\Manager;

use EasyCSV;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_CHECK_AAPI';
mkdir($results_dir);

/**
 *
 */
define('ERROR_REPORTING', E_ALL & ~E_NOTICE);

// Create cURL object.
$curl_ch = curl_init();
// Follow any Location: headers that the server sends.
curl_setopt($curl_ch, CURLOPT_FOLLOWLOCATION, false);
// However, don't follow more than five Location: headers.
curl_setopt($curl_ch, CURLOPT_MAXREDIRS, 5);
// Automatically set the Referrer: field in requests
// following a Location: redirect.
curl_setopt($curl_ch, CURLOPT_AUTOREFERER, true);
// Return the transfer as a string instead of dumping to screen.
curl_setopt($curl_ch, CURLOPT_RETURNTRANSFER, true);
// If it takes more than 5 minutes => fail
curl_setopt($curl_ch, CURLOPT_TIMEOUT, 60 * 5);
// We don't want the header (use curl_getinfo())
curl_setopt($curl_ch, CURLOPT_HEADER, false);
// Track the handle's request string
curl_setopt($curl_ch, CURLINFO_HEADER_OUT, true);
// Attempt to retrieve the modification date of the remote document.
curl_setopt($curl_ch, CURLOPT_FILETIME, true);
// Initialize cURL headers

foreach (glob(CKANMNGR_DATA_DIR . '/check_*.csv') as $csv_file) {
    $status = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
    echo $status;

    $basename = str_replace('.csv', '', basename($csv_file));

//    fix wrong END-OF-LINE
    file_put_contents($csv_file, preg_replace('/[\\r\\n]+/', "\n", file_get_contents($csv_file)));

    $csv_source = new EasyCSV\Reader($csv_file, 'r+', false);
    $csv_destination = new EasyCSV\Writer($results_dir . '/' . $basename . '_log.csv');

    $csv_destination->writeRow(['dataset', 'status', 'aapi found']);

    $i = 0;
    while (true) {
        if (!($i++ % 100)) {
            echo $i . PHP_EOL;
        }
        $row = $csv_source->getRow();
        if (!$row) {
            break;
        }
//        skip headers
        if (in_array(trim(strtolower($row[0])), ['data.gov url'])) {
            continue;
        }

        $url = strtolower($row[0]);

        if (!strpos($url, '/dataset/')) {
            $csv_destination->writeRow([$url, 'not a dataset', '0']);
            continue;
        }

        $dataset = try_get_dataset($curl_ch, str_replace('/dataset/', '/api/rest/dataset/', $url));

        if (200 !== $dataset['info']['http_code']) {
//            Redirect check
            $dataset2 = try_get_dataset($curl_ch, $url);
            if ((404 == $dataset['info']['http_code']) && (200 == $dataset2['info']['http_code'])) {
                $response = $dataset2['response'];
                if (stripos($response, 'http-equiv="refresh"')) {
                    $pattern = '/content="0;URL=(http[\S\/\-\.]+)"/';
                    preg_match($pattern, $response, $matches, PREG_OFFSET_CAPTURE, 3);
                    if ($matches && isset($matches[1]) && isset($matches[1][0])) {
                        $url2 = $matches[1][0];

                        $dataset3 = try_get_dataset($curl_ch, str_replace('/dataset/', '/api/rest/dataset/', $url2));
                        if (200 == $dataset3['info']['http_code']) {
                            $aapi_found = strpos($dataset3['response'], 'aapi0916');
                            $csv_destination->writeRow([$url, 'ok (redirect)', ($aapi_found ? '1' : '0')]);
                            continue;
                        }
                    }
                }
            }
            $csv_destination->writeRow([$url, $dataset['info']['http_code'], '0']);
            continue;
        } else {
            if (!strpos($dataset['response'], '"type": "dataset",')) {
                $csv_destination->writeRow([$url, 'not a dataset', '0']);
                continue;
            }
            $aapi_found = strpos($dataset['response'], 'aapi0916');
            $csv_destination->writeRow([$url, 'ok', ($aapi_found ? '1' : '0')]);
            continue;
        }
    }
}

/**
 * @param $url1
 * @param $url2
 *
 * @return bool
 */
function url_compare($url1, $url2)
{
    $url1_strip = trim(str_replace(['http:', 'https:'], '', $url1), '/ ');
    $url2_strip = trim(str_replace(['http:', 'https:'], '', $url2), '/ ');

    return ($url1_strip === $url2_strip);
}

/**
 * @param $curl_ch
 * @param $url
 *
 * @return bool
 */
function try_get_dataset($curl_ch, $url)
{
    curl_setopt($curl_ch, CURLOPT_URL, $url);
    $method = 'GET';

    // Set cURL method.
    curl_setopt($curl_ch, CURLOPT_CUSTOMREQUEST, $method);

    // Execute request and get response headers.
    $response = curl_exec($curl_ch);
    $info = curl_getinfo($curl_ch);

    $return = [
        'response' => $response,
        'info' => $info
    ];

    return $return;
}

// show running time on finish
timer();
