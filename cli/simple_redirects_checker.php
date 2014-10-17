<?php
/**
 * @author Alex Perfilov
 * @date   5/23/14
 *
 */

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_REDIRECTS_CHECK';
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

$date = new DateTime(null, new DateTimeZone('UTC'));
$curl_ch_headers = [
    'Date: ' . $date->format('D, d M Y H:i:s') . ' GMT', // RFC 1123
    'Accept: application/json',
    'Accept-Charset: utf-8',
    'Accept-Encoding: gzip'
];

foreach (glob(DATA_DIR . '/redirects*.csv') as $csv_file) {
    $status = PHP_EOL . PHP_EOL . basename($csv_file) . PHP_EOL . PHP_EOL;
    echo $status;

    $basename = str_replace('.csv', '', basename($csv_file));

//    fix wrong END-OF-LINE
    file_put_contents($csv_file, preg_replace('/[\\r\\n]+/', "\n", file_get_contents($csv_file)));

    $csv_source = new EasyCSV\Reader($csv_file, 'r+', false);
    $csv_destination = new EasyCSV\Writer($results_dir . '/' . $basename . '_log.csv');

    $csv_destination->writeRow(['from', 'to', 'status']);

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
        if (in_array(trim(strtolower($row[0])), ['from', 'source url'])) {
//            $csv_destination->writeRow($row);
            continue;
        }

        $from = $row[0];
        $to = $row[1];

        $redirect = try_get_redirect($curl_ch, $from);
        if ($to == $redirect) {
            echo "OK" . PHP_EOL;
            $csv_destination->writeRow([$from, $to, 'OK']);
        } else {
            echo "Fail: " . $redirect . PHP_EOL;
            $csv_destination->writeRow([$from, $to, 'FAIL: ' . $redirect]);
        }
        continue;

//        $dataset_api_url = str_replace('/dataset/', '/api/rest/dataset/', $dataset_url);
//
//        $pageFound = try_get_page($curl_ch, $dataset_api_url) ? 'OK' : 404;
//        echo $pageFound . "\t" . $dataset_url . PHP_EOL;
//
//        if ('OK' == $pageFound) {
//            $csv_destination->writeRow([$dataset_url_xyz, $dataset_url, $pageFound, '', '']);
//        } else {
//            $api_rest_xyz = str_replace('/dataset/', '/api/rest/dataset/', $dataset_url_xyz);
//            $apiRestFound = try_get_page($curl_ch, $api_rest_xyz) ? 'OK' : 404;
//            echo $apiRestFound . "\t" . $api_rest_xyz . PHP_EOL;
//            $csv_destination->writeRow([$dataset_url_xyz, $dataset_url, $pageFound, $apiRestFound, $api_rest_xyz]);
//        }

//        $redirect = try_get_redirect($curl_ch, $dataset_url_xyz);
//        if (!$redirect) {
//            echo 'No redirect: ' . $dataset_url_xyz . PHP_EOL;
//            $csv_destination->writeRow([$dataset_url_xyz, $dataset_url, 'no redirect', '']);
//            continue;
//        }
//
//        if (url_compare($redirect, $dataset_url)) {
//            $csv_destination->writeRow([$dataset_url_xyz, $dataset_url, 'correct', '']);
//        } else {
//            echo 'Wrong redirect: ' . $dataset_url_xyz . PHP_EOL;
//            $csv_destination->writeRow([$dataset_url_xyz, $dataset_url, 'wrong redirect', '' . $redirect]);
//            continue;
//        }
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
    $u1 = trim(str_replace(['http:', 'https:'], '', $url1), '/ ');
    $u2 = trim(str_replace(['http:', 'https:'], '', $url2), '/ ');

    return ($u1 === $u2);
}

/**
 * @param $curl_ch
 * @param $url
 *
 * @return bool
 */
function try_get_page($curl_ch, $url)
{
    $try = 5;
    try {
        curl_setopt($curl_ch, CURLOPT_URL, $url);
        $method = 'GET';

        // Set cURL method.
        curl_setopt($curl_ch, CURLOPT_CUSTOMREQUEST, $method);

        // Execute request and get response headers.
        curl_exec($curl_ch);
        $info = curl_getinfo($curl_ch);

        switch ($info['http_code']) {
            case 404:
                return false;
            case 200:
                return true;
            default:
                echo $err = 'Unknown code:' . $info['http_code'] . PHP_EOL;
                throw new Exception($err);
//            die('Unknown code:' . $info['http_code'] . PHP_EOL);
        }
    } catch (Exception $ex) {
        $try--;
        if (!$try) {
            die($ex->getMessage());
        }
    }

    return false;
}

/**
 * @param $curl_ch
 * @param $url
 *
 * @return bool
 */
function try_get_redirect($curl_ch, $url)
{
    curl_setopt($curl_ch, CURLOPT_URL, $url);
    $method = 'GET';

    // Set cURL method.
    curl_setopt($curl_ch, CURLOPT_CUSTOMREQUEST, $method);

    // Execute request and get response headers.
    $response = curl_exec($curl_ch);
    $info = curl_getinfo($curl_ch);
    if (isset($info['redirect_url']) && $info['redirect_url']) {
        return $info['redirect_url'];
    }

    if (stripos($response, 'http-equiv="refresh"')) {
        $pattern = '/content="0;URL=(http[\S\/\-\.]+)"/';
        preg_match($pattern, $response, $matches, PREG_OFFSET_CAPTURE, 3);
        if ($matches && isset($matches[1]) && isset($matches[1][0])) {
            return $matches[1][0];
        }
    }

    return false;
}

// show running time on finish
timer();