<?php

/**
 * http://idm.data.gov/fed_agency.json
 */

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs
 */
$results_dir = RESULTS_DIR . date('/Ymd-His') . '_TAG_BY_identifier';
mkdir($results_dir);

/**
 * Adding Legacy dms tag
 * Production
 */
//$Importer = new \CKAN\Manager\CkanManager(CKAN_API_URL, CKAN_API_KEY);

/**
 * Staging
 */
//$Importer = new \CKAN\Manager\CkanManager(CKAN_STAGING_API_URL, CKAN_STAGING_API_KEY);

/**
 * DEV
 */
$Importer = new \CKAN\Manager\CkanManager(CKAN_DEV_API_URL, CKAN_DEV_API_KEY);

$Importer->tag_by_extra_field('identifier', 'source_datajson_identifier', $results_dir);

timer();