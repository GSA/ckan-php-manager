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
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_CHECK_AAPI';
mkdir($results_dir);

/**
 * Adding Legacy dms tag
 * Production
 */
$Importer = new \CKAN\Manager\CkanManager(CKAN_API_URL, CKAN_API_KEY);

/**
 * Staging
 */
//$Importer = new \CKAN\Manager\CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);

/**
 * Dev
 */
//$Importer = new \CKAN\Manager\CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);

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

foreach (glob(DATA_DIR . '/check_*.csv') as $csv_file) {
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
        if (in_array(trim(strtolower($row[0])), ['socrata code', 'from', 'source url'])) {
//            $csv_destination->writeRow($row);
            continue;
        }

        $url = strtolower($row[0]);

        $dataset = try_get_dataset($curl_ch, str_replace('/dataset/', '/api/rest/dataset/', $url));

        if (200 !== $dataset['info']['http_code']) {
            $csv_destination->writeRow([$url, $dataset['info']['http_code'], '0']);
        } else {
            $aapi_found = strpos($dataset['response'], 'aapi0916');
            $csv_destination->writeRow([$url, 'ok', ($aapi_found ? '1' : '0')]);
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