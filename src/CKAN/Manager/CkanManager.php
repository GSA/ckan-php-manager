<?php

namespace CKAN\Manager;

use CKAN\Core\CkanClient;

/**
 * Created by PhpStorm.
 * User: Alex Perfilov
 * Date: 2/24/14
 * Time: 2:18 PM
 */
class CkanManager
{
    /**
     * @var \CKAN\Core\CkanClient
     */
    private $Ckan;

    /**
     * Ckan results per page
     * @var int
     */
    private $perPage = 100;

    /**
     * @param string $apiUrl
     */
    public function __construct($apiUrl)
    {
        $this->Ckan = new CkanClient($apiUrl, null);
    }

    /**
     * Import all packages by organization term
     * @param $terms
     */
    public function import_packages_by_org_terms($terms)
    {
        foreach ($terms as $term) {
            echo PHP_EOL . $term . PHP_EOL;
            $page    = 0;
            $results = array();
            while (true) {
                $start      = $page++ * $this->perPage;
                $ckanResult = $this->Ckan->package_search('organization:' . $term, $this->perPage, $start);
//        decode as array
                $ckanResult = json_decode($ckanResult, true);
                $ckanResult = $ckanResult['result'];
                $results    = array_merge($results, $ckanResult['results']);
                echo "start from $start / " . $ckanResult['count'] . ' total ' . PHP_EOL;
                if ($ckanResult['count'] - $this->perPage < $start) {
                    break;
                }
            }
            $json = (json_encode($results, JSON_PRETTY_PRINT));
            file_put_contents(ROOT_DIR . '/results/' . $term . '.json', $json);
        }
    }
}