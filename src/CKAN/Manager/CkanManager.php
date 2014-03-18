<?php

namespace CKAN\Manager;

use CKAN\Core\CkanClient;
use CKAN\Exceptions\NotFoundHttpException;

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
    private $packageSearchPerPage = 200;

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
        $log_output = ORGANIZATION_TO_EXPORT . PHP_EOL . PHP_EOL;
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
     * Ability to tag datasets by extra field
     * @param string $extra_field
     * @param string $tag_name
     * @param string $results_dir
     */
    public function tag_by_extra_field($extra_field, $tag_name, $results_dir)
    {
        $page         = 0;
        $processed    = 0;
        $log_output   = '';
        $tag_template = [
            'key'   => $tag_name,
            'value' => true,
        ];

        $marked_true  = 0;
        $marked_other = 0;

        while (true) {
            $start      = $page++ * $this->packageSearchPerPage;
            $ckanResult = $this->Ckan->package_search('identifier:*', $this->packageSearchPerPage, $start);
            $ckanResult = json_decode($ckanResult, true); //  decode json as array
            $ckanResult = $ckanResult['result'];

            if (!($count = $ckanResult['count'])) {
                break;
            }

            $datasets = $ckanResult['results'];

            foreach ($datasets as $dataset) {
                $processed++;
                if (!isset($dataset['extras']) || !is_array($dataset['extras']) || !sizeof($dataset['extras'])) {
                    continue;
                }
                $identifier_found = false;
                foreach ($dataset['extras'] as $extra) {
                    if ($tag_template == $extra) {
                        $marked_true++;
//                        exact match key,value
                        continue 2;
                    }
                    if ($tag_name == $extra['key']) {
                        $marked_other++;
//                        only same key
                        continue 2;
                    }
                    if ($extra_field == $extra['key']) {
                        $identifier_found = true;
                    }
                }

                if ($identifier_found) {
                    $dataset['extras'][] = $tag_template;
                }

                $log_output .= $dataset['name'] . PHP_EOL;
//                echo $log_output;

                $this->Ckan->package_update($dataset);
                $marked_true++;
            }

            echo "processed $processed ( $tag_name true = $marked_true, other = $marked_other) / " . $count . ' total ' . PHP_EOL;
            if ($count - $this->packageSearchPerPage < $start) {
                break;
            }
        }
        file_put_contents($results_dir . '/_' . $tag_name . '.log', $log_output);
    }

    /**
     * Ability to Add legacy tag to all dms datasets for an organization and make all those datasets private
     */
    public function tag_legacy_dms($termsArray, $tag_name, $results_dir)
    {
//        get all datasets to update
        $datasets = $this->get_dms_public_datasets($termsArray);

        $count = sizeof($datasets);

        $log_file   = "$count.log";
        $log_output = '';

//        update dataset tags list
        foreach ($datasets as $key => $dataset) {
            $log_output .= $status = "[ $key / $count ] " . $dataset['name'] . PHP_EOL;
            echo $status;
            $dataset['tags'][]  = [
                'name' => $tag_name,
            ];
            $dataset['private'] = true;

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
     * @return array
     */
    private function get_dms_public_datasets($terms = null)
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

    /**
     * @param $datasetNames
     * @param $group
     * @param string $categories
     * @param $results_dir
     * @throws \Exception
     */
    public function assign_groups_and_categories_to_datasets($datasetNames, $group, $categories = null, $results_dir)
    {
        $log_output = '';

        if (!($group = $this->findGroup($group))) {
            throw new \Exception('Group ' . $group . ' not found!' . PHP_EOL);
        }

        foreach ($datasetNames as $datasetName) {
            $log_output .= $status = str_pad($datasetName, 100, ' . ');
            echo $status;

            try {
                $dataset = $this->Ckan->package_show($datasetName);
            } catch (NotFoundHttpException $ex) {
                $log_output .= $status = str_pad('NOT FOUND', 10, ' . ', STR_PAD_LEFT) . PHP_EOL;
                echo $status;
                continue;
            }


            $dataset = json_decode($dataset, true);
            if (!$dataset['success']) {
                $log_output .= $status = str_pad('NOT FOUND', 10, ' . ', STR_PAD_LEFT) . PHP_EOL;
                echo $status;
                continue;
            }

            $dataset             = $dataset['result'];
            $dataset['groups'][] = [
                'name' => $group['name'],
            ];
            if ($categories) {
                $dataset['extras'][] = [
                    'key'   => '__category_tag_' . $group['id'],
                    'value' => $categories,
                ];
            }
            $this->Ckan->package_update($dataset);
            $log_output .= $status = str_pad('SUCCESS', 10, ' . ', STR_PAD_LEFT) . PHP_EOL;
            echo $status;
        }

        file_put_contents($results_dir . '/groups.log', $log_output, FILE_APPEND | LOCK_EX);
    }

    /**
     * Return a list of the names of the site’s groups.
     * @param string $groupName
     * @throws \Exception
     * @return mixed
     */
    private function findGroup($groupName)
    {
        static $group_list;
        if (!$group_list) {
            $list = $this->Ckan->group_list(true);
            $list = json_decode($list, true);
            if (!$list['success']) {
                throw new \Exception('Could not retrieve group list');
            }
            $group_list = $list['result'];
        }

        $group = false;
        foreach ($group_list as $group) {
            if (stristr(json_encode($group), $groupName)) {
                break;
            }
        }

        return $group;
    }

    /**
     * Remove groups & all group tags from dataset
     * @param $datasetNames
     * @param $group_to_remove
     * @param $results_dir
     * @throws \Exception
     */
    public function remove_tags_and_groups_to_datasets($datasetNames, $group_to_remove, $results_dir)
    {
        $log_output = '';

        if (!($group_to_remove = $this->findGroup($group_to_remove))) {
            throw new \Exception('Group ' . $group_to_remove . ' not found!' . PHP_EOL);
        }

        foreach ($datasetNames as $datasetName) {
            $log_output .= $status = str_pad($datasetName, 100, ' . ');
            echo $status;

            try {
                $dataset = $this->Ckan->package_show($datasetName);
            } catch (NotFoundHttpException $ex) {
                $log_output .= $status = str_pad('NOT FOUND', 10, ' . ', STR_PAD_LEFT) . PHP_EOL;
                echo $status;
                continue;
            }

            $dataset = json_decode($dataset, true);
            if (!$dataset['success']) {
                $log_output .= $status = str_pad('NOT FOUND', 10, ' . ', STR_PAD_LEFT) . PHP_EOL;
                echo $status;
                continue;
            }

            $dataset = $dataset['result'];


//            removing group
            $groups = [];
            foreach ($dataset['groups'] as $group) {
                if ($group['name'] !== $group_to_remove['name']) {
                    $groups[] = $group;
                }
            }

            if (sizeof($dataset['groups']) > sizeof($groups)) {
                $log_output .= $status = str_pad('-GROUP', 8, ' . ', STR_PAD_LEFT);
                echo $status;
            }

            $dataset['groups'] = $groups;

//            removing extra tags of group
            $category_tag = '__category_tag_' . $group_to_remove['id'];

            $extras = [];
            foreach ($dataset['extras'] as $extra) {
                if ($extra['key'] !== $category_tag) {
                    $extras[] = $extra;
                } else {
                    $extra['value'] = null;
                    $extras[] = $extra;
                    $log_output .= $status = str_pad('-TAGS', 7, ' . ', STR_PAD_LEFT);
                    echo $status;
                }
            }

            $dataset['extras'] = $extras;

            $this->Ckan->package_update($dataset);
            $log_output .= $status = str_pad('SUCCESS', 10, ' . ', STR_PAD_LEFT) . PHP_EOL;
            echo $status;
        }

        file_put_contents($results_dir . '/groups.log', $log_output, FILE_APPEND | LOCK_EX);
    }
}