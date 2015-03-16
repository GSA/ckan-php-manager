<?php

namespace CKAN\Manager;

use CKAN\Core\CkanClient;
use CKAN\Core\OrganizationList;
use CKAN\Exceptions\NotFoundHttpException;
use EasyCSV\Writer;

/**
 * @author Alex Perfilov
 * @date   2/24/14
 */
class CkanManager
{
    /**
     *
     */
    const EXPORT_DMS_ONLY = 0b00000001;
    /**
     *
     */
    const EXPORT_PUBLIC_ONLY = 0b00000010;
    /**
     *
     */
    const EXPORT_PRIVATE_ONLY = 0b00000100;
    /**
     *
     */
    const IDS_ONLY = 0b00001000;
    /**
     * @var string
     */
    public $logOutput = '';
    /**
     * @var string
     */
    public $resultsDir = RESULTS_DIR;
    /**
     * @var \CKAN\Core\CkanClient
     */
    private $Ckan;
    /**
     * @var bool
     */
    private $return = false;
    /**
     * Ckan results per page
     * @var int
     */
    private $packageSearchPerPage = 200;
    /**
     * @var array
     */
    private $expectedFieldsDiff = [];
    /**
     * @var string
     */
    private $apiUrl = '';

    /**
     * @param string $apiUrl
     * @param null $apiKey
     */
    public function __construct($apiUrl, $apiKey = null)
    {
        $this->apiUrl = $apiUrl;
        $this->Ckan   = new CkanClient($apiUrl, $apiKey);
    }

    /**
     *
     */
    public
    function test_dev()
    {
        $datasets = $this->try_package_search('u');

        $i = 0;
        foreach ($datasets as $dataset) {
            $dataset = $this->try_package_show($dataset['name']);

            echo ++$i . ' ' . $dataset['name'] . PHP_EOL;

            $this->package_update($dataset);

            if (defined('QUIT')) {
                die();
            }
        }
    }

    /**
     * @param        $search
     * @param int $rows
     * @param int $start
     * @param string $q
     * @param int $try
     *
     * @return bool|mixed
     */
    private
    function try_package_search(
        $search,
        $rows = 100,
        $start = 0,
        $q = 'q',
        $try = 3
    ) {
        $datasets = false;
        while ($try) {
            try {
                $datasets = $this->Ckan->package_search($search, $rows, $start, $q);
                $datasets = json_decode($datasets, true); // as array

                if (!$datasets['success'] || !isset($datasets['result'])) {
                    throw new \Exception('Could not search datasets');
                }

                $datasets = $datasets['result'];

                if (!$datasets['count']) {
                    echo 'Nothing found ' . PHP_EOL;

                    return false;
                }

                if (!isset($datasets['results']) || !sizeof($datasets['results'])) {
                    echo 'No results ' . PHP_EOL;

                    return false;
                }

                $datasets = $datasets['results'];

                $try = 0;
            } catch (NotFoundHttpException $ex) {
                echo "Datasets not found " . PHP_EOL;

                return false;
            } catch (\Exception $ex) {
                $try--;
                sleep(3);
                echo '      zzz   ' . PHP_EOL;
                if (!$try) {
                    echo 'Too many attempts ' . PHP_EOL;

                    return false;
                }
            }
        }

        return $datasets;
    }

    /**
     * @param string $id
     * @param int $try
     *
     * @return bool|mixed
     */
    public
    function try_package_show(
        $id,
        $try = 3
    ) {
        $dataset = false;
        while ($try) {
            try {
                $dataset = $this->Ckan->package_show($id);
                $dataset = json_decode($dataset, true); // as array

                if (!$dataset['success']) {
                    echo 'No success: ' . $id . PHP_EOL;
                    echo ' :( ';
                    return false;
                }

                if (!isset($dataset['result']) || !sizeof($dataset['result'])) {
                    echo 'No result: ' . $id . PHP_EOL;
                    echo ' :( ';
                    return false;
                }

                $dataset = $dataset['result'];

                $try = 0;
            } catch (NotFoundHttpException $ex) {
                echo "Dataset not found: " . $id . PHP_EOL;

                return false;
            } catch (\Exception $ex) {
                $try--;
                sleep(3);
                echo '      zzz   ' . $id . PHP_EOL;
                if (!$try) {
                    echo 'Too many attempts: ' . $id . PHP_EOL;

                    return false;
                }
            }
        }

        return $dataset;
    }

    /**
     * @param $dataset
     *
     * @return bool
     * @throws \Exception
     */
    public function package_update($dataset)
    {
        if ('dataset' !== $dataset['type']) {
//            throw new \Exception('Not a dataset');
            return false;
        }

        $this->Ckan->package_update($dataset);
        if (!$this->check_dataset_consistency($dataset)) {
            throw new \Exception('dataset consistency check failed');
        }

        return true;
    }

    /**
     * @param $dataset
     *
     * @return bool
     * @throws \Exception
     */
    public function check_dataset_consistency($dataset)
    {
        $id              = $dataset['name'];
        $updated_dataset = $this->try_package_show($id);

        try {
            if (sizeof($dataset['resources']) != sizeof($updated_dataset['resources'])) {
                throw new \Exception('Number of resources does not match after update (check dumps): ' . $id);
            }


            if (isset($dataset['extras'])) {
                $this->pre_diff_sort($dataset);
                $this->pre_diff_sort($updated_dataset);
            }

            $diff = $this->array_diff_assoc_recursive($dataset, $updated_dataset);

            if (sizeof($diff)) {
                file_put_contents(
                    $this->resultsDir . '/dump-diff__' . $id . '.json',
                    json_encode($diff, JSON_PRETTY_PRINT)
                );
                throw new \Exception('Consistency check failed (check diff): ' . $id);
            }

        } catch (\Exception $ex) {
            file_put_contents(
                $this->resultsDir . '/dump-before__' . $id . '.json',
                json_encode($dataset, JSON_PRETTY_PRINT)
            );
            file_put_contents(
                $this->resultsDir . '/dump-after__' . $id . '.json',
                json_encode($updated_dataset, JSON_PRETTY_PRINT)
            );
            throw $ex;
        }

        return true;
    }

    /**
     * @param array $dataset
     */
    private function pre_diff_sort(array &$dataset)
    {
        if (isset($dataset['extras']) && sizeof($dataset['extras'])) {
            $extras = [];
            foreach ($dataset['extras'] as $extra) {
                $extras[$extra['key']] = $extra;
            }
            ksort($extras);
            $dataset['extras'] = $extras;
        }
    }

    /**
     * @param $array1
     * @param $array2
     *
     * @return array
     */
    private function  array_diff_assoc_recursive($array1, $array2)
    {
        $blacklist = [
            'revision_timestamp',
            'metadata_modified',
            'revision_id',
            'no_real_name',
            'tags',
        ];
        $blacklist = array_merge($blacklist, $this->expectedFieldsDiff);

        $difference = [];
        foreach ($array1 as $key => $value) {
            if (is_array($value)) {
                if (!isset($array2[$key]) || !is_array($array2[$key])) {
                    if (!in_array($key, $blacklist)) {
                        $difference[$key] = $value;
                    }
                } else {
                    $new_diff = $this->array_diff_assoc_recursive($value, $array2[$key]);
                    if (!empty($new_diff)) {
                        if (!in_array($key, $blacklist)) {
                            $difference[$key] = $new_diff;
                        }
                    }
                }
            } else {
                if (!array_key_exists($key, $array2) || $array2[$key] !== $value) {
                    if (!in_array($key, $blacklist)) {
                        $difference[$key] = $value;
                    }
                }
            }
        }

        return $difference;
    }

    /**
     * @param $results_dir
     */
    public
    function get_interactive_resources(
        $results_dir
    ) {
        $log_file = $results_dir . '/resources.csv';
        $fp       = fopen($log_file, 'w');

//        Title of Dataset in Socrata | dataset URL in Socrata | dataset URL in Catalog
        $csv_header = [
            'Title of Dataset in Socrata',
            'Dataset URL in Socrata',
            'Dataset URL in Catalog',
        ];

        fputcsv($fp, $csv_header);

//        http://catalog.data.gov/api/search/resource?url=explore.data.gov&all_fields=1&limit=100
        $resources = $this->try_api_package_search(['url' => 'explore.data.gov']);
        if (!$resources) {
            die('error' . PHP_EOL);
        }

        foreach ($resources as $resource) {
            if (!isset($resource['package_id']) || !$resource['package_id']) {
                echo "error: no package_id: " . $resource['id'] . PHP_EOL;
                continue;
            }
            $dataset = $this->try_package_show($resource['package_id']);
            if (!$dataset) {
                echo "error: no dataset: " . $resource['package_id'] . PHP_EOL;
                continue;
            }

            echo "http://catalog.data.gov/dataset/" . $dataset['name'] . PHP_EOL . $dataset['title'] . PHP_EOL . PHP_EOL;
        }

    }

    /**
     * @param     $search
     * @param int $try
     *
     * @return bool|mixed
     */
    private
    function try_api_package_search(
        $search,
        $try = 3
    ) {
        $resources = false;
        while ($try) {
            try {
                $resources = $this->Ckan->api_resource_search($search);
                $resources = json_decode($resources, true); // as array

                if (!$resources['count']) {
                    echo 'No count ' . PHP_EOL;

                    return false;
                }

                if (!isset($resources['results']) || !sizeof($resources['results'])) {
                    echo 'No results ' . PHP_EOL;

                    return false;
                }

                $resources = $resources['results'];

                $try = 0;
            } catch (NotFoundHttpException $ex) {
                echo "Resources not found " . PHP_EOL;

                return false;
            } catch (\Exception $ex) {
                $try--;
                sleep(3);
                echo '      zzz   ' . PHP_EOL;
                if (!$try) {
                    echo 'Too many attempts ' . PHP_EOL;

                    return false;
                }
            }
        }

        return $resources;
    }

    /**
     * @param $title
     * @param $csv_writer
     *
     * @return bool
     */
    public
    function search_by_title(
        $title,
        Writer $csv_writer
    ) {
        $solr_title = $this->escapeSolrValue($title);
        $datasets   = $this->try_package_search("($solr_title)", 3);
        if ($datasets && isset($datasets[0]) && isset($datasets[0]['name'])) {
            $found_title = $this->escapeSolrValue($datasets[0]['title']);
            $exact       = ($solr_title == $found_title);
            echo PHP_EOL . $title . PHP_EOL . $solr_title . PHP_EOL . ($exact ? 'EXACT MATCH' : 'NOT EXACT MATCH') . PHP_EOL . $datasets[0]['title'] . PHP_EOL . $datasets[0]['name'] . PHP_EOL;
            $csv_writer->writeRow(
                [
                    'https://catalog.data.gov/dataset/' . $datasets[0]['name'],
                    ($exact ? 'true' : 'false'),
                    $datasets[0]['title'],
                    $title
                ]
            );
        } else {
            $csv_writer->writeRow(['not found', '', 'not found', $title]);
        }

        return true;
    }

    /**
     * @param $string
     *
     * @return mixed
     */
    private
    function escapeSolrValue(
        $string
    ) {
        $string = preg_replace("/'/u", '', $string);
        $string = preg_replace('/[\W]+/u', ' ', $string);

        return $string;
    }

