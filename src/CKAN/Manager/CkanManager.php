<?php

namespace CKAN\Manager;

use CKAN\Core\CkanClient;

/**
 * @author Alex Perfilov
 * @date   2/24/14
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
     * Export all packages by organization term
     * @param $terms
     * @param $results_dir
     */
    public function export_packages_by_org_terms($terms, $results_dir)
    {
        $log           = ORGANIZATION_TO_EXPORT . PHP_EOL . PHP_EOL;
        foreach ($terms as $term => $agency) {
            echo PHP_EOL . $term . PHP_EOL;
            $page    = 0;
            $count     = 0;
            $results   = [];
            while (true) {
                $start      = $page++ * $this->perPage;
                $ckanResult = $this->Ckan->package_search('organization:' . $term, $this->perPage, $start);
                $ckanResult = json_decode($ckanResult, true); //  decode json as array
                $ckanResult = $ckanResult['result'];
                $results    = array_merge($results, $ckanResult['results']);
                $count = $ckanResult['count'];
                echo "start from $start / " . $count . ' total ' . PHP_EOL;
                if ($ckanResult['count'] - $this->perPage < $start) {
                    break;
                }
            }

            $log .= str_pad("[$term]", 20) . str_pad($agency, 50, ' .') . "[$count]" . PHP_EOL;

            $json = (json_encode($results, JSON_PRETTY_PRINT));
            file_put_contents($results_dir . '/' . $term . '.json', $json);
        }
        file_put_contents($results_dir . '/_' . PARENT_TERM . '.log', $log);
    }
}