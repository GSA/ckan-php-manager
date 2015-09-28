<?php

namespace CKAN\Manager;

require_once dirname(dirname(__DIR__)) . '/inc/common.php';

$results_dir = CKANMNGR_RESULTS_DIR . date('/Ymd-His') . '_FIND_MATCHES';
mkdir($results_dir);

$CkanManager = new CkanManager(CKAN_API_URL);
//$CkanManager = new CkanManager(CKAN_QA_API_URL);
$CkanManager->resultsDir = $results_dir;

$CkanManager->findMatchesByAgency('nrc');