    /**
     * @param $search_list
     * @param $results_dir
     */
    public
    function search_by_terms(
        $search_list,
        $results_dir
    ) {
        $log_file_popularity = $results_dir . '/search_' . sizeof($search_list) . '_terms_by_popularity.csv';
        $log_file_relevance  = $results_dir . '/search_' . sizeof($search_list) . '_terms_by_relevance.csv';
        $fp_popularity       = fopen($log_file_popularity, 'w');
        $fp_relevance        = fopen($log_file_relevance, 'w');

        $csv_header = [
            'Name of Dataset',
            'Agency',
            'Data.gov URL',
            'Keyword',
        ];

        fputcsv($fp_popularity, $csv_header);
        fputcsv($fp_relevance, $csv_header);

        $ckan_url = 'https://catalog.data.gov/dataset/';
        $i        = 1;

//        most relevant:
//        http://catalog.data.gov/api/3/action/package_search?q=Asian+AND+dataset_type:dataset&sort=score+desc
//        most popular:
//        http://catalog.data.gov/api/3/action/package_search?q=Asian+AND+dataset_type:dataset&sort=views_recent+desc
        foreach ($search_list as $term) {
            echo $i++ . '/' . sizeof($search_list) . ' : ' . $term . PHP_EOL;
            if (!sizeof($term = trim($term))) {
                continue;
            }
            $ckan_query = $this->escapeSolrValue($term) . ' AND dataset_type:dataset';

            $only_first_page = true;
            if ('Demographics' == $term) {
                $only_first_page = false;
            }

            $done     = false;
            $start    = 0;
            $per_page = 20;
            while (!$done) {
                // relevance
                $ckanResultRelevance = $this->Ckan->package_search($ckan_query, $per_page, $start);
                $ckanResultRelevance = json_decode($ckanResultRelevance, true); //  decode json as array
                $ckanResultRelevance = $ckanResultRelevance['result'];

                $count = $ckanResultRelevance['count'];
                echo $start . '/' . $count . ' by relevance' . PHP_EOL;
                if (!$count) {
                    $done = true;
                    continue;
                }

                if (sizeof($ckanResultRelevance['results'])) {
                    foreach ($ckanResultRelevance['results'] as $dataset) {
                        fputcsv(
                            $fp_relevance,
                            [
                                isset($dataset['title']) ? $dataset['title'] : '---',
                                isset($dataset['organization']) && isset($dataset['organization']['title']) ?
                                    $dataset['organization']['title'] : '---',
                                isset($dataset['name']) ?
                                    $ckan_url . $dataset['name'] : '---',
                                $term
                            ]
                        );
                    }
                } else {
                    echo 'no results: ' . $term . PHP_EOL;
                    continue;
                }

                if ($only_first_page) {
                    $done = true;
                    continue;
                }

                $start += $per_page;
                if ($start > $count) {
                    $done = true;
                }
            }

            $done     = false;
            $start    = 0;
            $per_page = 20;
            while (!$done) {
                // popularity
                $ckanResultPopularity = $this->Ckan->package_search(
                    $ckan_query,
                    $per_page,
                    $start,
                    'q',
                    'views_recent desc,name asc'
                );
                $ckanResultPopularity = json_decode($ckanResultPopularity, true); //  decode json as array
                $ckanResultPopularity = $ckanResultPopularity['result'];

                $count = $ckanResultPopularity['count'];
                echo $start . '/' . $count . ' by popularity' . PHP_EOL;
                if (!$count) {
                    $done = true;
                    continue;
                }

                if (sizeof($ckanResultPopularity['results'])) {
                    foreach ($ckanResultPopularity['results'] as $dataset) {
                        fputcsv(
                            $fp_popularity,
                            [
                                isset($dataset['title']) ? $dataset['title'] : '---',
                                isset($dataset['organization']) && isset($dataset['organization']['title']) ?
                                    $dataset['organization']['title'] : '---',
                                isset($dataset['name']) ?
                                    $ckan_url . $dataset['name'] : '---',
                                $term
                            ]
                        );
                    }
                } else {
                    echo 'no results: ' . $term . PHP_EOL;
                    continue;
                }

                if ($only_first_page) {
                    $done = true;
                    continue;
                }

                $start += $per_page;
                if ($start > $count) {
                    $done = true;
                }
            }
        }

        fclose($fp_relevance);
        fclose($fp_popularity);
    }

    /**
     * @param $groups_list
     * @param $results_dir
     */
    public
    function search_by_topics(
        $groups_list,
        $results_dir
    ) {
        $this->logOutput     = '';
        $log_file_popularity = $results_dir . '/search_' . sizeof($groups_list) . '_topics_by_popularity.csv';
        $log_file_relevance  = $results_dir . '/search_' . sizeof($groups_list) . '_topics_by_relevance.csv';
        $error_log           = $results_dir . '/search_' . sizeof($groups_list) . '_topics.log';
        $fp_popularity       = fopen($log_file_popularity, 'w');
        $fp_relevance        = fopen($log_file_relevance, 'w');

        $csv_header = [
            'Name of Dataset',
            'Agency',
            'Data.gov URL',
            'Topic',
        ];

        fputcsv($fp_popularity, $csv_header);
        fputcsv($fp_relevance, $csv_header);

        $ckan_url = 'https://catalog.data.gov/dataset/';
        $i        = 1;

//        most relevant:
//        http://catalog.data.gov/api/3/action/package_search?q=Asian+AND+dataset_type:dataset&sort=score+desc
//        most popular:
//        http://catalog.data.gov/api/3/action/package_search?q=Asian+AND+dataset_type:dataset&sort=views_recent+desc
        foreach ($groups_list as $topic) {
            $this->say(PHP_EOL . $i++ . '/' . sizeof($groups_list) . ' : ' . $topic);
            if (!sizeof($topic = trim($topic))) {
                continue;
            }

            switch ($topic) {
                case 'Cities':
//                        http://catalog.data.gov/api/3/action/package_search?q=organization_type:%22City+Government%22
                    $ckan_query = 'organization_type:"City Government" AND dataset_type:dataset';
                    break;
                case 'Counties':
//                        http://catalog.data.gov/api/3/action/package_search?q=organization_type:%22County+Government%22
                    $ckan_query = 'organization_type:"County Government" AND dataset_type:dataset';
                    break;
                case 'States':
//                        http://catalog.data.gov/api/3/action/package_search?q=organization_type:%22State+Government%22
                    $ckan_query = 'organization_type:"State Government" AND dataset_type:dataset';
                    break;
                case 'Health':
//                        http://catalog.data.gov/api/3/action/package_search?q=organization:hhs-gov
                    $ckan_query = 'organization:"hhs-gov" AND dataset_type:dataset';
                    break;
                case 'Science & Research':
//                        http://catalog.data.gov/api/3/action/package_search?q=groups:research9385
                    $ckan_query = 'groups:(research9385) AND dataset_type:dataset';
                    break;
                case 'Public Safety':
//                        http://catalog.data.gov/api/3/action/package_search?q=groups:safety3175
                    $ckan_query = 'groups:(safety3175) AND dataset_type:dataset';
                    break;
                default:
                    $group = $this->findGroup($topic);
                    if (!$group) {
                        $this->say('Could not find topic: ' . $topic);
//                        file_put_contents($error_log, 'Could not find topic: ' . $topic . PHP_EOL, FILE_APPEND);
                        continue 2;
                    } else {
                        $ckan_query = 'groups:(' . $this->escapeSolrValue(
                                $group['name']
                            ) . ') AND dataset_type:dataset';
                    }
                    break;
            }

            $this->say('API{' . $ckan_query . '}');
//            file_put_contents($error_log, PHP_EOL.$topic.PHP_EOL.$ckan_query.PHP_EOL, FILE_APPEND);
//            echo PHP_EOL.$topic.PHP_EOL.$ckan_query.PHP_EOL;

            $only_first_page = true;
//            if ('Demographics' == $term) {
//                $only_first_page = false;
//            }

            $done     = false;
            $start    = 0;
            $per_page = 20;
            while (!$done) {
                // relevance
                $ckanResultRelevance = $this->Ckan->package_search($ckan_query, $per_page, $start);
                $ckanResultRelevance = json_decode($ckanResultRelevance, true); //  decode json as array
                $ckanResultRelevance = $ckanResultRelevance['result'];

                $count = $ckanResultRelevance['count'];
                $this->say($start . '/' . $count . ' by relevance');
                if (!$count) {
                    $done = true;
                    continue;
                }

                if (sizeof($ckanResultRelevance['results'])) {
                    foreach ($ckanResultRelevance['results'] as $dataset) {
                        fputcsv(
                            $fp_relevance,
                            [
                                isset($dataset['title']) ? $dataset['title'] : '---',
                                isset($dataset['organization']) && isset($dataset['organization']['title']) ?
                                    $dataset['organization']['title'] : '---',
                                isset($dataset['name']) ?
                                    $ckan_url . $dataset['name'] : '---',
                                $topic
                            ]
                        );
                    }
                } else {
                    $this->say('no results: ' . $topic);
                    continue;
                }

                if ($only_first_page) {
                    $done = true;
                    continue;
                }

                $start += $per_page;
                if ($start > $count) {
                    $done = true;
                }
            }

            $done     = false;
            $start    = 0;
            $per_page = 20;
            while (!$done) {
                // popularity
                $ckanResultPopularity = $this->Ckan->package_search(
                    $ckan_query,
                    $per_page,
                    $start,
                    'q',
                    'views_recent desc,name asc'
                );
                $ckanResultPopularity = json_decode($ckanResultPopularity, true); //  decode json as array
                $ckanResultPopularity = $ckanResultPopularity['result'];

                $count = $ckanResultPopularity['count'];
                $this->say($start . '/' . $count . ' by popularity');
                if (!$count) {
                    $done = true;
                    continue;
                }

                if (sizeof($ckanResultPopularity['results'])) {
                    foreach ($ckanResultPopularity['results'] as $dataset) {
                        fputcsv(
                            $fp_popularity,
                            [
                                isset($dataset['title']) ? $dataset['title'] : '---',
                                isset($dataset['organization']) && isset($dataset['organization']['title']) ?
                                    $dataset['organization']['title'] : '---',
                                isset($dataset['name']) ?
                                    $ckan_url . $dataset['name'] : '---',
                                $topic
                            ]
                        );
                    }
                } else {
                    $this->say('no results: ' . $topic);
                    continue;
                }

                if ($only_first_page) {
                    $done = true;
                    continue;
                }

                $start += $per_page;
                if ($start > $count) {
                    $done = true;
                }
            }
        }

        fclose($fp_relevance);
        fclose($fp_popularity);

        file_put_contents($error_log, $this->logOutput, FILE_APPEND);
        $this->logOutput = '';
    }

    /**
     * Shorthand for sending output to stdout and appending to log buffer at the same time.
     *
     * @param string $output
     * @param string $eol
     */
    public
    function say(
        $output = '',
        $eol = PHP_EOL
    ) {
        echo $output . $eol;
        $this->logOutput .= $output . $eol;
    }

    /**
     * Return a list of the names of the site’s groups.
     *
     * @param string $groupName
     *
     * @throws \Exception
     * @return mixed
     */
    private
    function findGroup(
        $groupName
    ) {
        static $group_list;
        if (!$groupName) {
            return false;
        }
        if (!$group_list) {
            $list = $this->Ckan->group_list(true);
            $list = json_decode($list, true);
            if (!$list['success']) {
                throw new \Exception('Could not retrieve group list');
            }
            $group_list = $list['result'];
        }

        foreach ($group_list as $group) {
            if (stristr(json_encode($group), $groupName)) {
                return $group;
            }
        }

        return false;
    }

    /**
     * @param $organizations_list
     * @param $results_dir
     */
    public
    function search_by_organizations(
        $organizations_list,
        $results_dir
    ) {
        $this->logOutput     = '';
        $log_file_popularity = $results_dir . '/search_' . sizeof(
                $organizations_list
            ) . '_organizations_by_popularity.csv';
        $log_file_relevance  = $results_dir . '/search_' . sizeof(
                $organizations_list
            ) . '_organizations_by_relevance.csv';
        $error_log           = $results_dir . '/search_' . sizeof($organizations_list) . '_organizations.log';

        $fp_popularity = fopen($log_file_popularity, 'w');
        $fp_relevance  = fopen($log_file_relevance, 'w');

        $csv_header = [
            'Name of Dataset',
            'Agency',
            'Data.gov URL',
        ];

        fputcsv($fp_popularity, $csv_header);
        fputcsv($fp_relevance, $csv_header);

        $ckan_url = 'http://catalog.data.gov/dataset/';

        $i = 1;

//        most relevant:
//        http://catalog.data.gov/api/3/action/package_search?q=Asian+AND+dataset_type:dataset&sort=score+desc
//        most popular:
//        http://catalog.data.gov/api/3/action/package_search?q=Asian+AND+dataset_type:dataset&sort=views_recent+desc
        foreach ($organizations_list as $organization) {
            $this->say(PHP_EOL . $i++ . '/' . sizeof($organizations_list) . ' : ' . $organization);
            if (!sizeof($organization = trim($organization))) {
                continue;
            }

//            defaults
            $ckan_query = '';

            switch ($organization) {
                case 'Federal Highway Administration':
                    $ckan_query = 'publisher:"Federal Highway Administration" AND dataset_type:dataset';
                    break;
                default:
                    $organization_term = $this->findOrganization($organization);

                    if (!$organization_term) {
                        $this->say('Could not find organization: ' . $organization);
                        continue;
                    }

                    $ckan_query = 'organization:(' . $organization_term . ')' . ' AND dataset_type:dataset';
                    break;
            }

            $only_first_page = true;
//            if ('Demographics' == $term) {
//                $only_first_page = false;
//            }

            $done     = false;
            $start    = 0;
            $per_page = 20;
            while (!$done) {
                // relevance
                $ckanResultRelevance = $this->Ckan->package_search($ckan_query, $per_page, $start);
                $ckanResultRelevance = json_decode($ckanResultRelevance, true); //  decode json as array
                $ckanResultRelevance = $ckanResultRelevance['result'];

                $count = $ckanResultRelevance['count'];
                $this->say($start . '/' . $count . ' by relevance');
                if (!$count) {
                    $done = true;
                    continue;
                }

                if (sizeof($ckanResultRelevance['results'])) {
                    foreach ($ckanResultRelevance['results'] as $dataset) {
                        fputcsv(
                            $fp_relevance,
                            [
                                isset($dataset['title']) ? $dataset['title'] : '---',
                                $organization,
                                isset($dataset['name']) ?
                                    $ckan_url . $dataset['name'] : '---'
                            ]
                        );
                    }
                } else {
                    $this->say('no results: ' . $organization);
                    continue;
                }

                if ($only_first_page) {
                    $done = true;
                    continue;
                }

                $start += $per_page;
                if ($start > $count) {
                    $done = true;
                }
            }

            $done     = false;
            $start    = 0;
            $per_page = 20;
            while (!$done) {
                // popularity
                $ckanResultPopularity = $this->Ckan->package_search(
                    $ckan_query,
                    $per_page,
                    $start,
                    'q',
                    'views_recent desc,name asc'
                );
                $ckanResultPopularity = json_decode($ckanResultPopularity, true); //  decode json as array
                $ckanResultPopularity = $ckanResultPopularity['result'];

                $count = $ckanResultPopularity['count'];
                $this->say($start . '/' . $count . ' by popularity');
                if (!$count) {
                    $done = true;
                    continue;
                }

                if (sizeof($ckanResultPopularity['results'])) {
                    foreach ($ckanResultPopularity['results'] as $dataset) {
                        fputcsv(
                            $fp_popularity,
                            [
                                isset($dataset['title']) ? $dataset['title'] : '---',
                                $organization,
                                isset($dataset['name']) ?
                                    $ckan_url . $dataset['name'] : '---',
                            ]
                        );
                    }
                } else {
                    $this->say('no results: ' . $organization);
                    continue;
                }

                if ($only_first_page) {
                    $done = true;
                    continue;
                }

                $start += $per_page;
                if ($start > $count) {
                    $done = true;
                }
            }
        }

        fclose($fp_relevance);
        fclose($fp_popularity);

        file_put_contents($error_log, $this->logOutput, FILE_APPEND);
        $this->logOutput = '';
    }

