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
    private $packageSearchPerPage = 100;

    /**
     * @param string $apiUrl
     * @param null $apiKey
     */
    public function __construct($apiUrl, $apiKey = null)
    {
        $this->Ckan = new CkanClient($apiUrl, $apiKey);
    }

    /**
     * Export all packages by organization term
     * @param $terms
     * @param $results_dir
     */
    public function export_packages_by_org_terms($terms, $results_dir)
    {
        $log_output  = ORGANIZATION_TO_EXPORT . PHP_EOL . PHP_EOL;
        foreach ($terms as $term => $agency) {
            echo PHP_EOL . $term . PHP_EOL;
            $page    = 0;
            $count   = 0;
            $results = [];
            while (true) {
                $start      = $page++ * $this->packageSearchPerPage;
                $ckanResult = $this->Ckan->package_search('organization:' . $term, $this->packageSearchPerPage, $start);
                $ckanResult = json_decode($ckanResult, true); //  decode json as array
                $ckanResult = $ckanResult['result'];
                $results    = array_merge($results, $ckanResult['results']);
                $count      = $ckanResult['count'];
                echo "start from $start / " . $count . ' total ' . PHP_EOL;
                if ($ckanResult['count'] - $this->packageSearchPerPage < $start) {
                    break;
                }
            }

            $offset = ($term == PARENT_TERM) ? '' : '  ';
            $log_output .= str_pad($offset . "[$term]", 20) . str_pad($offset . $agency, 50, ' .') . "[$count]" . PHP_EOL;

            $json = (json_encode($results, JSON_PRETTY_PRINT));
            file_put_contents($results_dir . '/' . $term . '.json', $json);
        }
        file_put_contents($results_dir . '/_' . PARENT_TERM . '.log', $log_output);
    }

    /**
     * Ability to Add legacy tag to all dms datasets for an organization and make all those datasets private
     */
    public function tag_legacy_dms($termsArray, $tag_name, $results_dir)
    {
//        get all datasets to update
        $datasets = $this->get_dms_public_datasets($termsArray, $tag_name);

        $count = sizeof($datasets);

        $log_file   = "$count.log";
        $log_output = '';

//        update dataset tags list
        foreach ($datasets as $key => $dataset) {
            $log_output .= $status = "[ $key / $count ] " . $dataset['name'] . PHP_EOL;
            echo $status;
            $dataset['tags'][] = [
                'name' => $tag_name,
            ];

            try {
                $this->Ckan->package_update($dataset);
            } catch (\Exception $ex) {
                die(json_encode($dataset, JSON_PRETTY_PRINT) . PHP_EOL . $ex->getMessage() . PHP_EOL . PHP_EOL);
            }
        }

        file_put_contents($results_dir . '/' . $log_file, $log_output);
    }

    /**
     * Use organization terms array to filter, use null to tag all datasets
     * @param array $terms
     * @param $tag_name
     * @return array
     */
    private function get_dms_public_datasets($terms = null, $tag_name)
    {
        $dms_datasets = [];
        $page         = 0;

        if ($terms) {
            $organizationFilter = array_keys($terms);
            // & = ugly hack to prevent 'Unused local variable' error by PHP IDE, it works perfect without &
            array_walk($organizationFilter, function (&$term) {
                $term = ' organization:"' . $term . '" ';
            });
            $organizationFilter = ' AND (' . join(' OR ', $organizationFilter) . ')';
        } else {
            $organizationFilter = '';
        }

        while (true) {
            $start      = $page++ * $this->packageSearchPerPage;
            $ckanResult = $this->Ckan->package_search('dms' . $organizationFilter, $this->packageSearchPerPage, $start);
            $ckanResult = json_decode($ckanResult, true); //  decode json as array
            $ckanResult = $ckanResult['result'];
            foreach ($ckanResult['results'] as $dataset) {
                if (!isset($dataset['extras']) || !is_array($dataset['extras']) || !sizeof($dataset['extras'])) {
                    continue;
                }
                if (strpos(json_encode($dataset), '"' . $tag_name . '"')) {
                    continue;
                }
                if (strpos(json_encode($dataset['extras']), '"dms"')) {
                    $dms_datasets[] = $dataset;
                }
            }
            $count = $ckanResult['count'];
            echo "start from $start / " . $count . ' total ' . PHP_EOL;
            if ($ckanResult['count'] - $this->packageSearchPerPage < $start) {
                break;
            }
        }

        return $dms_datasets;
    }
}