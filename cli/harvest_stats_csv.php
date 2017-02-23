<?php

namespace CKAN\Manager;

require_once dirname(__DIR__) . '/inc/common.php';

/**
 * Create results dir for logs and json results
 */
$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_HARVEST_STATS';
mkdir($results_dir);

/**
 * Production
 */
$CkanManager = new CkanManager(CKAN_API_URL);

$CkanManager->resultsDir = $results_dir;
$CkanManager->harvestStats();

// show running time on finish
timer();