    /**
     * @param string $organizationName
     *
     * @throws \Exception
     * @return mixed
     */
    private
    function findOrganization(
        $organizationName
    ) {
        static $OrgList;
        if (!$OrgList) {
            $OrgList = new OrganizationList(AGENCIES_LIST_URL);
        }

        return $OrgList->getTermFor($organizationName);
    }

    /**
     * Export all packages by organization term
     *
     * @param $terms
     * @param $results_dir
     */
    public
    function export_packages_by_org_terms(
        $terms,
        $results_dir
    ) {
        $this->say(ORGANIZATION_TO_EXPORT . PHP_EOL);
        $ckan_url = 'https://catalog.data.gov/dataset/';

        foreach ($terms as $term => $agency) {
            $page    = 0;
            $count   = 0;
            $results = [];

            $csv_fp = fopen($results_dir . '/' . $term . '.csv', 'w');

//                csv for title, url, topic, and topic category

            $csv_header = [
                'Title',
                'Url',
                'Topics',
                'Topics categories',
            ];

            fputcsv($csv_fp, $csv_header);

            while (true) {
                $start      = $page++ * $this->packageSearchPerPage;
                $ckanResult = $this->Ckan->package_search(
                    'organization:' . $term,
                    $this->packageSearchPerPage,
                    $start
                );
                $ckanResult = json_decode($ckanResult, true); //  decode json as array
                $ckanResult = $ckanResult['result'];
                $results    = array_merge($results, $ckanResult['results']);

//                csv for title, url, topic, and topic category
                $datasets = $ckanResult['results'];
                foreach ($datasets as $dataset) {

                    $extras = $dataset['extras'];

                    $categories_tags = [];
                    foreach ($extras as $extra) {
                        if (false !== strpos($extra['key'], '__category_tag_')) {
                            $tags = trim($extra['value'], '[]');
                            $tags = explode('","', $tags);
                            foreach ($tags as &$tag) {
                                $tag = trim($tag, '" ');
                            }
                            $categories_tags = array_merge($categories_tags, $tags);
                        }
                    }
                    $categories_tags = sizeof($categories_tags) ? join(';', $categories_tags) : false;
                    $categories      = [];
                    if (sizeof($dataset['groups'])) {
                        foreach ($dataset['groups'] as $group) {
                            $categories[] = $group['title'];
                        }
                    }
                    $categories = sizeof($categories) ? join(';', $categories) : false;

                    fputcsv(
                        $csv_fp,
                        [
                            isset($dataset['title']) ? $dataset['title'] : '---',
                            isset($dataset['name']) ? $ckan_url . $dataset['name'] : '---',
                            $categories ? $categories : '---',
                            $categories_tags ? $categories_tags : '---'
                        ]
                    );
                }

                $count = $ckanResult['count'];
                if ($start) {
                    echo "start from $start / " . $count . ' total ' . PHP_EOL;
                }

                if ($ckanResult['count'] - $this->packageSearchPerPage < $start) {
                    break;
                }
            }

            $offset = ($term == PARENT_TERM) ? '' : '  ';
            $this->say(
                str_pad($offset . "[$term]", 20) . str_pad(
                    $offset . $agency,
                    50,
                    ' .'
                ) . "[$count]"
            );

            fclose($csv_fp);
            if (sizeof($results)) {
                $json = (json_encode($results, JSON_PRETTY_PRINT));
                file_put_contents($results_dir . '/' . $term . '.json', $json);
            } else {
                unlink($results_dir . '/' . $term . '.csv');
            }
        }
        file_put_contents($results_dir . '/_' . PARENT_TERM . '.log', $this->logOutput);
    }

    /**
     * Export all dataset visit tracking by organization term
     *
     * @param $terms
     * @param $results_dir
     */
    public
    function export_tracking_by_org_terms(
        $terms,
        $results_dir
    ) {
        $this->logOutput = '';
        $this->say(ORGANIZATION_TO_EXPORT . PHP_EOL);
        foreach ($terms as $term => $agency) {

            $fp = fopen($results_dir . '/' . $term . '.csv', 'w');

            $csv_header = [
                'Organization',
                'Dataset Title',
                'Recent Visits',
                'Total Visits',
            ];

            fputcsv($fp, $csv_header);

            $page  = 0;
            $count = 0;
            while (true) {
                $start      = $page++ * $this->packageSearchPerPage;
                $ckanResult = $this->Ckan->package_search(
                    'organization:' . $term,
                    $this->packageSearchPerPage,
                    $start
                );
                $ckanResult = json_decode($ckanResult, true); //  decode json as array
                $ckanResult = $ckanResult['result'];

                if (sizeof($ckanResult['results'])) {
                    foreach ($ckanResult['results'] as $dataset) {
                        fputcsv(
                            $fp,
                            [
                                isset($dataset['organization']) && isset($dataset['organization']['title']) ?
                                    $dataset['organization']['title'] : '---',
                                isset($dataset['title']) ? $dataset['title'] : '---',
                                isset($dataset['tracking_summary']) && isset($dataset['tracking_summary']['recent']) ?
                                    $dataset['tracking_summary']['recent'] : 0,
                                isset($dataset['tracking_summary']) && isset($dataset['tracking_summary']['total']) ?
                                    $dataset['tracking_summary']['total'] : 0,
                            ]
                        );
                    }
                }

                $count = $ckanResult['count'];
                if ($start) {
                    echo "start from $start / " . $count . ' total ' . PHP_EOL;
                }
                if ($ckanResult['count'] - $this->packageSearchPerPage < $start) {
                    break;
                }
            }

            fclose($fp);

            $offset = ($term == PARENT_TERM) ? '' : '  ';
            $this->say(
                str_pad($offset . "[$term]", 20) . str_pad(
                    $offset . $agency,
                    50,
                    ' .'
                ) . "[$count]"
            );
        }
        file_put_contents($results_dir . '/_' . PARENT_TERM . '.log', $this->logOutput);
    }

    /**
     * Ability to tag datasets by extra field
     *
     * @param string $extra_field
     * @param string $tag_name
     * @param string $results_dir
     */
    public
    function tag_by_extra_field(
        $extra_field,
        $tag_name,
        $results_dir
    ) {
        $this->logOutput = '';
        $page            = 0;
        $processed       = 0;
        $tag_template    = [
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
                if (!isset($dataset['extras']) || !is_array($dataset['extras']) || !sizeof(
                        $dataset['extras']
                    )
                ) {
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

                $this->say($dataset['name']);

                $this->package_update($dataset);
                $marked_true++;
            }

            echo "processed $processed ( $tag_name true = $marked_true, other = $marked_other) / " . $count . ' total ' . PHP_EOL;
            if ($count - $this->packageSearchPerPage < $start) {
                break;
            }
        }
        file_put_contents($results_dir . '/_' . $tag_name . '.log', $this->logOutput);
    }

    /**
     * @param $dataset_id
     * @param $results_dir
     * @param $basename
     */
    public
    function make_dataset_private(
        $dataset_id,
        $results_dir,
        $basename
    ) {
        $this->logOutput = '';

        $this->say(str_pad($dataset_id, 105, ' . '), '');

        $dataset = $this->try_package_show($dataset_id);

        if (!$dataset) {
            $this->say(str_pad('NOT_FOUND', 10, ' '));
        } else {

            $dataset['private'] = true;

            try {
                $this->package_update($dataset);
                $this->say(str_pad('OK', 10, ' '));
            } catch (\Exception $ex) {
                $this->say(str_pad('ERROR', 10, ' '));
//                die(json_encode($dataset, JSON_PRETTY_PRINT) . PHP_EOL . $ex->getMessage() . PHP_EOL . PHP_EOL);
                file_put_contents($results_dir . '/err.log', $ex->getMessage() . PHP_EOL, FILE_APPEND);
            }
        }
        file_put_contents($results_dir . '/_' . $basename . '.log', $this->logOutput, FILE_APPEND);
    }

    /**
     * Ability to Add legacy tag to all dms datasets for an organization and make all those datasets private
     *
     * @param $termsArray
     * @param $tag_name
     */
    public
    function tag_legacy_dms(
        $termsArray,
        $tag_name
    ) {
        $this->logOutput = '';

//        get all datasets to update
        $datasets = $this->get_dms_public_datasets($termsArray);

        $count = sizeof($datasets);

        $log_file = PARENT_TERM . "_add_legacy_make_private.log";

        $csv = new Writer($this->resultsDir . '/' . PARENT_TERM . date('_Ymd-His') . '.csv');

        $this->expectedFieldsDiff = [
            'name',
            'num_tags',
            'extras',
        ];

//        update dataset tags list
        foreach ($datasets as $key => $dataset) {
            echo str_pad("$key / $count ", 10, ' ');

            $csv->writeRow(
                [
//                    $dataset['organization']['name'],
                    $dataset['name'],
                    $newDatasetName = $dataset['name'] . '_legacy',
                ]
            );

            if (LIST_ONLY) {
                $this->say('http://catalog.data.gov/dataset/' . $dataset['name']);
            } else {
                $this->say(str_pad($dataset['name'], 100, ' . '), '');

                $dataset['tags'][] = [
                    'name' => $tag_name,
                ];

                if (defined('MARK_PRIVATE') && MARK_PRIVATE) {
                    $dataset['private'] = true;
                }

                if (defined('RENAME_TO_LEGACY') && RENAME_TO_LEGACY) {
                    $dataset['name'] = $newDatasetName;
                }

                try {
                    $this->package_update($dataset);
                    $this->say(str_pad('OK', 7, ' '));
                } catch (\Exception $ex) {
                    $this->say(str_pad('ERROR', 7, ' '));
//                die(json_encode($dataset, JSON_PRETTY_PRINT) . PHP_EOL . $ex->getMessage() . PHP_EOL . PHP_EOL);
                    file_put_contents($this->resultsDir . '/err.log', $ex->getMessage() . PHP_EOL, FILE_APPEND);
                }
            }

            file_put_contents($this->resultsDir . '/' . $log_file, $this->logOutput, FILE_APPEND);
            $this->logOutput = '';
        }
    }

