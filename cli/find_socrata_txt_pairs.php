<?php
/**
 * @author Alex Perfilov
 * @date   5/23/14
 *
 */

require_once dirname(__DIR__) . '/inc/common.php';

$start = isset($argv[1]) ? trim($argv[1]) : false;
$limit = isset($argv[2]) ? intval($argv[2]) : 1;

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_SOCRATA_PAIRS';
mkdir($results_dir);

/**
 * Adding Legacy dms tag
 * Production
 */
$CkanManager = new \CKAN\Manager\CkanManager(CKAN_API_URL, CKAN_API_KEY);

/**
 * Staging
 */
//$CkanManager = new \CKAN\Manager\CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);

/**
 * Dev
 */
//$CkanManager = new \CKAN\Manager\CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);

/**
 *
 */
define('ERROR_REPORTING', E_ALL & ~E_NOTICE);

//  https://explore.data.gov/api/views/bxfh-jivs.json
/**
 *
 */
define('SOCRATA_URL', 'https://explore.data.gov/api/views/');

if (!is_readable($socrata_file_path = DATA_DIR . '/socrata.txt')) {
    die($socrata_file_path . ' not readable');
}

$socrata_list = file_get_contents($socrata_file_path);
$socrata_list = preg_replace('/[\\r\\n]+/', "\n", $socrata_list);
$socrata_list = explode("\n", $socrata_list);

$CkanManager->get_socrata_pairs($socrata_list, $results_dir);

// show running time on finish
timer();