    /**
     * Use organization terms array to filter, use null to tag all datasets
     *
     * @param array $terms
     * @param int $options
     *
     * @return array
     */
    private
    function get_dms_public_datasets(
        $terms = null,
        $options = 0
    ) {
        $dms_datasets = [];
        $page         = 0;

        if ($terms) {
            $organizationFilter = array_keys($terms);
            // & = ugly hack to prevent 'Unused local variable' error by PHP IDE, it works perfect without &
            array_walk(
                $organizationFilter,
                function (&$term) {
                    $term = ' organization:"' . $term . '" ';
                }
            );
            $organizationFilter = ' AND (' . join(' OR ', $organizationFilter) . ')';
        } else {
            $organizationFilter = '';
        }

        while (true) {
            $start      = $page++ * $this->packageSearchPerPage;
            $ckanResult = $this->Ckan->package_search(
                'dms' . $organizationFilter,
                $this->packageSearchPerPage,
                $start
            );
            $ckanResult = json_decode($ckanResult, true); //  decode json as array
            $ckanResult = $ckanResult['result'];
            foreach ($ckanResult['results'] as $dataset) {
                if (!isset($dataset['extras']) || !is_array($dataset['extras']) || !sizeof(
                        $dataset['extras']
                    )
                ) {
                    continue;
                }
                if (strpos(json_encode($dataset['extras']), '"dms"')) {
                    if ($options & self::IDS_ONLY) {
                        $dms_datasets[] = $dataset['name'];
                    } else {
                        $dms_datasets[] = $dataset;
                    }
                }
            }
            $count = $ckanResult['count'];
            if ($start) {
                echo "start from $start / " . $count . ' total ' . PHP_EOL;
            }
            if ($ckanResult['count'] - $this->packageSearchPerPage < $start) {
                break;
            }
        }

        return $dms_datasets;
    }

    /**
     * Exports all organizations associated with the department
     *
     * @param $termsArray
     * @param $results_dir
     */
    public
    function export_organizations(
        $termsArray,
        $results_dir
    ) {

        foreach ($termsArray as $org_slug => $org_name) {

            try {
                $results = $this->Ckan->organization_show($org_slug);
            } catch (NotFoundHttpException $ex) {
                echo "Couldn't find $org_slug";
                continue;
            }

            if ($results) {
                $results = json_decode($results);

                $json = (json_encode($results, JSON_PRETTY_PRINT));
                file_put_contents($results_dir . '/' . $org_slug . '.json', $json);
            }

        }

    }

    /**
     * @param $organization
     * @param int $options
     */
    public function full_organization_export($organization, $options = 0)
    {
        if (($options & self::EXPORT_PUBLIC_ONLY) && ($options & self::EXPORT_DMS_ONLY)) {
            $members = $this->get_dms_public_datasets([$organization => $organization], self::IDS_ONLY);
        } else {
            $members = $this->try_member_list($organization);
        }

        if (!$members || !is_array($members) || !sizeof($members)) {
            $this->say(join(',', [$organization, 0]));
//            $this->say(sprintf('%25s%10d', $organization, 0));
            return;
        }

        $export    = [];
        $exportIds = [];
        foreach ($members as $package) {
            //  member_list returns weird array
            $id      = is_array($package) ? $package[0] : $package;
            $dataset = $this->try_package_show($id);

            if ('dataset' != $dataset['type']) {
                echo $id . ' :: NOT A DATASET' . PHP_EOL;
                continue;
            }

            if ('deleted' == $dataset['state']) {
                echo $id . ' :: DELETED' . PHP_EOL;
                continue;
            }

            if ($options & self::EXPORT_PRIVATE_ONLY && !$dataset['private']) {
                echo $id . ' :: IS PUBLIC' . PHP_EOL;
                continue;
            }

            if ($options & self::EXPORT_PUBLIC_ONLY && $dataset['private']) {
                echo $id . ' :: IS PRIVATE' . PHP_EOL;
                continue;
            }

            $id      = $dataset['name'];
            $dataset = json_encode($dataset, JSON_PRETTY_PRINT);

            if ($options & self::EXPORT_DMS_ONLY
                && !(strstr($dataset, '"dms"') && strstr($dataset, '"metadata-source"'))
            ) {
                echo $id . ' :: IS DMS' . PHP_EOL;
                continue;
            }

            $exportIds[] = $id;
            $export[]    = $dataset;
        }

        $total = sizeof($exportIds);
//        $this->say(sprintf('%25s%10d', $organization, $total));
        $this->say(join(',', [$organization, $total]));

        if (!$total) {
            return;
        }

        $suffix = ($options & self::EXPORT_PUBLIC_ONLY ? '_PUBLIC' : '')
            . ($options & self::EXPORT_PRIVATE_ONLY ? '_PRIVATE' : '')
            . ($options & self::EXPORT_DMS_ONLY ? '_DMS' : '');

        mkdir($this->resultsDir . '/' . $organization);

        file_put_contents($this->resultsDir . '/' . $organization . '/' . $organization . $suffix . '.json',
            '[' . join(',' . PHP_EOL, $export) . ']');

        file_put_contents($this->resultsDir . '/' . $organization . '/' . $organization . $suffix . '.csv',
            join(PHP_EOL, $exportIds));

        file_put_contents(
            $this->resultsDir . '/export_orgs' . $suffix . '.csv',
            join(',' . $organization . PHP_EOL, $exportIds) . ',' . $organization . PHP_EOL,
            FILE_APPEND | LOCK_EX);
    }

    /**
     * @param string $id
     * @param int $try
     *
     * @return bool|mixed
     */
    private
    function try_member_list(
        $id,
        $try = 3
    ) {
        $list = false;
        while ($try) {
            try {
                $list = $this->Ckan->member_list($id);
                $list = json_decode($list, true); // as array

                if (!$list['success']) {
//                    echo 'No success: ' . $id . PHP_EOL;

                    return false;
                }

                if (!isset($list['result']) || !sizeof($list['result'])) {
//                    echo 'No result: ' . $id . PHP_EOL;

                    return false;
                }

                $list = $list['result'];

                $try = 0;
            } catch (NotFoundHttpException $ex) {
                echo "Organization not found: " . $id . PHP_EOL;

                return false;
            } catch (\Exception $ex) {
                sleep(3);
                echo '      zzz   ' . $id . PHP_EOL;
                $try--;
                if (!$try) {
                    echo 'Too many attempts: ' . $id . PHP_EOL;

                    return false;
                }
            }
        }

        if (defined('ERROR_REPORTING') && ERROR_REPORTING == E_ALL) {
            echo "Member list: " . sizeof($list) . ' records' . PHP_EOL;
        }

        return $list;
    }

    /**
     * Exports information about active users
     *
     * @param $results_dir
     */
    public
    function organizations_stats(
        $results_dir
    ) {
        /**
         * curl --data '{"all_fields":true}' "https://catalog.data.gov/api/action/organization_list" > organizations.json
         */
        $isInventory = false;
        if (false === strstr($this->apiUrl, 'https')) {
            $ckan_url = 'https://catalog.data.gov/';
            $orgs     = file_get_contents(DATA_DIR . '/organizations.json');
            $filename = '/catalog_orgs_list_' . (START ?: '') . '.csv';
        } else {
            $isInventory = true;
            $ckan_url    = 'https://inventory.data.gov/';
            $orgs        = $this->Ckan->organization_list();
            $filename    = '/inventory_orgs_list_' . (START ?: '') . '.csv';
        }

        $filter = false;
        if (is_file(DATA_DIR . '/organizations_stats_filter.csv')) {
            $filter = array_filter(explode(PHP_EOL, file_get_contents(DATA_DIR . '/organizations_stats_filter.csv')));
            if (!sizeof($filter)) {
                $filter = false;
            }
        }

        $orgs = json_decode($orgs);
        $orgs = $orgs->result;


        // initialize log file
        $log_file = $results_dir . $filename;
        $fp_log   = fopen($log_file, 'w');

        $csv_header = [
            'Organization ID',
            'Organization Name',
            'Organization URL',
            'Public Packages (incl. harv.src)',
//            'Number of Private Datasets',
            'Number of Members (incl. private)',
            'State: Active',
            'State: Draft',
            'State: Draft-complete',
            'State: Deleted',
        ];

        if (!$isInventory) {
            $csv_header[] = 'DMS Private';
            $csv_header[] = 'DMS Public';
            $csv_header[] = 'Non-DMS Public';
        }

        fputcsv($fp_log, $csv_header);

        $total        = $filter ? sizeof($filter) : sizeof($orgs);
        $digits_count = strlen('' . $total);
        $i            = 0;
        $skip         = (bool)START;
        foreach ($orgs as $org) {
            $org_slug = is_object($org) ? $org->name : $org;

            if ($filter && !in_array($org_slug, $filter)) {
                continue;
            }

            $i++;

            if (START && START == $org_slug) {
                $skip = false;
            }

            if (STOP && STOP == $org_slug) {
                $skip = true;
            }

            if ($skip) {
                continue;
            }

            try {
                $org_results = $this->Ckan->organization_show($org_slug);
            } catch (NotFoundHttpException $ex) {
                echo "Couldn't find org " . $org_slug . PHP_EOL;
                continue;
            }

            if ($org_results) {
                $org_results = json_decode($org_results);
                $org_results = $org_results->result;

                printf("[%0{$digits_count}d/%0{$digits_count}d] %s" . PHP_EOL, $i, $total, $org_slug);

                $member_list = $this->try_member_list($org_slug);
//                $private_count = 'na';
                $total_count    = 'na';
                $package_states = [];
                $dms_public     = $dms_private = $non_dms_public = 0;

                if (is_array($member_list)) {
                    $total_count         = sizeof($member_list);
                    $member_digits_count = strlen('' . $total_count);
//                    $private_count = $total_count - $org_results->package_count;

                    $j = 0;
                    foreach ($member_list as $member) {
                        if (!(++$j % 1000)) {
                            printf("   [%0{$member_digits_count}d/%0{$member_digits_count}d] members of: %s"
                                . PHP_EOL, $j, $total_count, $org_slug);
                        }
                        $id      = $member[0];
                        $package = $this->try_package_show($id);
                        if ('dataset' !== $package['type']) {
                            continue;
                        }
//                        file_put_contents($resultsDir.'/'.$id.'.json',
//                            json_encode($package, JSON_PRETTY_PRINT));
//                        die();
                        $state = $package['state'];
                        if (!isset($package_states[$state])) {
                            $package_states[$state] = 1;
                        } else {
                            $package_states[$state]++;
                        }

                        if (!$isInventory) {
                            if (isset($package['extras'])) {
                                $extras = json_encode($package['extras']);
                                if (strpos($extras, '"dms"')) {
                                    if ($package['private']) {
                                        $dms_private++;
                                    } else {
                                        $dms_public++;
                                    }
                                } else {
                                    if (!$package['private']) {
                                        $non_dms_public++;
                                    }
                                }
                            } elseif (!$package['private']) {
                                $non_dms_public++;
                            }
                        }
                    }
                }

                $organization_row = [
                    $org_results->name,
                    $org_results->title,
                    $ckan_url . 'organization/' . $org_results->name,
                    $org_results->package_count,
//                    $private_count,
                    $total_count,
                    isset($package_states['active']) ? $package_states['active'] : 0,
                    isset($package_states['draft']) ? $package_states['draft'] : 0,
                    isset($package_states['draft-complete']) ? $package_states['draft-complete'] : 0,
                    isset($package_states['deleted']) ? $package_states['deleted'] : 0,
                ];

                if (!$isInventory) {
                    $organization_row[] = $dms_private;
                    $organization_row[] = $dms_public;
                    $organization_row[] = $non_dms_public;
                }

                fputcsv($fp_log, $organization_row);

//                if ($users = $org_results->result->users) {
//
//                    foreach ($users as $org_user) {
//                        $user_id = $org_user->name;
//
//                        try {
//                            $user_results = $this->Ckan->user_show($user_id);
//                        } catch (NotFoundHttpException $ex) {
//                            echo "Couldn't find user $user_id";
//                            continue;
//                        }
//
//                        if ($user_results) {
//                            $user_results = json_decode($user_results);
//
//                            if ($user = $user_results->result) {
//
//                                $last_activity = (!empty($user->activity[0]->timestamp)) ? $user->activity[0]->timestamp : '';
//
//                                $user_row = array(
//                                    $org_results->result->title,
//                                    $org_results->result->name,
//                                    $user->fullname,
//                                    $user->name,
//                                    $user->email,
//                                    $user->number_administered_packages,
//                                    $user->number_of_edits,
//                                    $org_user->capacity,
//                                    $user->sysadmin,
//                                    $last_activity
//                                );
//
//                                $this->say("Exporting Organization: {$org_results->result->title}, User: {$user->fullname} ({$user->name})");
//
//                                fputcsv($fp_log, $user_row);
//
//                            }
//                        }
//                    }
//                }
            }
        }

        // close log file
        fclose($fp_log);
    }

    /**
     * Exports information about active users
     *
     * @param $results_dir
     */
    public
    function active_users(
        $results_dir
    ) {
        /**
         * curl --data '{"all_fields":true}' "https://catalog.data.gov/api/action/organization_list" > organizations.json
         */
        if (false === strstr($this->apiUrl, 'https')) {
            $orgs = file_get_contents(DATA_DIR . '/organizations.json');
        } else {
            $orgs = $this->Ckan->organization_list();
        }

        $orgs = json_decode($orgs);
        $orgs = $orgs->result;


        // initialize log file
        $log_file = $results_dir . '/user_list.csv';
        $fp_log   = fopen($log_file, 'w');

        $csv_header = [
            'Organization Name',
            'Organization ID',
            'User Name',
            'User ID',
            'User Email',
            'User Dataset Count',
            'User Dataset Edits',
            'User Role',
            'User Sysadmin',
            'User Last Activity',
        ];

        fputcsv($fp_log, $csv_header);

        foreach ($orgs as $org) {
            $org_slug = $org->name;

            try {
                echo $org_slug . PHP_EOL;
                $org_results = $this->Ckan->organization_show($org_slug);
            } catch (NotFoundHttpException $ex) {
                echo "Couldn't find org $org_slug";
                continue;
            }

            if ($org_results) {
                $org_results = json_decode($org_results);

                if ($users = $org_results->result->users) {

                    foreach ($users as $org_user) {
                        $user_id = $org_user->name;

                        try {
                            $user_results = $this->Ckan->user_show($user_id);
                        } catch (NotFoundHttpException $ex) {
                            echo "Couldn't find user $user_id";
                            continue;
                        }

                        if ($user_results) {
                            $user_results = json_decode($user_results);

                            if ($user = $user_results->result) {

                                $last_activity = (!empty($user->activity[0]->timestamp)) ? $user->activity[0]->timestamp : '';

                                $user_row = [
                                    $org_results->result->title,
                                    $org_results->result->name,
                                    $user->fullname,
                                    $user->name,
                                    $user->email,
                                    $user->number_administered_packages,
                                    $user->number_of_edits,
                                    $org_user->capacity,
                                    $user->sysadmin,
                                    $last_activity
                                ];

                                $this->say("Exporting Organization: {$org_results->result->title}, User: {$user->fullname} ({$user->name})");

                                fputcsv($fp_log, $user_row);

                            }
                        }


                    }

                }

            }

        }

        // close log file
        fclose($fp_log);

    }

    /**
     * @param $datasetName
     * @param $organizationName
     */
    public function deleteDataset($datasetName, $organizationName)
    {
        $dataset = $this->try_package_show($datasetName);
        if (!$dataset) {
            $this->say(join(',', [$datasetName, $organizationName, '404 Not Found']));
            return;
        }

        if ('deleted' == $dataset['state']) {
            $this->say(join(',', [$datasetName, $organizationName, 'Already deleted']));
            return;
        }

        if (!isset($dataset['private']) || !$dataset['private']) {
            $this->say(join(',', [$datasetName, $organizationName, 'Not private']));
            return;
        }

        $result = $this->try_package_delete($datasetName);
        if ($result) {
            $this->say(join(',', [$datasetName, $organizationName, 'DELETED']));
            return;
        }

        $this->say(join(',', [$datasetName, $organizationName, 'ERROR']));
    }

    /**
     * @param $datasetId
     * @param int $try
     *
     * @return bool
     */
    private function try_package_delete($datasetId, $try = 3)
    {
        while ($try) {
            try {
                $result = $this->Ckan->package_delete($datasetId);
                $result = json_decode($result, true); // as array

                if (!isset($result['success']) || !$result['success']) {
                    throw new \Exception('Could not delete dataset');
                }
                return true;
            } catch (NotFoundHttpException $ex) {
                echo "Datasets not found " . PHP_EOL;

                return false;
            } catch (\Exception $ex) {
                $try--;
                sleep(3);
                echo '      zzz   ' . PHP_EOL;
                if (!$try) {
                    echo 'Too many attempts ' . PHP_EOL;

                    return false;
                }
            }
        }
        return false;
    }

    /**
     * @param $resource
     */
    public function resourceCreate($resource)
    {
        $dataset_name_or_id = $resource['package_id'];
        $dataset            = $this->try_package_show($dataset_name_or_id);
        if (!$dataset) {
            $this->say(join(',',
                [$dataset_name_or_id, $resource['url'], 'ERROR', 'could not find dataset by id/name']));
            return;
        }

        if ('dataset' !== $dataset['type']) {
            $this->say(join(',', [$dataset_name_or_id, $resource['url'], 'ERROR', 'package is not of type "dataset"']));
            return;
        }

        $existing_resources = json_encode($dataset['resources']);
        if (strstr($existing_resources, $resource['url']) || strstr($existing_resources, json_encode($resource['url']))
        ) {
            $this->say(join(',',
                [$dataset_name_or_id, $resource['url'], 'ERROR', 'same package url already exists in this dataset']));
            return;
        }

        $resource['package_id'] = $dataset['id'];

        $result = $this->try_resource_create($resource);

        $this->say(join(',', [$dataset_name_or_id, $resource['url'], 'SUCCESS', $result['id']]));
        return;

    }

    /**
     * @param $resource_json
     * @param int $try
     *
     * @return bool|mixed
     */
    private function try_resource_create(
        $resource_json,
        $try = 3
    ) {
        $resource = false;
        while ($try) {
            try {
                $resource = $this->Ckan->resource_create($resource_json);
                $resource = json_decode($resource, true); // as array

                if (!$resource['success']) {
                    echo 'No success: ' . $resource_json['url'] . PHP_EOL;
                    return false;
                }

                if (!isset($resource['result']) || !sizeof($resource['result'])) {
                    echo 'No result: ' . $resource_json['url'] . PHP_EOL;
                    return false;
                }

                $resource = $resource['result'];

                $try = 0;
            } catch (\Exception $ex) {
                $try--;
                sleep(3);
                echo '      zzz   ' . $resource_json['url'] . PHP_EOL;
                if (!$try) {
                    echo 'Too many attempts: ' . $resource_json['url'] . PHP_EOL;

                    return false;
                }
            }
        }

        return $resource;
    }

    /**
     * Rename $dataset['name'], preserving all the metadata
     *
     * @param $datasetName
     * @param $newDatasetName
     * @param $results_dir
     * @param $basename
     */
    public
    function renameDataset(
        $datasetName,
        $newDatasetName,
        $results_dir,
        $basename
    ) {
        $this->logOutput = '';
        $log_file        = $basename . '_rename.log';

        $this->say(str_pad($datasetName, 100, ' . '), '');

        try {
            $ckanResult = $this->Ckan->package_show($datasetName);
        } catch (NotFoundHttpException $ex) {
            $this->say(str_pad('NOT FOUND', 10, ' . ', STR_PAD_LEFT));
            file_put_contents($results_dir . '/' . $log_file, $this->logOutput, FILE_APPEND);
            $this->logOutput = '';

            return;
        }

        $ckanResult = json_decode($ckanResult, true);
        $dataset    = $ckanResult['result'];

        $dataset['name'] = $newDatasetName;

        try {
            $this->package_update($dataset);
            $this->say(str_pad('OK', 7, ' '));
        } catch (\Exception $ex) {
            $this->say(str_pad('ERROR', 7, ' '));
            file_put_contents($results_dir . '/err.log', $ex->getMessage() . PHP_EOL, FILE_APPEND);
        }

        file_put_contents($results_dir . '/' . $log_file, $this->logOutput, FILE_APPEND);
        $this->logOutput = '';
    }

    /**
     * Moves legacy datasets to parent organization
     *
     * @param $organization
     * @param $termsArray
     * @param $backup_dir
     * @param $results_dir
     */
    public
    function reorganize_datasets(
        $organization,
        $termsArray,
        $backup_dir,
        $results_dir
    ) {

        // Make sure we get the id for the parent organization (department)
        foreach ($termsArray as $org_slug => $org_name) {
            if ($org_name == $organization) {
                $department = $org_slug;
            }
        }
        reset($termsArray);

        // Set up logging
        $this->logOutput = '';
        $time            = time();
        $log_file        = (isset($department) ? $department : '_') . '_' . "$time.log";

        if (!empty($department)) {

            // Get organization id for department
            $results = $this->Ckan->organization_show($department);
            $results = json_decode($results);

            $department_id = $results->result->id;
        }

        if (!empty($department_id)) {

            $output = "Reorganizing $organization (id: $department_id / name: " . (isset($department) ? $department : '-') . ")" . PHP_EOL;
            $this->say($output);

            foreach ($termsArray as $org_slug => $org_name) {

                // Skip department level org
                if (isset($department) && $org_slug == $department) {
                    continue;
                }

                // set backup file path
                $file_path = $backup_dir . '/' . $org_slug . '.json';

                if (file_exists($file_path)) {

                    $output = PHP_EOL . "Reorganizing $org_name ($org_slug)" . PHP_EOL;
                    $this->say($output);

                    // load backup file
                    $json = file_get_contents($file_path);
                    $json = json_decode($json);

                    foreach ($json as $record) {
                        $current_record = $record->id;

                        // load current version of record
                        $ckanResult = $this->Ckan->package_show($current_record);
                        $dataset    = json_decode($ckanResult, true);

                        $dataset = $dataset['result'];

                        // note the legacy organization as an extra field
                        $dataset['extras'][] = [
                            'key'   => 'dms_publisher_organization',
                            'value' => $org_slug
                        ];

                        $dataset['owner_org'] = $department_id;

                        $this->package_update($dataset);

                        $output = 'Moved ' . $current_record;
                        $this->say($output);
                    }
                } else {
                    $output = "Couldn't find backup file: " . $file_path;
                    $this->say($output);
                }
            }
        }

        file_put_contents($results_dir . '/' . $log_file, $this->logOutput);

    }

    /**
     * @param $datasetName
     * @param $stagingDataset
     */
    public
    function diffUpdate(
        $datasetName,
        $stagingDataset
    ) {
        try {
            $freshDataset = $this->get_dataset($datasetName);
//            no exception, cool
            $this->say(str_pad('Prod OK', 15, ' . '));

            $freshExtras = [];
            foreach ($freshDataset['extras'] as $extra) {
                if (!strpos($extra['key'], 'category_tag')) {
                    $freshExtras[$extra['key']] = true;
                }
            }

            $diff = [];
            foreach ($stagingDataset['extras'] as $extra) {
                if (!strpos($extra['key'], 'category_tag')) {
                    if (!isset($freshExtras[$extra['key']])) {
                        $diff[] = $extra;
                    }
                }
            }

            $freshDataset['extras'] = array_merge($freshDataset['extras'], $diff);

            $this->package_update($freshDataset);

        } catch (NotFoundHttpException $ex) {
            $this->say(str_pad('Prod 404: ' . $ex->getMessage(), 15, ' . '));
        } catch (\Exception $ex) {
            $this->say(str_pad('Prod Error: ' . $ex->getMessage(), 15, ' . '));
        }
    }

    /**
     * @param $datasetName
     *
     * @return mixed
     * @throws \Exception
     */
    public
    function get_dataset(
        $datasetName
    ) {
        $dataset = $this->Ckan->package_show($datasetName);

        $dataset = json_decode($dataset, true);
        if (!$dataset['success']) {
            throw new \Exception('Dataset does not have "success" key');
        }

        $dataset = $dataset['result'];

        return $dataset;
    }

    /**
     * @param $group
     * @param $results_dir
     *
     * @throws \Exception
     */
    public
    function export_datasets_with_tags_by_group(
        $group,
        $results_dir
    ) {
        $this->logOutput = '';

        if (!($group = $this->findGroup($group))) {
            throw new \Exception('Group ' . $group . ' not found!' . PHP_EOL);
        }

        $log_file = $results_dir . '/export_group_' . $group['name'] . '_with_tags.csv';
        $fp       = fopen($log_file, 'w');

        $csv_header = [
            'Name of Dataset',
            'Dataset Link',
            'Topic Name',
            'Topic Categories',
        ];

        fputcsv($fp, $csv_header);

        $ckan_url = 'https://catalog.data.gov/dataset/';

        $ckan_query = $this->escapeSolrValue($group['name']) . ' AND dataset_type:dataset';

        $category_key = ('__category_tag_' . $group['id']);

        $start    = 0;
        $per_page = 50;

        echo $ckan_query . PHP_EOL;

        while (true) {
            $datasets = $this->try_package_search($ckan_query, $per_page, $start, 'fq', 5);
            if (!is_array($datasets) || !sizeof($datasets)) {
                break;
            }
            $start += $per_page;
            echo $start . '/' . sizeof($datasets) . PHP_EOL;

            foreach ($datasets as $dataset) {

                $extras = $dataset['extras'];

                $tags = false;
                foreach ($extras as $extra) {
                    if ($category_key == $extra['key']) {
                        $tags = trim($extra['value'], '[]');
                        $tags = explode('","', $tags);
                        foreach ($tags as &$tag) {
                            $tag = trim($tag, '" ');
                        }
                        $tags = join(';', $tags);
                        break;
                    }
                }

                fputcsv(
                    $fp,
                    [
                        isset($dataset['title']) ? $dataset['title'] : '---',
                        isset($dataset['name']) ? $ckan_url . $dataset['name'] : '---',
                        $group['title'],
                        $tags ? $tags : '---'
                    ]
                );
            }
        }

        fclose($fp);
    }

    /**
     * @param $search
     *
     * @throws \Exception
     */
    public
    function export_datasets_by_search(
        $search
    ) {
        $this->logOutput = '';

        $date                  = date('Ymd-His');
        $filename_strip_search = preg_replace("/[^a-zA-Z0-9\\ ]+/i", '', $search);
        $log_file              = $this->resultsDir . '/export_' . $filename_strip_search . '_' . $date . '.csv';
        $fp                    = fopen($log_file, 'w');

        $csv_header = [
            'data.gov url',
            'topic name'
        ];

        fputcsv($fp, $csv_header);

        $ckan_url = 'https://catalog.data.gov/dataset/';

//        $ckan_query = '((' . $search . ') AND (dataset_type:dataset))';
        $ckan_query = $search;
//        $ckan_query = '(organization:"ers-usda-gov")';
//        $ckan_query = 'metadata-source:dms';

        echo $ckan_query . PHP_EOL;

        $done     = false;
        $start    = 0;
        $per_page = 100;
        while (!$done) {
            echo $ckan_query . PHP_EOL;
            $ckanResult = $this->Ckan->package_search($ckan_query, $per_page, $start, 'q');

            $ckanResult = json_decode($ckanResult, true); //  decode json as array
            $ckanResult = $ckanResult['result'];

            $totalCount = $ckanResult['count'];
            $count      = sizeof($ckanResult['results']);

            echo "Found $totalCount results" . PHP_EOL;

            /**
             * Sample title:
             * export_fresc_20141117-121528_[1..33].json
             */
            $out_json_path = $this->resultsDir . '/export_' . $filename_strip_search . '_' . $date
                . '_[' . ($start + 1) . '..' . ($start + $count) . '].json';
            file_put_contents($out_json_path, json_encode($ckanResult['results'], JSON_PRETTY_PRINT));

            $start += $per_page;
            echo $start . '/' . $totalCount . PHP_EOL;
            if (!$totalCount) {
                $done = true;
                continue;
            }

            if (sizeof($ckanResult['results'])) {
                foreach ($ckanResult['results'] as $dataset) {
                    fputcsv(
                        $fp,
                        [
                            isset($dataset['name']) ? $ckan_url . $dataset['name'] : '---',
                            $dataset['title'],
                        ]
                    );
                }
            } else {
                echo 'no results: ' . $search . PHP_EOL;
                continue;
            }
            if ($start > $totalCount) {
                $done = true;
            }
        }

        fclose($fp);
    }

    /**
     * @param $topicTitle
     * @param $results_dir
     */
    public
    function cleanup_tags_by_topic(
        $topicTitle,
        $results_dir
    ) {
        $start = 0;
        $limit = 100;
        while (true) {
            $datasets = $this->try_package_search('(groups:' . $topicTitle . ')', $limit, $start);

//            Finish
            if (!$datasets) {
                break;
            }

            foreach ($datasets as $dataset) {
                if (!isset($dataset['groups']) || !sizeof($dataset['groups'])) {
                    continue;
                }
                $groups = $dataset['groups'];
                foreach ($groups as $group) {
                    $this->remove_tags_and_groups_to_datasets(
                        [$dataset['name']],
                        $group['name'],
                        'non-existing-tag&&',
                        $results_dir,
                        $topicTitle
                    );
                }
            }

            echo sizeof($datasets) . PHP_EOL;

//            Finish
            if (sizeof($datasets) < $limit) {
                break;
            }
            $start += $limit;
        }
    }

    /**
     * Remove groups & all group tags from dataset
     *
     * @param $datasetNames
     * @param $group_to_remove
     * @param $tags_to_remove
     * @param $basename
     *
     * @throws \Exception
     */
    public
    function remove_tags_and_groups_to_datasets(
        $datasetNames,
        $group_to_remove,
        $tags_to_remove,
        $basename
    ) {
        $this->logOutput = '';

//        Getting Group object
        if (!($group_to_remove = $this->findGroup($group_to_remove))) {
            throw new \Exception('Group ' . $group_to_remove . ' not found!' . PHP_EOL);
        }

//        Remove Group from each dataset given
        foreach ($datasetNames as $datasetName) {
            $this->say(str_pad($datasetName, 100, ' . '), '');

            try {
                $dataset = $this->Ckan->package_show($datasetName);
            } catch (NotFoundHttpException $ex) {
                $this->say(str_pad('NOT FOUND', 10, ' . ', STR_PAD_LEFT));
                continue;
            }

            $dataset = json_decode($dataset, true);
            if (!$dataset['success']) {
                $this->say(str_pad('NOT FOUND', 10, ' . ', STR_PAD_LEFT));
                continue;
            }

            $dataset = $dataset['result'];

//            Removing group from dataset found
            if (!$tags_to_remove) {
//            removing group
                $groups = [];
                foreach ($dataset['groups'] as $group) {
                    if ($group['name'] !== $group_to_remove['name']) {
                        $groups[] = $group;
                    }
                }

                if (sizeof($dataset['groups']) > sizeof($groups)) {
                    $this->say(str_pad('-GROUP', 8, ' . ', STR_PAD_LEFT), '');
                }

                $dataset['groups'] = $groups;
            }

//            removing extra tags for the removed group
            $category_tag = '__category_tag_' . $group_to_remove['id'];

            $extras = $dataset['extras'];

            $newTags           = [];
            $dataset['extras'] = [];

            foreach ($extras as $extra) {
                if ($category_tag == $extra['key']) {
                    if (!$tags_to_remove) {
//                        just remove the whole group
                    } else {
                        $tags_to_remove = explode(';', $tags_to_remove);
                        array_walk($tags_to_remove, create_function('&$val', '$val = trim($val);'));
                        $oldTags = trim($extra['value'], '"[], ');
                        $oldTags = explode('","', $oldTags);
                        $newTags = [];
                        if ($oldTags && is_array($oldTags)) {
                            foreach ($oldTags as $tag) {
                                if (!in_array(trim($tag), $tags_to_remove)) {
                                    $newTags[] = $tag;
                                }
                            }
                        }
                        $newTags = $this->cleanupTags($newTags);
                    }

                    $this->say(str_pad('-TAGS', 7, ' . ', STR_PAD_LEFT), '');
                    continue;
                }
                $dataset['extras'][] = $extra;
            }

            if ($newTags) {
                $formattedTags       = '["' . join('","', $newTags) . '"]';
                $dataset['extras'][] = [
                    'key'   => $category_tag,
                    'value' => $formattedTags,
                ];
            } else {
                $dataset['extras'][] = [
                    'key'   => $category_tag,
                    'value' => null,
                ];
            }

            $this->package_update($dataset);
            $this->say(str_pad('SUCCESS', 10, ' . ', STR_PAD_LEFT));
        }

        file_put_contents($this->resultsDir . '/' . $basename . '_remove.log', $this->logOutput,
            FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array $tagsArray
     *
     * @return array
     */
    private
    function cleanupTags(
        $tagsArray
    ) {
        $return    = [];
        $tagsArray = array_unique($tagsArray);
        foreach ($tagsArray as $tag) {
            $tag = str_replace(['\\t'], [''], $tag);
            $tag = trim($tag, " \t\n\r\0\x0B\"'");
            if (strlen($tag)) {
                $return[] = $tag;
            }
        }

        return $return;
    }

    /**
     * @param $datasetNames
     * @param $extraField
     * @param $oldValue
     * @param $newValue
     * @param $basename
     */
    public function update_extra_fields($datasetNames, $extraField, $oldValue, $newValue, $basename)
    {
        static $counter = 0;
        $this->logOutput = '';

        foreach ($datasetNames as $datasetName) {

            $datasetName = strtolower($datasetName);

            echo str_pad(++$counter, 6);
            $this->say($datasetName . ',', '');

            try {
                $dataset = $this->Ckan->package_show($datasetName);
            } catch (NotFoundHttpException $ex) {
                $this->say('NOT FOUND');
                continue;
            }

            $dataset = json_decode($dataset, true);
            if (!$dataset['success']) {
                $this->say('NOT FOUND');
                continue;
            }

            $dataset = $dataset['result'];

            if ('dataset' !== $dataset['type']) {
                $this->say('NOT A DATASET (type: ' . $dataset['type'] . ')');
                continue;
            }

            $extras            = $dataset['extras'];
            $dataset['extras'] = [];

            $updated = false;
            $value   = '';
            foreach ($extras as $extra) {
                if ($extraField == $extra['key'] && $oldValue == $extra['value']) {
                    $extra['value'] = $newValue;
                    $updated        = true;
                } elseif ($extraField == $extra['key']) {
                    $value = $extra['value'];
                }
                $dataset['extras'][] = $extra;
            }

            if (!$updated) {
                $this->say('NOT UPDATED ' . $value);
                continue;
            }

            try {
                $this->package_update($dataset);
                $this->say('SUCCESS');
            } catch (\Exception $ex) {
                $this->say('ERROR: CHECK LOG');
                file_put_contents($this->resultsDir . '/error.log', $ex->getMessage() . PHP_EOL,
                    FILE_APPEND | LOCK_EX);
            }
        }

        file_put_contents(
            $this->resultsDir . '/' . $basename . '_update_extra_fields.log.csv',
            $this->logOutput,
            FILE_APPEND | LOCK_EX
        );
        $this->logOutput = '';
    }

    /**
     * @param      $datasetNames
     * @param      $group
     * @param null $categories
     * @param      $basename
     *
     * @throws \Exception
     */
    public
    function assign_groups_and_categories_to_datasets(
        $datasetNames,
        $group,
        $categories = null,
        $basename
    ) {
        static $counter = 0;
        $this->logOutput = '';

        $group                    = $this->findGroup($group);
        $this->expectedFieldsDiff = [
            'groups'
        ];

        foreach ($datasetNames as $datasetName) {

            $datasetName = strtolower($datasetName);

            echo str_pad(++$counter, 6);
//            $this->say(str_pad($datasetName, 100, ' . '), '');
            $this->say($datasetName . ',', '');

            if (!$group) {
//                $this->say(str_pad('GROUP "' . $group . '" NOT FOUND', 15, ' . ', STR_PAD_LEFT));
                $this->say('GROUP "' . $group . '" NOT FOUND');
                continue;
            }

//            if (!is_array($categories) && !strlen($categories)) {
////                $this->say(str_pad('EMPTY TOPIC TAG', 15, ' . ', STR_PAD_LEFT));
//                $this->say('EMPTY TOPIC TAG');
//                continue;
//            }

            try {
                $dataset = $this->Ckan->package_show($datasetName);
            } catch (NotFoundHttpException $ex) {
//                $this->say(str_pad('NOT FOUND', 15, ' . ', STR_PAD_LEFT));
                $this->say('NOT FOUND');
                continue;
            }

            $dataset = json_decode($dataset, true);
            if (!$dataset['success']) {
//                $this->say(str_pad('NOT FOUND', 15, ' . ', STR_PAD_LEFT));
                $this->say('NOT FOUND');
                continue;
            }

            $dataset = $dataset['result'];

            if ('dataset' !== $dataset['type']) {
                $this->say('NOT A DATASET (type: ' . $dataset['type'] . ')');
                continue;
            }

            $dataset['groups'][] = [
                'name' => $group['name'],
            ];

            if (is_array($categories) || strlen($categories)) {
                $extras            = $dataset['extras'];
                $dataset['extras'] = [];

                foreach ($extras as $extra) {
                    if ('__category_tag_' . $group['id'] == $extra['key']) {
                        $oldCategories = trim($extra['value'], '"[], ');
                        $oldCategories = explode('","', $oldCategories);
                        $categories    = array_merge($categories, $oldCategories);
                        $categories    = $this->cleanupTags($categories);
                        continue;
                    }
                    $dataset['extras'][] = $extra;
                }

                if ($categories) {
                    $formattedCategories = '["' . join('","', $categories) . '"]';
                    $dataset['extras'][] = [
                        'key'   => '__category_tag_' . $group['id'],
                        'value' => $formattedCategories,
                    ];
                }
            }

            try {
                $this->package_update($dataset);
//                $this->say(str_pad('SUCCESS', 15, ' . ', STR_PAD_LEFT));
                $this->say('SUCCESS');
            } catch (\Exception $ex) {
//                $this->say(str_pad('ERROR: CHECK LOG', 15, ' . ', STR_PAD_LEFT));
                $this->say('ERROR: CHECK LOG');
                file_put_contents($this->resultsDir . '/error.log', $ex->getMessage() . PHP_EOL,
                    FILE_APPEND | LOCK_EX);
            }
        }
        file_put_contents(
            $this->resultsDir . '/' . $basename . '_tags.log.csv',
            $this->logOutput,
            FILE_APPEND | LOCK_EX
        );
        $this->logOutput = '';
    }

    /**
     * @param mixed $tree
     * @param string $results_dir
     * @param string|bool $start
     * @param int|bool $limit
     */
    public
    function get_redirect_list(
        $tree,
        $results_dir,
        $start = false,
        $limit = 1
    ) {
        $countOfRootOrganizations = sizeof($tree);
        $i                        = 0;
        $processed                = 0;
        foreach ($tree as $rootOrganization) {
            $i++;

            if (!$start || $start == $rootOrganization['id']) {
                $start = false;
                echo "::Processing Root Organization #$i of $countOfRootOrganizations::" . PHP_EOL;
                $this->get_redirect_list_by_organization($rootOrganization, $results_dir);
            }

            if (isset($rootOrganization['children'])) {
                foreach ($rootOrganization['children'] as $subAgency) {
                    if (!$start || $start == $subAgency['id']) {
                        $this->get_redirect_list_by_organization($subAgency, $results_dir);
                        if ($start && (1 == $limit)) {
                            return;
                        }
                        $start = false;
                    }
                }
            }

            if ($start) {
                continue;
            }

            $processed++;
            if ($limit && $limit == $processed) {
                echo "processed: $processed root organizations" . PHP_EOL;

                return;
            }
        }
    }

    /**
     * @param mixed $organization
     * @param string $results_dir
     *
     * @return bool
     */
    private
    function get_redirect_list_by_organization(
        $organization,
        $results_dir
    ) {
        $return = [];

        if (ERROR_REPORTING == E_ALL) {
            echo PHP_EOL . "Getting member list of: " . $organization['id'] . PHP_EOL;
        }

        $list = $this->try_member_list($organization['id']);

        if (!$list) {
            return;
        }

        $i    = 0;
        $size = sizeof($list);
        foreach ($list as $package) {
            if (!(++$i % 500)) {
                echo str_pad($i, 7, ' ', STR_PAD_LEFT) . ' / ' . $size . PHP_EOL;
            }
            $dataset = $this->try_package_show($package[0]);
            if (!$dataset) {
                continue;
            }

//            skip harvest sources etc
            if ('dataset' != $dataset['type']) {
                continue;
            }

//            we need only private datasets
            if (!$dataset['private']) {
                if (strpos(json_encode($dataset), 'metadata_from_legacy_dms')) {
                    $return[] = [
                        $package[0],
                        '',
                        'http://catalog.data.gov/dataset/' . $dataset['name'],
                        '',
                        ''
                    ];
                }
                continue;
            }

            $newDataset = $this->try_find_new_dataset_by_identifier($package[0]);
            if (!$newDataset) {
                $newDataset = $this->try_find_new_dataset_by_title(trim($dataset['title']));
            }
            if (!$newDataset) {
                continue;
            }

            if (strpos($dataset['name'], '_legacy')) {
                $legacy_url = '';
            } else {
                $legacy_url = $dataset['name'] . '_legacy';
            }

            $return[] = [
                $package[0],
                'http://catalog.data.gov/dataset/' . $package[0],
                'http://catalog.data.gov/dataset/' . $newDataset['name'],
                'http://catalog.data.gov/dataset/' . $dataset['name'],
                $legacy_url
            ];
        }

        if (sizeof($return)) {
            $fp_csv = fopen(($filename = $results_dir . '/' . $organization['id'] . '.csv'), 'w');

            if ($fp_csv == false) {
                die("Unable to create file: " . $filename);
            }

//            header
            fputcsv($fp_csv, ['id', 'socrata_url', 'public_url', 'private_url', 'legacy_url']);

            foreach ($return as $csv_line) {
                fputcsv($fp_csv, $csv_line);
            }

            fclose($fp_csv);
        }
    }

    /**
     * @param string $identifier
     * @param int $try
     *
     * @return bool|mixed
     */
    private
    function try_find_new_dataset_by_identifier(
        $identifier,
        $try = 3
    ) {
        $dataset = false;
        while ($try) {
            try {
                $dataset = $this->Ckan->package_search(
                    'identifier:' . $identifier,
                    1,
                    0,
                    'fq'
                );
                $dataset = json_decode($dataset, true); // as array

                if (!$dataset['success']) {
                    return false;
                }

                if (!isset($dataset['result']) || !sizeof($dataset['result'])) {

                    return false;
                }

                $dataset = $dataset['result'];

                if (!$dataset['count']) {
                    return false;
                }

                $dataset = $dataset['results'][0];

                $try = 0;
            } catch (NotFoundHttpException $ex) {

                return false;
            } catch (\Exception $ex) {
                $try--;
                if (!$try) {

                    return false;
                }
            }
        }

        return $dataset;
    }

    /**
     * @param string $title
     * @param int $try
     *
     * @return bool|mixed
     */
    private
    function try_find_new_dataset_by_title(
        $title,
        $try = 3
    ) {
        $dataset = false;
        $title   = $this->escapeSolrValue($title);
        while ($try) {
            try {
                $ckanResult = $this->Ckan->package_search(
                    'title:' . $title,
                    50,
                    0,
                    'fq'
                );
                $ckanResult = json_decode($ckanResult, true); // as array

                if (!$ckanResult['success']) {
                    return false;
                }

                if (!isset($ckanResult['result']) || !sizeof($ckanResult['result'])) {

                    return false;
                }

                $ckanResult = $ckanResult['result'];

                if (!$ckanResult['count']) {
                    return false;
                }

                foreach ($ckanResult['results'] as $dataset) {
                    if ($this->simplifyTitle($title) == $this->simplifyTitle($dataset['title'])) {
                        return $dataset;
                    }
                }

                return false;
            } catch (NotFoundHttpException $ex) {

                return false;
            } catch (\Exception $ex) {
                $try--;
                if (!$try) {

                    return false;
                }
            }
        }

        return $dataset;
    }

    /**
     * Sometimes harvested ckan title does not exactly matches, but dataset is same, ex. double spaces
     * To avoid these cases, we remove all non-word chars, leaving only alphabetic and digit chars
     * Ex.
     * Input: Tree dog dataset    , agriculture, 1997 ?????!!!
     * Output: treedogdatasetagriculture1997
     *
     * @param $string
     *
     * @return mixed|string
     */
    private
    function simplifyTitle(
        $string
    ) {
        $string = preg_replace('/[\W]+/', '', $string);
        $string = strtolower($string);

        return $string;
    }

    /**
     * @param mixed $tree
     * @param string $results_dir
     * @param string|bool $start
     * @param int|bool $limit
     */
    public
    function get_private_list(
        $tree,
        $results_dir,
        $start = false,
        $limit = 1
    ) {
        $this->return = [];

        $countOfRootOrganizations = sizeof($tree);
        $i                        = 0;
        $processed                = 0;
        foreach ($tree as $rootOrganization) {
            $i++;

            if (!$start || $start == $rootOrganization['id']) {
                $start = false;
                echo "::Processing Root Organization #$i of $countOfRootOrganizations::" . PHP_EOL;
                $this->get_private_list_by_organization($rootOrganization, $results_dir);
            }

            if (isset($rootOrganization['children'])) {
                foreach ($rootOrganization['children'] as $subAgency) {
                    if (!$start || $start == $subAgency['id']) {
                        $this->get_private_list_by_organization($subAgency, $results_dir);
                        if ($start && (1 == $limit)) {
                            return;
                        }
                        $start = false;
                    }
                }
            }

            if ($start) {
                continue;
            }

            $processed++;
            if ($limit && $limit == $processed) {
                echo "processed: $processed root organizations" . PHP_EOL;

                return;
            }
        }
    }

    /**
     * @param mixed $organization
     * @param string $results_dir
     *
     * @return bool
     */
    private
    function get_private_list_by_organization(
        $organization,
        $results_dir
    ) {
        if (ERROR_REPORTING == E_ALL) {
            echo PHP_EOL . "Getting member list of: " . $organization['id'] . PHP_EOL;
        }

        $list = $this->try_member_list($organization['id']);

        if (!$list) {
            return;
        }

        foreach ($list as $package) {
            $dataset = $this->try_package_show($package[0]);
            if (!$dataset) {
                continue;
            }

//            skip harvest sources etc
            if ('dataset' != $dataset['type']) {
                continue;
            }

//            we need only private datasets
            if (!$dataset['private']) {
                continue;
            }

            $this->return[] = $dataset;
        }

        if (sizeof($this->return)) {
            $json = (json_encode($this->return, JSON_PRETTY_PRINT));
            file_put_contents($results_dir . '/' . $organization['id'] . '_PRIVATE_ONLY.json', $json);
        }
    }

    /**
     * @param $socrata_list
     * @param $results_dir
     */
    public
    function get_socrata_pairs(
        $socrata_list,
        $results_dir
    ) {
        $socrata_redirects  = ['from,to'];
        $ckan_rename_legacy = ['from,to'];
        $ckan_rename_public = ['from,to'];
        $ckan_redirects     = ['from,to'];
        $socrata_txt_log    = ['socrata_id,ckan_id,status,private,public'];

        $notFound = $publicFound = $privateOnly = $alreadyLegacy = $mustRename = $socrataNotFound = 0;

        $ckan_url = 'https://catalog.data.gov/dataset/';

        $SocrataApi = new ExploreApi('http://explore.data.gov/api/');

        $size = sizeof($socrata_list);
        $i    = 0;
        foreach ($socrata_list as $socrata_line) {
            echo ++$i . " / $size $socrata_line" . PHP_EOL;
            if (!strlen($socrata_line = trim($socrata_line))) {
                continue;
            }
            list($socrata_id, $ckan_id) = explode(': ', $socrata_line);
            $socrata_id = trim($socrata_id);
            $ckan_id    = trim($ckan_id);

            $socrataDatasetTitle = $this->try_find_socrata_title($SocrataApi, $socrata_id);

            if (!$socrataDatasetTitle) {
                $socrataNotFound++;
                echo 'socrata not found' . PHP_EOL;
                $socrata_txt_log [] = join(
                    ',',
                    [$socrata_id, $ckan_id, 'socrata not found', '-', '-']
                );
                continue;
            }

            /**
             * Try to find dataset with same id
             */
            $dataset = $this->try_package_show($ckan_id);

            if (!$dataset) {
                /**
                 * Let's try to get original explore.data.gov dataset title
                 * and search public dataset with same title
                 */
                $public_dataset = $this->try_find_new_dataset_by_title($socrataDatasetTitle);

                if ($public_dataset) {
                    $publicFound++;
                    echo 'ckan public found by socrata title' . PHP_EOL;
                    $socrata_txt_log []   = join(
                        ',',
                        [
                            $socrata_id,
                            $ckan_id,
                            'ckan public found by socrata title',
                            '-',
                            $ckan_url . $public_dataset['name']
                        ]
                    );
                    $socrata_redirects [] = join(',', [$socrata_id, $ckan_url . $public_dataset['name']]);
//                    $ckan_redirects []    = join(',', [$ckan_url . $ckan_id, $ckan_url . $public_dataset['name']]);
                    continue;
                }

//                else
                $notFound++;
                echo 'ckan nothing found' . PHP_EOL;
                $socrata_txt_log [] = join(
                    ',',
                    [$socrata_id, $ckan_id, 'ckan nothing found', '-', '-']
                );
                continue;
            }

            /**
             * if PUBLIC
             */
            if (!$dataset['private']) {
                $publicFound++;
                echo 'ckan public found by id' . PHP_EOL;
                $socrata_txt_log []   = join(
                    ',',
                    [$socrata_id, $ckan_id, 'ckan public found by id', '-', $ckan_url . $dataset['name']]
                );
                $socrata_redirects [] = join(',', [$socrata_id, $ckan_url . $dataset['name']]);
//                $ckan_redirects []    = join(',', [$ckan_url . $ckan_id, $ckan_url . $dataset['name']]);
                continue;
            }

            /**
             * Dataset is private, let's try to find his public brother
             */
            $publicDataset = $this->try_find_new_dataset_by_title($dataset['title']);

            if (!$publicDataset) {
                echo $dataset['title'] . ' :: not found' . PHP_EOL;
                /**
                 * Let's try to get original explore.data.gov dataset title
                 * and search public dataset with same title
                 */

                $public_dataset = $this->try_find_new_dataset_by_title($socrataDatasetTitle);

                if ($public_dataset) {
                    $publicFound++;
                    echo 'ckan public found by socrata title' . PHP_EOL;
                    $socrata_txt_log []   = join(
                        ',',
                        [
                            $socrata_id,
                            $ckan_id,
                            'ckan public found by socrata title',
                            '-',
                            $ckan_url . $public_dataset['name']
                        ]
                    );
                    $socrata_redirects [] = join(',', [$socrata_id, $ckan_url . $public_dataset['name']]);
//                    $ckan_redirects []    = join(',', [$ckan_url . $ckan_id, $ckan_url . $public_dataset['name']]);
                    continue;
                }

//                else
                $privateOnly++;
                echo 'ckan private only' . PHP_EOL;
                $socrata_txt_log [] = join(
                    ',',
                    [
                        $socrata_id,
                        $ckan_id,
                        'ckan private only',
                        $ckan_url . $dataset['name'],
                        '-'
                    ]
                );
                continue;
            }

            /**
             * Public dataset found, but private dataset already has _legacy postfix
             */
            if (strpos($dataset['name'], '_legacy')) {
                $alreadyLegacy++;
                echo 'ckan private already _legacy; public brother ok; no renaming' . PHP_EOL;
                $socrata_txt_log []   = join(
                    ',',
                    [
                        $socrata_id,
                        $ckan_id,
                        'ckan private already _legacy; public brother ok; no renaming',
                        $ckan_url . $dataset['name'],
                        $ckan_url . $publicDataset['name']
                    ]
                );
                $socrata_redirects [] = join(',', [$socrata_id, $ckan_url . $publicDataset['name']]);
//                $ckan_redirects []    = join(',', [$ckan_url . $ckan_id, $ckan_url . $publicDataset['name']]);
                continue;
            }

            /**
             * Public dataset found, let's rename
             */
            $mustRename++;
            echo 'ckan private and public found; need to rename' . PHP_EOL;
            $socrata_txt_log []   = join(
                ',',
                [
                    $socrata_id,
                    $ckan_id,
                    'ckan private and public found; need to rename',
                    $ckan_url . $dataset['name'],
                    $ckan_url . $publicDataset['name']
                ]
            );
            $socrata_redirects [] = join(',', [$socrata_id, $ckan_url . $dataset['name']]);
//            $ckan_redirects []    = join(',', [$ckan_url . $ckan_id, $ckan_url . $dataset['name']]);
            $ckan_redirects []    = join(
                ',',
                [$ckan_url . $publicDataset['name'], $ckan_url . $dataset['name']]
            );
            $ckan_rename_legacy[] = join(
                ',',
                [$ckan_url . $dataset['name'], $ckan_url . $dataset['name'] . '_legacy']
            );
            $ckan_rename_public[] = join(
                ',',
                [$ckan_url . $publicDataset['name'], $ckan_url . $dataset['name']]
            );
            continue;
        }

        $socrata_txt_log = join("\n", $socrata_txt_log);
        file_put_contents($results_dir . '/socrata_txt_log.csv', $socrata_txt_log);

        $ckan_rename_legacy = join("\n", $ckan_rename_legacy);
        file_put_contents($results_dir . '/rename_private_datasets_legacy.csv', $ckan_rename_legacy);

        $ckan_rename_public = join("\n", $ckan_rename_public);
        file_put_contents($results_dir . '/rename_public_datasets.csv', $ckan_rename_public);

        $ckan_redirects = join("\n", $ckan_redirects);
        file_put_contents($results_dir . '/redirects_ckan.csv', $ckan_redirects);

        $socrata_redirects = join("\n", $socrata_redirects);
        file_put_contents($results_dir . '/redirects_socrata.csv', $socrata_redirects);

        echo <<<EOR
Total socrata datasets in list:       $size

Not found in Socrata:                 $socrataNotFound
Found in Socrata, Not found in CKAN:  $notFound
Found public on ckan:                 $publicFound
Found only private dataset:           $privateOnly
Private already _legacy:              $alreadyLegacy
Renaming needed for datasets:         $mustRename
EOR;

    }

    /**
     * @param ExploreApi $SocrataApi
     * @param            $socrata_id
     * @param int $try
     *
     * @return bool
     */
    private
    function try_find_socrata_title(
        ExploreApi $SocrataApi,
        $socrata_id,
        $try = 3
    ) {
        $title = false;
        while ($try) {
            try {
                $dataset = $SocrataApi->get_json($socrata_id);
                $dataset = json_decode($dataset, true); // as array

//                if (!isset($dataset['viewType']) || !isset($dataset['name'])) {
                if (!isset($dataset['name'])) {
                    return false;
                }
//
//                if ('href' !== $dataset['viewType']) {
//                    return false;
//                }

                $title = $dataset['name'];
                $try   = 0;
            } catch (\Exception $ex) {
                $try--;
                sleep(3);
                echo '      zzz   ' . $socrata_id . PHP_EOL;
                if (!$try) {
                    echo 'Too many attempts: ' . $socrata_id . PHP_EOL;

                    return false;
                }
            }
        }

        return $title;
    }

    /**
     * @param Writer $csv_agencies
     * @param Writer $csv_categories
     */
    public
    function breakdown_by_group(
        Writer $csv_agencies,
        Writer $csv_categories
    ) {
        $offset   = 0;
        $per_page = 50;
        $counter  = 0;

        $organizations = [];
        $tags          = [];

        $search_query = '(' . GROUP_TO_EXPORT . ') AND (dataset_type:dataset)';
//        $group = $this->findGroup(GROUP_TO_EXPORT);

        while (true) {
            $datasets = $this->try_package_search($search_query, $per_page, $offset);

//            Finish
            if (!$datasets) {
                echo PHP_EOL . "no datasets: offset $offset limit: $per_page" . PHP_EOL;
                break;
            } // if

            foreach ($datasets as $dataset) {
                $counter++;

                echo $dataset['organization']['title'] . PHP_EOL;
                if (isset($organizations[$dataset['organization']['title']])) {
                    $organizations[$dataset['organization']['title']]++;
                } else {
                    $organizations[$dataset['organization']['title']] = 1;
                }

                if (!isset($dataset['extras'])) {
                    continue;
                }

                foreach ($dataset['extras'] as $extra) {
                    if (!strpos($extra['key'], 'category_tag') || !$extra['value']) {
                        continue;
                    }
                    $extra_tags = trim($extra['value'], '"[], ');
                    $extra_tags = explode('","', $extra_tags);

                    if (sizeof($extra_tags)) {
                        foreach ($extra_tags as $tag) {
                            if (isset($tags[$tag])) {
                                $tags[$tag]++;
                            } else {
                                $tags[$tag] = 1;
                            }
                        }
                    }
                }
            } // foreach

            $offset += $per_page;

        } // while

        $csv_agencies->writeRow(['Agency', '# Datasets']);
        foreach ($organizations as $agency => $datasets_number) {
            $csv_agencies->writeRow([$agency, $datasets_number]);
        }

        $csv_categories->writeRow(['Categories', '# Datasets']);
        foreach ($tags as $category => $datasets_number) {
            $csv_categories->writeRow([$category, $datasets_number]);
        }

    }

    /**
     * @param $results_dir
     * @param $limit
     * @param $start
     */
    public
    function orphaned_tags_seek(
        $results_dir,
        $limit,
        $start
    ) {
        $counter  = 0;
        $offset   = $start;
        $finish   = $start + $limit;
        $per_page = 50;

        $groups = $this->try_get_groups_array();

        while (true) {
            $datasets = $this->try_package_search('(dataset_type:dataset)', $per_page, $offset);

//            Finish
            if (!$datasets) {
                echo PHP_EOL . "no datasets: offset $offset limit: $per_page" . PHP_EOL;
                break;
            }

            foreach ($datasets as $dataset) {
                $counter++;
                if (!isset($dataset['extras'])) {
                    continue;
                }

                $dataset_groups = null;

                foreach ($dataset['extras'] as $extra) {
                    if (!strpos($extra['key'], 'category_tag') || !$extra['value']) {
                        continue;
                    }
                    $group_id = str_replace('__category_tag_', '', $extra['key']);
                    $group    = isset($groups[$group_id]) ? $groups[$group_id] : $group_id;

                    $group_found = false;
                    if (isset($dataset['groups'])) {
                        foreach ($dataset['groups'] as $dataset_group) {
                            if ($dataset_group['id'] == $group_id) {
                                $group_found = true;
                                break;
                            }
                        }
                    }

                    if (!$group_found) {
                        echo $counter . ' ' . ($out = $dataset['name'] . ',' . $group . ',' . $extra['value'] . PHP_EOL);
                        file_put_contents($results_dir . '/orphaned_tags.csv', $out, FILE_APPEND);
                    }
                }
            }

//            Finish
//            if (($sz = sizeof($datasets)) < $per_page) {
//                echo PHP_EOL."$sz datasets: offset $offset limit: $per_page".PHP_EOL;
//                break;
//            }

            $offset += $per_page;

//            iteration done
            if ($offset >= $finish) {
                echo PHP_EOL . "done: offset $offset limit: $per_page" . PHP_EOL;
                break;
            }
        }
    }

    /**
     * @return array
     */
    private
    function try_get_groups_array()
    {
        $groups = json_decode($this->Ckan->group_list(true), true);
        if (!$groups || !$groups['success']) {
            die('cant get groups array');
        }
        $return = [];
        foreach ($groups['result'] as $group) {
            $return[$group['id']] = $group['name'];
        }

        return $return;
    }

    /**
     * @return array
     */
    public
    function groups_array()
    {
        return $this->try_get_groups_array();
    }

    /**
     * @param             $category
     * @param CkanManager $CkanManagerProduction
     * @param             $results_dir
     */
    public
    function check_group_against_prod(
        $category,
        self $CkanManagerProduction,
        $results_dir
    ) {
        $csv = new Writer($results_dir . '/' . $category . date('_Ymd-His') . '.csv');
        $csv->writeRow(
            [
                'Staging dataset name',
                'Staging Source',
                'Prod exists',
                'Prod has ' . $category,
                'Prod Source'
            ]
        );

        $ckan_query = '((groups:' . $category . ') + dataset_type:dataset)';
        $start      = 0;
        $per_page   = 20;
        while (true) {
            $packages = $this->try_package_search($ckan_query, $per_page, $start);
            if (!$packages) {
                echo "$start / $per_page :: finish" . PHP_EOL;
                break;
            }

            foreach ($packages as $package) {
                if (is_array($package['extras'])
                    && sizeof($package['extras'])
                    && (strpos(json_encode($package['extras']), '"dms"')
                    )
                ) {
                    $resource_type = 'DMS';
//                    echo "DMS ".$package['name'].PHP_EOL;
                } elseif (is_array($package['extras']) && sizeof($package['extras']) && strpos(
                        json_encode($package['extras']),
                        '"value":"geospatial"'
                    )
                ) {
                    $resource_type = 'GEO';
//                    echo "GEO ".$package['name'].PHP_EOL;
                } elseif (is_array($package['extras']) && sizeof($package['extras']) && strpos(
                        json_encode($package['extras']),
                        'source_datajson_identifier'
                    )
                ) {
                    $resource_type = 'JSON';
//                    echo "JSON ".$package['name'].PHP_EOL;
                } else {
                    $resource_type = 'OTHER';
                    echo json_encode($package['extras']) . PHP_EOL;
                    echo "UNKNOWN: " . $package['name'] . PHP_EOL;
                }

                $prod_package        = $CkanManagerProduction->try_package_show($package['name']);
                $exists              = $prod_package ? 'EXISTS' : 'NOT FOUND';
                $prod_category_found = '';
                $prod_resource_type  = '';

                if ($prod_package) {
                    $prod_category_found = 'FALSE';

                    if (isset($prod_package['groups']) && sizeof($prod_package['groups']) && strpos(
                            json_encode($prod_package['groups']),
                            $category
                        )
                    ) {
                        $prod_category_found = 'HAS';
                    }

                    if (is_array($prod_package['extras']) && sizeof($prod_package['extras']) && (strpos(
                            json_encode($prod_package['extras']),
                            '"dms"'
                        ))
                    ) {
                        $prod_resource_type = 'DMS';
//                    echo "DMS ".$prod_package['name'].PHP_EOL;
                    } elseif (is_array($prod_package['extras']) && sizeof($prod_package['extras']) && strpos(
                            json_encode($prod_package['extras']),
                            '"value":"geospatial"'
                        )
                    ) {
                        $prod_resource_type = 'GEO';
//                    echo "GEO ".$prod_package['name'].PHP_EOL;
                    } elseif (is_array($prod_package['extras']) && sizeof($prod_package['extras']) && strpos(
                            json_encode($prod_package['extras']),
                            'source_datajson_identifier'
                        )
                    ) {
                        $prod_resource_type = 'JSON';
//                    echo "JSON ".$prod_package['name'].PHP_EOL;
                    } else {
                        $prod_resource_type = 'OTHER';
                        echo json_encode($prod_package['extras']) . PHP_EOL;
                        echo "UNKNOWN on PROD: " . $prod_package['name'] . PHP_EOL;
                    }
                }

                $csv->writeRow(
                    [
                        $package['name'],
                        $resource_type,
                        $exists,
                        $prod_category_found,
                        $prod_resource_type
                    ]
                );
            }

            $start += $per_page;
        }
    }

    /**
     * @param $json
     */
    public function import_json($json)
    {

    }
}
