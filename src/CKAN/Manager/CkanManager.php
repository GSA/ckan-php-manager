<?php

namespace CKAN\Manager;

use CKAN\CkanClient;
use CKAN\NotFoundHttpException;
use CKAN\OrganizationList;
use Colors\Color;
use EasyCSV\Writer;

class CkanManager
{
    /**
     *
     */
    const EXPORT_DMS_ONLY = 0b1;
    /**
     *
     */
    const EXPORT_PUBLIC_ONLY = 0b10;
    /**
     *
     */
    const EXPORT_PRIVATE_ONLY = 0b100;
    /**
     *
     */
    const IDS_ONLY = 0b1000;
    /**
     * @var string
     */
    public $logOutput = '';
    /**
     * @var string
     */
    public $resultsDir;
    /**
     * @var Color
     */
    public $color;
    /**
     * @var \CKAN\CkanClient
     */
    private $Ckan;
    /**
     * @var bool
     */
    private $return = false;
    /**
     * CkanManager results per page
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
     * @var int
     */
    private $resultCount = 0;

    /**
     * @param string $apiUrl
     * @param null $apiKey
     */
    public function __construct($apiUrl, $apiKey = null)
    {
        $this->color = new Color();
        $this->apiUrl = $apiUrl;
        // skip while unit tests
        if (defined('RESULTS_DIR')) {
            $this->resultsDir = CKANMNGR_RESULTS_DIR;
            echo $this->color->magenta("API URL:") . ' ' . $this->color->bold($apiUrl) . PHP_EOL . PHP_EOL;
//            letting user cancel (Ctrl+C) script if noticed wrong api url
            sleep(3);
        }
        $this->setCkan(new CkanClient($apiUrl, $apiKey));
    }

    /**
     * @param CkanClient $Ckan
     */
    public function setCkan($Ckan)
    {
        $this->Ckan = $Ckan;
    }

    /**
     *
     */
    public function test_dev()
    {
        $datasets = $this->tryPackageSearch('u', '');

        $counter = 0;
        foreach ($datasets as $dataset) {
            $dataset = $this->tryPackageShow($dataset['name']);

            echo ++$counter . ' ' . $dataset['name'] . PHP_EOL;

            $this->tryPackageUpdate($dataset);

            if (defined('QUIT')) {
                return;
            }
        }
    }

    /**
     * @param        $search_q
     * @param        $search_fq
     * @param int $rows
     * @param int $start
     * @param int $try
     *
     * @return bool|mixed
     */
    public function tryPackageSearch(
        $search_q = '',
        $search_fq = '',
        $rows = 100,
        $start = 0,
        $try = 3
    ) {
        $datasets = false;
        while ($try) {
            try {
                $datasets = $this->Ckan->package_search($search_q, $search_fq, $rows, $start);
                $datasets = json_decode($datasets, true);   /* as array */

                if (!$datasets['success'] || !isset($datasets['result'])) {
                    throw new \Exception('Could not search datasets');
                }

                $datasets = $datasets['result'];

                $this->resultCount = $datasets['count'];

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
                echo "Nothing found" . PHP_EOL;

                return false;
            } catch (\Exception $ex) {
                echo $ex->getMessage() . PHP_EOL;
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
     * @param string $package_id
     * @param int $try
     *
     * @return bool|mixed
     */
    public function tryPackageShow(
        $package_id,
        $try = 3
    ) {
        $dataset = false;
        while ($try) {
            try {
                $dataset = $this->Ckan->package_show($package_id);
                $dataset = json_decode($dataset, true); /* as array */

                if (!$dataset['success']) {
                    echo 'No success: ' . $package_id . PHP_EOL;
                    echo ' :( ';

                    return false;
                }
                if (!isset($dataset['result']) || !sizeof($dataset['result'])) {
                    echo 'No result: ' . $package_id . PHP_EOL;
                    echo ' :( ';

                    return false;
                }
                $dataset = $dataset['result'];
                $try = 0;
            } catch (NotFoundHttpException $ex) {
                return false;
            } catch (\Exception $ex) {
                $try--;
                sleep(3);
                echo '      zzz   ' . $package_id . PHP_EOL;
                if (!$try) {
                    echo 'Too many attempts: ' . $package_id . PHP_EOL;

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
    public function tryPackageUpdate($dataset)
    {
        if ('dataset' !== $dataset['type']) {
            return false;
        }

        try {
            $this->Ckan->package_update($dataset);
            if (!$this->checkDatasetConsistency($dataset)) {
                throw new \Exception('dataset consistency check failed');
            }

            return true;
        } catch (\Exception $ex) {
//            echo $ex->getMessage() . PHP_EOL;
            return false;
        }
    }

    /**
     * @param $dataset
     *
     * @return bool
     * @throws \Exception
     */
    public function checkDatasetConsistency($dataset)
    {
        $package_id = $dataset['name'];
        $updated_dataset = $this->tryPackageShow($package_id);

        try {
            if (sizeof($dataset['resources']) != sizeof($updated_dataset['resources'])) {
                throw new \Exception('Number of resources does not match after update (check dumps): ' . $package_id);
            }


            if (isset($dataset['extras'])) {
                $this->preDiffSort($dataset);
                $this->preDiffSort($updated_dataset);
            }

            $diff = $this->arrayDiffAssocRecursive($dataset, $updated_dataset);

            if (sizeof($diff)) {
                file_put_contents(
                    $this->resultsDir . '/dump-diff__' . $package_id . '.json', json_encode($diff, JSON_PRETTY_PRINT)
                );
                throw new \Exception('Consistency check failed (check diff): ' . $package_id);
            }

        } catch (\Exception $ex) {
            file_put_contents(
                $this->resultsDir . '/dump-before__' . $package_id . '.json', json_encode($dataset, JSON_PRETTY_PRINT)
            );
            file_put_contents(
                $this->resultsDir . '/dump-after__' . $package_id . '.json',
                json_encode($updated_dataset, JSON_PRETTY_PRINT)
            );
            throw $ex;
        }

        return true;
    }

    /**
     * @param array $dataset
     */
    private function preDiffSort(array &$dataset)
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
    private function  arrayDiffAssocRecursive($array1, $array2)
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
                    $new_diff = $this->arrayDiffAssocRecursive($value, $array2[$key]);
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
     * @param $datasetName
     *
     * @throws \Exception
     */
    public function fixModified($datasetName)
    {
        $dataset = $this->tryPackageShow($datasetName);
        if (!$dataset) {
            $this->say([$datasetName, 'ERROR', 'not found']);

            return;
        }

        if (isset($dataset['metadata_modified'])) {
            $modified = $dataset['metadata_modified'];
        } else {
            $this->say([$datasetName, 'ERROR', 'metadata_modified not found']);

            return;
        }

        if (strstr(json_encode($dataset['extras']), 'modified')) {
            foreach ($dataset['extras'] as $extra) {
                if ('modified' == $extra['key']) {
                    $modified = $extra['value'];
                }
            }
            $this->say([$datasetName, 'SUCCESS', 'metadata is ' . $modified]);

            return;
        }

        $dataset['extras'][] = [
            'key'   => 'modified',
            'value' => $modified,
        ];

        $result = $this->tryPackageUpdate($dataset);
        if ($result) {
            $this->say([$datasetName, 'SUCCESS', 'extras metadata is ' . $modified]);
        } else {
            $this->say([$datasetName, 'ERROR', 'could not update dataset']);
        }

        return;
    }

    /**
     * Shorthand for sending output to stdout and appending to log buffer at the same time.
     *
     * @param string $output
     * @param string $eol
     */
    public function say(
        $output = '',
        $eol = PHP_EOL
    ) {
        if (is_array($output)) {
            $output = join(',', $output);
        }

        $this->logOutput .= $output . $eol;

        switch ($output) {
            case 'SUCCESS':
                $output = $this->color->green($output);
                $output = $this->color->bold($output);
                break;
            case 'NOT FOUND':
            case 'INVALID URL':
                $output = $this->color->red($output);
                $output = $this->color->bold($output);
                break;
            default:
                break;
        }
        echo $output . $eol;
    }

    /**
     *
     */
    public function findMatchesSeparateFiles()
    {
        $page = 0;
        $datasets_by_harvest1 = [];
        $main_harvest_title = 'Environmental Dataset Gateway';
        $datasets_by_harvest = [];
        $titles = [];

        while (true) {
            $start = $page++ * $this->packageSearchPerPage;
            $ckanResults = $this->tryPackageSearch(
                'organization:epa-gov', '', $this->packageSearchPerPage, $start);

            if (!is_array($ckanResults)) {
                throw new \Exception('No results from CKAN - FATAL');
            }

//                csv for title, url, topic, and topic category
            foreach ($ckanResults as $dataset) {
                if ($dataset['type'] !== 'dataset' || !isset($dataset['extras'])) {
                    continue;
                }

                $title = $this->simplifyTitle($dataset['title']);
                $titles[$title] = $dataset['title'];

                $harvestSource = $this->extra($dataset['extras'], 'harvest_source_title');

                if ($harvestSource == $main_harvest_title) {
                    $groups = [];
                    if (sizeof($dataset['groups'])) {
                        foreach ($dataset['groups'] as $group) {
                            $tags = $this->extra($dataset['extras'], '__category_tag_' . $group['id']);
                            if ($tags) {
                                $groups[] = $group['title'] . '{' . $tags . '}';
                            } else {
                                $groups[] = $group['title'];
                            }
                        }
                    }
                    $datasets_by_harvest1[] = [
                        'title'    => $title,
                        'basename' => $dataset['name'],
                        'groups'   => join(';', $groups),
                    ];
                    continue;
                }

                if (!isset($datasets_by_harvest[$harvestSource])) {
                    $datasets_by_harvest[$harvestSource] = [];
                }

                if (!isset($datasets_by_harvest[$harvestSource][$title])) {
                    $datasets_by_harvest[$harvestSource][$title] = [];
                }
                $datasets_by_harvest[$harvestSource][$title][] = $dataset['name'];

            }

            $count = $this->resultCount;
            if ($start) {
                echo "start from $start / " . $count . ' total ' . PHP_EOL;
            }

            if ($this->resultCount - $this->packageSearchPerPage < $start) {
                break;
            }
        }

        $other_harvests = array_keys($datasets_by_harvest);

        foreach ($other_harvests as $harvest) {
            $csv = new Writer($this->resultsDir . '/matches_' . $harvest . '.csv');
            $csv->writeRow(['groups', 'title', $main_harvest_title, $harvest]);
            foreach ($datasets_by_harvest1 as $dataset) {
                $matches = [];
                if (isset($datasets_by_harvest[$harvest][$dataset['title']])) {
                    $matches = $datasets_by_harvest[$harvest][$dataset['title']];
                }
                $csv->writeRow(array_merge([
                    $dataset['groups'],
                    $titles[$dataset['title']],
                    $dataset['basename']
                ],
                    $matches));
            }
        }
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
    private function simplifyTitle(
        $string
    ) {
        $string = preg_replace('/[\W]+/', '', $string);
        $string = strtolower($string);

        return $string;
    }

    /**
     * @param $extras
     * @param $extra_key
     * @return bool
     */
    private function extra($extras, $extra_key)
    {
        foreach ($extras as $key => $extra) {
            if ($key === $extra_key) {
                return $extra;
            }
            if (isset($extra['key']) && $extra['key'] == $extra_key) {
                return $extra['value'];
            }
        }

        return false;
    }


    /**
     * @throws \Exception
     */
    public function findMatchesOneFile()
    {
        $page = 0;
        $main_datasets_by_harvest = [];
        $main_harvest_title = 'Environmental Dataset Gateway';
        $datasets_by_harvest = [];
        $titles = [];

        $csv = new Writer($this->resultsDir . '/matches.csv');

        while (true) {
            $start = $page++ * $this->packageSearchPerPage;
            $ckanResults = $this->tryPackageSearch(
                'organization:epa-gov', '', $this->packageSearchPerPage, $start);

            if (!is_array($ckanResults)) {
                throw new \Exception('No results from CKAN. Exiting...');
            }

            /* csv for title, url, topic, and topic category */
            foreach ($ckanResults as $dataset) {
                if ($dataset['type'] !== 'dataset' || !isset($dataset['extras'])) {
                    continue;
                }

                $title = $this->simplifyTitle($dataset['title']);
                $titles[$title] = $dataset['title'];

                $harvestSource = $this->extra($dataset['extras'], 'harvest_source_title');

                if ($harvestSource == $main_harvest_title) {
                    $groups = [];
                    if (sizeof($dataset['groups'])) {
                        foreach ($dataset['groups'] as $group) {
                            $tags = $this->extra($dataset['extras'], '__category_tag_' . $group['id']);
                            if ($tags) {
                                $groups[] = $group['title'] . '{' . $tags . '}';
                            } else {
                                $groups[] = $group['title'];
                            }
                        }
                    }
                    $main_datasets_by_harvest[] = [
                        'title'    => $title,
                        'basename' => $dataset['name'],
                        'groups'   => join(';', $groups),
                    ];
                    continue;
                }

                if (!isset($datasets_by_harvest[$harvestSource])) {
                    $datasets_by_harvest[$harvestSource] = [];
                }

                if (isset($datasets_by_harvest[$harvestSource][$title])) {
                    if (strlen($datasets_by_harvest[$harvestSource][$title]) > strlen($dataset['name'])) {
                        $datasets_by_harvest[$harvestSource][$title] = $dataset['name'];
                    }
                } else {
                    $datasets_by_harvest[$harvestSource][$title] = $dataset['name'];
                }
            }

            $count = $this->resultCount;
            if ($start) {
                echo "start from $start / " . $count . ' total ' . PHP_EOL;
            }

            if ($this->resultCount - $this->packageSearchPerPage < $start) {
                break;
            }
        }


        $other_harvests = array_keys($datasets_by_harvest);
        $csv->writeRow(array_merge(['groups', 'title', $main_harvest_title], array_keys($datasets_by_harvest)));

        foreach ($main_datasets_by_harvest as $dataset) {
            $matches = [];
            foreach ($other_harvests as $harvest) {
                if (isset($datasets_by_harvest[$harvest][$dataset['title']])) {
                    $matches[] = $datasets_by_harvest[$harvest][$dataset['title']];
                } else {
                    $matches[] = '';
                }
            }
            $csv->writeRow(array_merge([$dataset['groups'], $titles[$dataset['title']], $dataset['basename']],
                $matches));
        }
    }

    /**
     * @param int $limit
     * @throws \Exception
     */
    public function exportResourceList($limit = 500)
    {
        $page = 0;
        $list = [];
        while (true) {
            $start = $page++ * $this->packageSearchPerPage;
            echo $start . PHP_EOL;
            $ckanResults = $this->tryPackageSearch(
                'dataset_type:dataset', '', $this->packageSearchPerPage, $start);

            if (!is_array($ckanResults)) {
                throw new \Exception('No results from CKAN. Exiting...');
            }

            foreach ($ckanResults as $dataset) {
                if (!isset($dataset['resources'])) {
                    continue;
                }
                foreach ($dataset['resources'] as $resource) {
                    if (!isset($resource['url'])) {
                        continue;
                    }
                    $list[] = trim($resource['url']);
                }
            }
            if ($start > $limit) {
                break;
            }
        }
        $list = array_unique($list);
        $list_csv = new Writer($this->resultsDir . '/resources.csv');
        foreach ($list as $url) {
            $list_csv->writeRow([$url]);
        }

        return;
    }

    /**
     * @throws \Exception
     */
    public function findMatches()
    {
        $page = 0;
        $main_datasets_by_harvest = [];
        $main_harvest_title = 'Environmental Dataset Gateway';
        $datasets_by_harvest = [];
        $titles = [];

        $newJson = new Writer($this->resultsDir . '/assign_new_json.csv');
        $csv = new Writer($this->resultsDir . '/matches.csv');
        $rename_waf = new Writer($this->resultsDir . '/rename_waf.csv');
        $rename_fgdc = new Writer($this->resultsDir . '/rename_fgdc.csv');
        $rename_deleted = new Writer($this->resultsDir . '/rename_deleted.csv');

        while (true) {
            $start = $page++ * $this->packageSearchPerPage;
            $ckanResults = $this->tryPackageSearch(
                'dataset_type:dataset AND organization:epa-gov', '', $this->packageSearchPerPage, $start);

            if (!is_array($ckanResults)) {
                throw new \Exception('No results from CKAN. Exiting...');
            }

            /* csv for title, url, topic, and topic category */
            foreach ($ckanResults as $dataset) {
                if ($dataset['type'] !== 'dataset' || !isset($dataset['extras'])) {
                    continue;
                }

                $title = $this->simplifyTitle($dataset['title']);
                $titles[$title] = $dataset['title'];

                $harvestSource = $this->extra($dataset['extras'], 'harvest_source_title');

                if ($harvestSource == $main_harvest_title) {
                    $groups = [];
                    $group_tags = [];
                    if (sizeof($dataset['groups'])) {
                        foreach ($dataset['groups'] as $group) {
                            $tags = $this->extra($dataset['extras'], '__category_tag_' . $group['id']);
                            if ($tags) {
                                $groups[] = $group['title'] . '{' . $tags . '}';

                                $tags = trim($tags, '[]');
                                $tags = explode('","', $tags);
                                foreach ($tags as &$tag) {
                                    $tag = trim($tag, '" ');
                                }

                                $group_tags[$group['title']] = $tags;
                            } else {
                                $group_tags[$group['title']] = '';
                                $groups[] = $group['title'];
                            }
                        }
                    }
                    $main_datasets_by_harvest[] = [
                        'title'    => $title,
                        'basename' => $dataset['name'],
                        'groups'   => join(';', $groups),
                        'tags'     => $group_tags,
                    ];
                    continue;
                }

                if (!isset($datasets_by_harvest[$harvestSource])) {
                    $datasets_by_harvest[$harvestSource] = [];
                }

                if (isset($datasets_by_harvest[$harvestSource][$title])) {
                    $datasets_by_harvest[$harvestSource][$title][] = $dataset['name'];
                } else {
                    $datasets_by_harvest[$harvestSource][$title] = [$dataset['name']];
                }
            }

            $count = $this->resultCount;
            if ($start) {
                echo "start from $start / " . $count . ' total ' . PHP_EOL;
            }

            if ($this->resultCount - $this->packageSearchPerPage < $start) {
                break;
            }
        }

        $other_harvests = array_keys($datasets_by_harvest);
        $csv->writeRow(array_merge(['groups', 'title', $main_harvest_title], array_keys($datasets_by_harvest)));

        foreach ($main_datasets_by_harvest as $dataset) {
            $matches = [];
            foreach ($other_harvests as $harvest) {
                if (isset($datasets_by_harvest[$harvest][$dataset['title']])) {
                    $match = array_shift($datasets_by_harvest[$harvest][$dataset['title']]);
                    $matches[] = $match;

                    if ($match && stripos($harvest, 'waf')) {
                        $rename_deleted->writeRow([$dataset['basename'], $dataset['basename'] . '_epa_deleted']);
                        $rename_waf->writeRow([$match, $dataset['basename']]);
                    } elseif ($match && stripos($harvest, 'fgdc')) {
                        $rename_deleted->writeRow([$dataset['basename'], $dataset['basename'] . '_epa_deleted']);
                        $rename_fgdc->writeRow([$match, $dataset['basename']]);
                    } elseif ($match && (false !== stripos($harvest, 'new epa data'))) {
                        if (is_array($dataset['tags'])) {
                            foreach ($dataset['tags'] as $group => $tags) {
                                $newJson->writeRow([$match, $group, is_array($tags) ? join(';', $tags) : '']);
                            }
                        }
                    }
                } else {
                    $matches[] = '';
                }
            }
            $csv->writeRow(array_merge([$dataset['groups'], $titles[$dataset['title']], $dataset['basename']],
                $matches));
        }
    }

    /**
     * @param string $agency
     * @throws \Exception
     */
    public function findMatchesByAgency($agency = 'nrc')
    {
        $page = 0;
        $main_datasets_by_harvest = [];
        $main_harvest_title = false;
        $datasets_by_harvest = [];
        $titles = [];

        $csv = new Writer($this->resultsDir . '/matches.csv');
        $rename_json = new Writer($this->resultsDir . '/rename_json.csv');
        $rename_deleted = new Writer($this->resultsDir . '/rename_deleted.csv');

        $search = $agency . '-gov';
        if ('doc' == $agency) {
            $search = join(' OR ', [
                'doc-gov',
                'mbda-doc-gov',
                'trade-gov',
                'ntia-doc-gov',
                'ntis-gov',
                'nws-doc-gov',
                'census-gov',
                'eda-doc-gov',
                'uspto-gov',
                'bea-gov',
                'doc-gov',
                'bis-doc-gov',
            ]);
        }

//        echo 'dataset_type:dataset AND organization:('.$search.')'.PHP_EOL;die();

        while (true) {
            $start = $page++ * $this->packageSearchPerPage;
            $ckanResults = $this->tryPackageSearch(
                'organization:(' . $search . ') AND dataset_type:dataset AND -metadata_type:geospatial',
                '', $this->packageSearchPerPage, $start);

            if (!is_array($ckanResults)) {
                throw new \Exception('No results from CKAN. Exiting...');
            }

            /* csv for title, url, topic, and topic category */
            foreach ($ckanResults as $dataset) {
                if ($dataset['type'] !== 'dataset') {
                    continue;
                }

                $title = $this->simplifyTitle($dataset['title']);
                $titles[$title] = $dataset['title'];

                $harvestSource = $this->extra($dataset['extras'], 'harvest_source_title');

                if ($harvestSource == $main_harvest_title) {
                    $groups = [];
                    $group_tags = [];
                    if (sizeof($dataset['groups'])) {
                        foreach ($dataset['groups'] as $group) {
                            $tags = $this->extra($dataset['extras'], '__category_tag_' . $group['id']);
                            if ($tags) {
                                $groups[] = $group['title'] . '{' . $tags . '}';

                                $tags = trim($tags, '[]');
                                $tags = explode('","', $tags);
                                foreach ($tags as &$tag) {
                                    $tag = trim($tag, '" ');
                                }

                                $group_tags[$group['title']] = $tags;
                            } else {
                                $group_tags[$group['title']] = '';
                                $groups[] = $group['title'];
                            }
                        }
                    }
                    $main_datasets_by_harvest[] = [
                        'title'    => $title,
                        'basename' => $dataset['name'],
                        'groups'   => join(';', $groups),
                        'tags'     => $group_tags,
                    ];
                    continue;
                }

                if (!isset($datasets_by_harvest[$harvestSource])) {
                    $datasets_by_harvest[$harvestSource] = [];
                }

                if (isset($datasets_by_harvest[$harvestSource][$title])) {
                    $datasets_by_harvest[$harvestSource][$title][] = $dataset['name'];
                } else {
                    $datasets_by_harvest[$harvestSource][$title] = [$dataset['name']];
                }
            }

            $count = $this->resultCount;
            if ($start) {
                echo "start from $start / " . $count . ' total ' . PHP_EOL;
            }

            if ($this->resultCount - $this->packageSearchPerPage < $start) {
                break;
            }
        }

        $other_harvests = array_keys($datasets_by_harvest);
        $csv->writeRow(array_merge(['groups', 'title', $main_harvest_title], array_keys($datasets_by_harvest)));

        foreach ($main_datasets_by_harvest as $dataset) {
            $matches = [];
            foreach ($other_harvests as $harvest) {
                if (isset($datasets_by_harvest[$harvest][$dataset['title']])) {
                    $match = array_shift($datasets_by_harvest[$harvest][$dataset['title']]);
                    $matches[] = $match;

                    if ($match && stripos($harvest, 'json')) {
                        $rename_deleted->writeRow([
                            $dataset['basename'],
                            $dataset['basename'] . '_' . $agency . '_deleted'
                        ]);
                        $rename_json->writeRow([$match, $dataset['basename']]);
                    }
                } else {
                    $matches[] = '';
                }
            }
            $csv->writeRow(array_merge([$dataset['groups'], $titles[$dataset['title']], $dataset['basename']],
                $matches));
        }
    }

    /**
     */
    public function getInteractiveResources()
    {
        $log_file = $this->resultsDir . '/resources.csv';
        $log_fp = fopen($log_file, 'w');

        /*        Title of Dataset in Socrata | dataset URL in Socrata | dataset URL in Catalog */
        $csv_header = [
            'Title of Dataset in Socrata',
            'Dataset URL in Socrata',
            'Dataset URL in Catalog',
        ];

        fputcsv($log_fp, $csv_header);

        /* http://catalog.data.gov/api/search/resource?url=explore.data.gov&all_fields=1&limit=100 */
        $resources = $this->tryApiPackageSearch(['url' => 'explore.data.gov']);
        if (!$resources) {
            throw new \Exception('No resources found');
        }

        foreach ($resources as $resource) {
            if (!isset($resource['package_id']) || !$resource['package_id']) {
                echo "error: no package_id: " . $resource['id'] . PHP_EOL;
                continue;
            }
            $dataset = $this->tryPackageShow($resource['package_id']);
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
    private function tryApiPackageSearch(
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
    public function searchByTitle(
        $title,
        Writer $csv_writer
    ) {
        $solr_title = $this->escapeSolrValue($title);
        $datasets = $this->tryPackageSearch("($solr_title)", '', 3);
        if ($datasets && isset($datasets[0]) && isset($datasets[0]['name'])) {
            $found_title = $this->escapeSolrValue($datasets[0]['title']);
            $exact = ($solr_title == $found_title);
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
    private function escapeSolrValue(
        $string
    ) {
        $string = preg_replace("/'/u", '', $string);
        $string = preg_replace('/[\W]+/u', ' ', $string);

        return $string;
    }

    /**
     * @param $search_list
     */
    public function searchByTerms(
        $search_list
    ) {
        $log_file_popularity = $this->resultsDir . '/search_' . sizeof($search_list) . '_terms_by_popularity.csv';
        $log_file_relevance = $this->resultsDir . '/search_' . sizeof($search_list) . '_terms_by_relevance.csv';
        $fp_popularity = fopen($log_file_popularity, 'w');
        $fp_relevance = fopen($log_file_relevance, 'w');

        $csv_header = [
            'Name of Dataset',
            'Agency',
            'Data.gov URL',
            'Keyword',
        ];

        fputcsv($fp_popularity, $csv_header);
        fputcsv($fp_relevance, $csv_header);

        $ckan_url = 'https://catalog.data.gov/dataset/';
        $counter = 1;

//        most relevant:
//        http://catalog.data.gov/api/3/action/package_search?q=Asian+AND+dataset_type:dataset&sort=score+desc
//        most popular:
//        http://catalog.data.gov/api/3/action/package_search?q=Asian+AND+dataset_type:dataset&sort=views_recent+desc
        foreach ($search_list as $term) {
            echo $counter++ . '/' . sizeof($search_list) . ' : ' . $term . PHP_EOL;
            if (!sizeof($term = trim($term))) {
                continue;
            }
            $ckan_query = $this->escapeSolrValue($term) . ' AND dataset_type:dataset';

            $only_first_page = true;
            if ('Demographics' == $term) {
                $only_first_page = false;
            }

            $done = false;
            $start = 0;
            $per_page = 20;
            while (!$done) {
                // relevance
                $ckanResultRelevance = $this->Ckan->package_search($ckan_query, '', $per_page, $start);
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
                            $fp_relevance, [
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

            $done = false;
            $start = 0;
            $per_page = 20;
            while (!$done) {
                // popularity
                $ckanResultPopularity = $this->Ckan->package_search(
                    $ckan_query, '', $per_page, $start, 'views_recent desc,name asc');
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
                            $fp_popularity, [
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
     */
    public function searchByTopics(
        $groups_list
    ) {
        $this->logOutput = '';
        $log_file_popularity = $this->resultsDir . '/search_' . sizeof($groups_list) . '_topics_by_popularity.csv';
        $log_file_relevance = $this->resultsDir . '/search_' . sizeof($groups_list) . '_topics_by_relevance.csv';
        $error_log = $this->resultsDir . '/search_' . sizeof($groups_list) . '_topics.log';
        $fp_popularity = fopen($log_file_popularity, 'w');
        $fp_relevance = fopen($log_file_relevance, 'w');

        $csv_header = [
            'Name of Dataset',
            'Agency',
            'Data.gov URL',
            'Topic',
        ];

        fputcsv($fp_popularity, $csv_header);
        fputcsv($fp_relevance, $csv_header);

        $ckan_url = 'https://catalog.data.gov/dataset/';
        $counter = 1;

//        most relevant:
//        http://catalog.data.gov/api/3/action/package_search?q=Asian+AND+dataset_type:dataset&sort=score+desc
//        most popular:
//        http://catalog.data.gov/api/3/action/package_search?q=Asian+AND+dataset_type:dataset&sort=views_recent+desc
        foreach ($groups_list as $topic) {
            $this->say(PHP_EOL . $counter++ . '/' . sizeof($groups_list) . ' : ' . $topic);
            if (!sizeof($topic = trim($topic))) {
                continue;
            }

            switch ($topic) {
                case 'Cities':
                    /* http://catalog.data.gov/api/3/action/package_search?q=organization_type:%22City+Government%22 */
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
                        $ckan_query = 'groups:(' . $this->escapeSolrValue($group['name']) . ') AND dataset_type:dataset';
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

            $done = false;
            $start = 0;
            $per_page = 20;
            while (!$done) {
                // relevance
                $ckanResultRelevance = $this->Ckan->package_search($ckan_query, '', $per_page, $start);
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
                            $fp_relevance, [
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

            $done = false;
            $start = 0;
            $per_page = 20;
            while (!$done) {
                // popularity
                $ckanResultPopularity = $this->Ckan->package_search(
                    $ckan_query, '', $per_page, $start, 'views_recent desc,name asc');
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
                            $fp_popularity, [
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
     * Return a list of the names of the site’s groups.
     *
     * @param string $groupName
     *
     * @throws \Exception
     * @return mixed
     */
    private function findGroup(
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
     */
    public function searchByOrganizations(
        $organizations_list
    ) {
        $this->logOutput = '';
        $log_file_popularity = $this->resultsDir . '/search_' . sizeof(
                $organizations_list) . '_organizations_by_popularity.csv';
        $log_file_relevance = $this->resultsDir . '/search_' . sizeof(
                $organizations_list) . '_organizations_by_relevance.csv';
        $error_log = $this->resultsDir . '/search_' . sizeof($organizations_list) . '_organizations.log';

        $fp_popularity = fopen($log_file_popularity, 'w');
        $fp_relevance = fopen($log_file_relevance, 'w');

        $csv_header = [
            'Name of Dataset',
            'Agency',
            'Data.gov URL',
        ];

        fputcsv($fp_popularity, $csv_header);
        fputcsv($fp_relevance, $csv_header);

        $ckan_url = 'http://catalog.data.gov/dataset/';

        $counter = 1;

//        most relevant:
//        http://catalog.data.gov/api/3/action/package_search?q=Asian+AND+dataset_type:dataset&sort=score+desc
//        most popular:
//        http://catalog.data.gov/api/3/action/package_search?q=Asian+AND+dataset_type:dataset&sort=views_recent+desc
        foreach ($organizations_list as $organization) {
            $this->say(PHP_EOL . $counter++ . '/' . sizeof($organizations_list) . ' : ' . $organization);
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

            $done = false;
            $start = 0;
            $per_page = 20;
            while (!$done) {
                // relevance
                $ckanResultRelevance = $this->Ckan->package_search($ckan_query, '', $per_page, $start);
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
                            $fp_relevance, [
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

            $done = false;
            $start = 0;
            $per_page = 20;
            while (!$done) {
                // popularity
                $ckanResultPopularity = $this->Ckan->package_search(
                    $ckan_query, '', $per_page, $start, 'views_recent desc,name asc');
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
                            $fp_popularity, [
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
    private function findOrganization(
        $organizationName
    ) {
        static $OrgList;
        if (!$OrgList) {
            $OrgList = new OrganizationList(AGENCIES_LIST_URL);
        }

        return $OrgList->getTermFor($organizationName);
    }

    /**
     * @param $search_q
     * @param $search_fq
     * @param string $ckan_url
     * @param bool $short
     * @return array
     */
    public function exportShort(
        $search_q,
        $search_fq = '',
        $ckan_url = 'https://catalog.data.gov/dataset/',
        $short = true
    ) {
        return $this->exportBrief($search_q, $search_fq, $ckan_url, $short);
    }

    /**
     * @param string $path
     * @param string $ckan_url
     * @param bool|false $short
     * @return array
     * @throws \Exception
     */
    public function exportBriefFromJson(
        $path ='',
        $ckan_url = 'https://catalog.data.gov/dataset/',
        $short = false
    ) {
        $this->logOutput = '';

        $return = [];

        $datasets = json_decode(file_get_contents($path), true); //assoc

        if (false === $datasets) {
            throw new \Exception('No results found');
        }

        foreach ($datasets as $dataset) {
            $guid = $this->extra($dataset['extras'], 'guid');
            $identifier = $this->extra($dataset['extras'], 'identifier');
            $groups = [];
            if (isset($dataset['groups'])) {
                foreach ($dataset['groups'] as $group) {
                    if (strlen(trim($group['title']))) {
                        $groups[] = trim($group['title']);
                    }
                }
            }
            $categories = [];
            if (isset($dataset['extras'])) {
                foreach ($dataset['extras'] as $extra) {
                    if (false !== strpos($extra['key'], '__category_tag_')) {
                        $tags = trim($extra['value'], '[]');
                        $tags = explode('","', $tags);
                        foreach ($tags as &$tag) {
                            $tag = trim($tag, '" ');
                        }
                        $categories = array_merge($categories, $tags);
                    }
                }
            }

            $line = [
                'title'        => $dataset['title'],
                'title_simple' => $this->simplifyTitle($dataset['title']),
                'name'         => $dataset['name'],
                'url'          => $ckan_url . $dataset['name'],
                'identifier'   => $identifier,
                'guid'         => $guid,
                'topics'       => join(';', $groups),
                'categories'   => join(';', $categories),
            ];

            if ($short) {
                unset($line['title_simple']);
                unset($line['guid']);
            }
            $return[$dataset['name']] = $line;
        }

        return $return;
    }

    /**
     * @param string $search_q
     * @param string $search_fq
     * @param string $ckan_url
     * @param bool|false $short
     * @return array
     * @throws \Exception
     */
    public function exportBrief(
        $search_q = '',
        $search_fq = '',
        $ckan_url = 'https://catalog.data.gov/dataset/',
        $short = false
    ) {
        $this->logOutput = '';

        $return = [];

        $done = false;
        $start = 0;
        $per_page = 250;
        echo $search_q . PHP_EOL;

        while (!$done) {
            $datasets = $this->tryPackageSearch($search_q, $search_fq, $per_page, $start);
            if (false === $datasets) {
                throw new \Exception('No results found');
            }

            $totalCount = $this->resultCount;
            $count = sizeof($datasets);

            if (!$start) {
                echo "Found $totalCount results" . PHP_EOL;
            }

            $start += $per_page;
            echo $start . '/' . $totalCount . PHP_EOL;
            if (!$totalCount) {
                $done = true;
                continue;
            }

            if ($count) {
                foreach ($datasets as $dataset) {
                    $guid = $this->extra($dataset['extras'], 'guid');
                    $identifier = $this->extra($dataset['extras'], 'identifier');
                    $groups = [];
                    if (isset($dataset['groups'])) {
                        foreach ($dataset['groups'] as $group) {
                            if (strlen(trim($group['title']))) {
                                $groups[] = trim($group['title']);
                            }
                        }
                    }
                    $categories = [];
                    if (isset($dataset['extras'])) {
                        foreach ($dataset['extras'] as $extra) {
                            if (false !== strpos($extra['key'], '__category_tag_')) {
                                $tags = trim($extra['value'], '[]');
                                $tags = explode('","', $tags);
                                foreach ($tags as &$tag) {
                                    $tag = trim($tag, '" ');
                                }
                                $categories = array_merge($categories, $tags);
                            }
                        }
                    }

                    $line = [
                        'title'        => $dataset['title'],
                        'title_simple' => $this->simplifyTitle($dataset['title']),
                        'name'         => $dataset['name'],
                        'url'          => $ckan_url . $dataset['name'],
                        'identifier'   => $identifier,
                        'guid'         => $guid,
                        'topics'       => join(';', $groups),
                        'categories'   => join(';', $categories),
                    ];

                    if ($short) {
                        unset($line['title_simple']);
                        unset($line['guid']);
                    }
                    $return[$dataset['name']] = $line;
                }
            } else {
                echo 'no results: ' . $search_q . PHP_EOL;
                continue;
            }
            if ($start > $totalCount) {
                $done = true;
            }
        }

        return $return;
    }

    /**
     * @param $package_id
     * @return array
     * @throws \Exception
     */
    public function exportPackage($package_id)
    {
        $dataset = $this->tryPackageShow($package_id);
        if (false === $dataset) {
            throw new \Exception('Dataset not found. Exiting...');
        }
        $return = [];

        $extras = $dataset['extras'];
        $category_id_tags = [];
        foreach ($extras as $extra) {
            if (false !== strpos($extra['key'], '__category_tag_')) {
                $category_id = str_replace('__category_tag_', '', $extra['key']);
                $tags = trim($extra['value'], '[]');
                $tags = explode('","', $tags);
                foreach ($tags as &$tag) {
                    $tag = trim($tag, '" ');
                }
                $category_id_tags[$category_id] = $tags;
            }
        }

        if (sizeof($dataset['groups'])) {
            foreach ($dataset['groups'] as $group) {
                $tags = isset($category_id_tags[$group['id']]) ?
                    join(';', $category_id_tags[$group['id']]) : '';
                $return[] = [$dataset['name'], $group['title'], $tags];
            }
        }

        return $return;
    }

    /**
     * Export all packages by organization term
     *
     * @param $terms
     */
    public function exportPackagesByOrgTerms(
        $terms
    ) {
        $this->say(ORGANIZATION_TO_EXPORT . PHP_EOL);
        $ckan_url = 'http://catalog.data.gov/';
//        $ckan_url = 'http://qa-catalog-fe-data.reisys.com/dataset/';
//        $ckan_url = 'http://uat-catalog-fe-data.reisys.com/dataset/';

        $csv_global = new Writer($this->resultsDir . '/_combined.csv', 'w');
        $csv_global->writeRow(['Title', 'Url', 'Organization', 'Topics', 'Topics categories','Metadata Type']);

        foreach ($terms as $term => $agency) {
            $page = 0;
            $count = 0;

            $csv_tag_writer = new Writer($this->resultsDir . '/' . $term . '_tagging.csv', 'w');
            $csv_tag_writer->writeRow(['dataset', 'topic', 'tags', 'metadata_type']);
//            $csv_tag_writer->writeRow([
//                'Dataset Title',
//                'Dataset URL',
//                'Organization Name',
//                'Organization Link',
//                'Harvest Source Name',
//                'Harvest Source LInk',
//                'Topic Name',
//                'Topic Categories',
//            ]);

            $csv = new Writer($this->resultsDir . '/' . $term . '.csv', 'w');
            $csv->writeRow(['Title', 'Url', 'Topics', 'Topics categories', 'Metadata Type']);

            file_put_contents($this->resultsDir . '/' . $term . '.json', '[' . PHP_EOL, FILE_APPEND);
            file_put_contents($this->resultsDir . '/' . $term . '_geospatial_with_tags.json', '[' . PHP_EOL,
                FILE_APPEND);
            $comma_needed = false;
            $with_tags_comma_needed = false;

            while (true) {
                $start = $page++ * $this->packageSearchPerPage;
//                $ckanResults = $this->tryPackageSearch('extras_metadata-source:dms AND dataset_type:dataset AND organization:' . $term,
//                   '', $this->packageSearchPerPage, $start);
                $ckanResults = $this->tryPackageSearch('organization:' . $term . ' AND dataset_type:dataset',
                    '', $this->packageSearchPerPage, $start);
//                $ckanResults = $this->tryPackageSearch('dataset_type:dataset AND name:national-flood-hazard-layer-nfhl',
//                     '', $this->packageSearchPerPage, $start);
//                $ckanResults = $this->tryPackageSearch('groups:*', '', $this->packageSearchPerPage, $start);

                if (!is_array($ckanResults)) {
                    break;
                }

//                csv for title, url, topic, and topic category
                foreach ($ckanResults as $dataset) {
                    $extras = $dataset['extras'];
                    $metadata_type = '';
                    $category_id_tags = [];
                    $categories_tags = [];
                    foreach ($extras as $extra) {
                        if ('metadata_type' == $extra['key']) {
                            $metadata_type = $extra['value'];
                            continue;
                        }
                        if (false !== strpos($extra['key'], '__category_tag_')) {
                            $category_id = str_replace('__category_tag_', '', $extra['key']);
                            $tags = trim($extra['value'], '[]');
                            $tags = explode('","', $tags);
                            foreach ($tags as &$tag) {
                                $tag = trim($tag, '" ');
                            }
                            $category_id_tags[$category_id] = $tags;
                            $categories_tags = array_merge($categories_tags, $tags);
                        }
                    }
                    $categories_tags = sizeof($categories_tags) ? join(';', $categories_tags) : false;
                    $categories = [];
                    if (sizeof($dataset['groups'])) {
                        foreach ($dataset['groups'] as $group) {
                            $categories[] = $group['title'];
                            $tags = isset($category_id_tags[$group['id']]) ?
                                join(';', $category_id_tags[$group['id']]) : '';
                            $csv_tag_writer->writeRow([$dataset['name'], $group['title'], $tags, $metadata_type]);

//                            $harvest_title = self::extra($dataset['extras'], 'harvest_source_title');
//                            $harvest_title = $harvest_title ?: '';
//                            $harvest_id = self::extra($dataset['extras'], 'harvest_source_id');
//                            $harvest_url = $harvest_id ? $ckan_url . 'harvest/' . $harvest_id : '';
//
//                            $csv_tag_writer->writeRow(
//                                [
//                                    isset($dataset['title']) ? $dataset['title'] : '',
//                                    isset($dataset['name']) ? $ckan_url . 'dataset/' . $dataset['name'] : '',
//                                    isset($dataset['organization']) ? $dataset['organization']['title'] : '',
//                                    isset($dataset['organization']) ? $ckan_url . 'organization/' . $dataset['organization']['name'] : '',
//                                    $harvest_title,
//                                    $harvest_url,
//                                    $group['title'],
//                                    $tags,
//                                ]
//                            );
                        }
                    }
                    $categories = sizeof($categories) ? join(';', $categories) : false;

                    $csv->writeRow(
                        [
                            isset($dataset['title']) ? $dataset['title'] : '',
                            isset($dataset['name']) ? $ckan_url . $dataset['name'] : '',
                            $categories ? $categories : '',
                            $categories_tags ? $categories_tags : '',
                            $metadata_type,
                        ]
                    );

                    $csv_global->writeRow(
                        [
                            isset($dataset['title']) ? $dataset['title'] : '',
                            isset($dataset['name']) ? $ckan_url . $dataset['name'] : '',
                            isset($dataset['organization']) ? $dataset['organization']['title'] : '',
                            $categories ? $categories : '',
                            $categories_tags ? $categories_tags : '',
                            $metadata_type
                        ]
                    );

                    if ('geospatial' == $metadata_type && $categories) {
                        file_put_contents($this->resultsDir . '/' . $term . '_geospatial_with_tags.json',
                            ($with_tags_comma_needed ? ',' . PHP_EOL : '') . json_encode($dataset, JSON_PRETTY_PRINT),
                            FILE_APPEND);
                        $with_tags_comma_needed = true;
                    }

                    file_put_contents($this->resultsDir . '/' . $term . '.json',
                        ($comma_needed ? ',' . PHP_EOL : '') . json_encode($dataset, JSON_PRETTY_PRINT),
                        FILE_APPEND);
                    $comma_needed = true;
                }

                $count = $this->resultCount;
                if ($start) {
                    echo "start from $start / " . $count . ' total [' . $term . ']' . PHP_EOL;
                }

                if ($this->resultCount - $this->packageSearchPerPage < $start) {
                    break;
                }
            }

            $offset = ($term == PARENT_TERM) ? '' : '  ';
            $this->say(
                str_pad($offset . "[$term]", 20) . str_pad(
                    $offset . $agency, 50, ' .') . "[$count]"
            );

            file_put_contents($this->resultsDir . '/' . $term . '.json', PHP_EOL . ']', FILE_APPEND);
            file_put_contents($this->resultsDir . '/' . $term . '_geospatial_with_tags.json', PHP_EOL . ']',
                FILE_APPEND);

//            if (sizeof($results)) {
////                $json = "[" . PHP_EOL . join(',' . PHP_EOL, $results) . PHP_EOL . ']';
////                file_put_contents($this->resultsDir . '/' . $term . '.json', $json, FILE_APPEND);
//            } else {
//                unlink($this->resultsDir . '/' . $term . '.csv');
//            }
        }
        file_put_contents($this->resultsDir . '/_' . PARENT_TERM . '.log', $this->logOutput);
    }

    /**
     * Export all dataset visit tracking by organization term
     *
     * @param $terms
     */
    public function exportTrackingByOrgTerms(
        $terms
    ) {
        $this->logOutput = '';
        $this->say(ORGANIZATION_TO_EXPORT . PHP_EOL);
        foreach ($terms as $term => $agency) {

            $csv_log_file = fopen($this->resultsDir . '/' . $term . '.csv', 'w');

            $csv_header = [
                'Organization',
                'Dataset Title',
                'Recent Visits',
                'Total Visits',
            ];

            fputcsv($csv_log_file, $csv_header);

            $page = 0;
            $count = 0;
            while (true) {
                $start = $page++ * $this->packageSearchPerPage;
                $ckanResult = $this->Ckan->package_search(
                    'organization:' . $term, '', $this->packageSearchPerPage, $start);
                $ckanResult = json_decode($ckanResult, true); //  decode json as array
                $ckanResult = $ckanResult['result'];

                if (sizeof($ckanResult['results'])) {
                    foreach ($ckanResult['results'] as $dataset) {
                        fputcsv(
                            $csv_log_file, [
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

            fclose($csv_log_file);

            $offset = ($term == PARENT_TERM) ? '' : '  ';
            $this->say(
                str_pad($offset . "[$term]", 20) . str_pad(
                    $offset . $agency, 50, ' .') . "[$count]"
            );
        }
        file_put_contents($this->resultsDir . '/_' . PARENT_TERM . '.log', $this->logOutput);
    }

    /**
     * Ability to tag datasets by extra field
     *
     * @param string $extra_field
     * @param string $tag_name
     */
    public function tagByExtraField(
        $extra_field,
        $tag_name
    ) {
        $this->logOutput = '';
        $page = 0;
        $processed = 0;
        $tag_template = [
            'key'   => $tag_name,
            'value' => true,
        ];

        $marked_true = 0;
        $marked_other = 0;

        while (true) {
            $start = $page++ * $this->packageSearchPerPage;
            $ckanResult = $this->Ckan->package_search('identifier:*', '', $this->packageSearchPerPage, $start);
            $ckanResult = json_decode($ckanResult, true); //  decode json as array
            $ckanResult = $ckanResult['result'];

            if (!($count = $ckanResult['count'])) {
                break;
            }

            $datasets = $ckanResult['results'];

            foreach ($datasets as $dataset) {
                $processed++;
                if (!isset($dataset['extras']) || !is_array($dataset['extras']) || !sizeof(
                        $dataset['extras'])
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

                $this->tryPackageUpdate($dataset);
                $marked_true++;
            }

            echo "processed $processed ( $tag_name true = $marked_true, other = $marked_other) / " . $count . ' total ' . PHP_EOL;
            if ($count - $this->packageSearchPerPage < $start) {
                break;
            }
        }
        file_put_contents($this->resultsDir . '/_' . $tag_name . '.log', $this->logOutput);
    }

    /**
     * @param $dataset_id
     * @param $basename
     */
    public function makeDatasetPrivate(
        $dataset_id,
        $basename
    ) {
        $this->logOutput = '';
        $log_file = $basename . '_private.log';

        $this->say(str_pad($dataset_id, 105, ' . '), '');

        $dataset = $this->tryPackageShow($dataset_id);

        if (!$dataset) {
            $this->say(str_pad('NOT_FOUND', 10, ' '));
        } else {

            $dataset['private'] = true;
            $dataset['name'] .= '_legacy';
            $dataset['tags'][] = [
                'name' => 'metadata_from_legacy_dms',
            ];

            try {
                $this->tryPackageUpdate($dataset);
                $this->say(str_pad('OK', 10, ' '));
            } catch (\Exception $ex) {
                $this->say(str_pad('ERROR', 10, ' '));
//                die(json_encode($dataset, JSON_PRETTY_PRINT) . PHP_EOL . $ex->getMessage() . PHP_EOL . PHP_EOL);
                file_put_contents($this->resultsDir . '/err.log', $ex->getMessage() . PHP_EOL, FILE_APPEND);
            }
        }
        file_put_contents($this->resultsDir . '/' . $log_file, $this->logOutput, FILE_APPEND);
        $this->logOutput = '';
    }

    /**
     * Ability to Add legacy tag to all dms datasets for an organization and make all those datasets private
     *
     * @param $termsArray
     * @param $tag_name
     */
    public function tagLegacyDms(
        $termsArray,
        $tag_name
    ) {
        $this->logOutput = '';

//        get all datasets to update
        $datasets = $this->getDmsPublicDatasets($termsArray);

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
            echo str_pad(($key + 1) . " / $count ", 10, ' ');

            $csv->writeRow(
                [$dataset['name'], $newDatasetName = $dataset['name'] . '_legacy',]);

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
                    $this->tryPackageUpdate($dataset);
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
    private function getDmsPublicDatasets(
        $terms = null,
        $options = 0
    ) {
        $dms_datasets = [];
        $page = 0;

        if ($terms) {
            $organizationFilter = array_keys($terms);
            // & = ugly hack to prevent 'Unused local variable' error by PHP IDE, it works perfect without &
            array_walk(
                $organizationFilter, function (&$term) {
                $term = ' organization:"' . $term . '" ';
            }
            );
            $organizationFilter = ' AND (' . join(' OR ', $organizationFilter) . ')';
        } else {
            $organizationFilter = '';
        }

        while (true) {
            $start = $page++ * $this->packageSearchPerPage;
            $ckanResult = $this->Ckan->package_search(
                'dms' . $organizationFilter, '', $this->packageSearchPerPage, $start);
            $ckanResult = json_decode($ckanResult, true); //  decode json as array
            $ckanResult = $ckanResult['result'];
            foreach ($ckanResult['results'] as $dataset) {
                if (!isset($dataset['extras']) || !is_array($dataset['extras']) || !sizeof(
                        $dataset['extras'])
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
     */
    public function exportOrganizations(
        $termsArray
    ) {
        if (!sizeof($termsArray)) {
            return;
        }
        $termsArrayKeys = array_keys($termsArray);
        foreach ($termsArrayKeys as $org_slug) {

            try {
                $results = $this->Ckan->organization_show($org_slug);
            } catch (NotFoundHttpException $ex) {
                echo "Couldn't find $org_slug";
                continue;
            }

            if ($results) {
                $results = json_decode($results);

                $json = (json_encode($results, JSON_PRETTY_PRINT));
                file_put_contents($this->resultsDir . '/' . $org_slug . '.json', $json);
            }
        }
    }

    /**
     * @param $organization
     * @param int $options
     */
    public function fullOrganizationExport($organization, $options = 0)
    {
        if (($options & self::EXPORT_PUBLIC_ONLY) && ($options & self::EXPORT_DMS_ONLY)) {
            $members = $this->getDmsPublicDatasets([$organization => $organization], self::IDS_ONLY);
        } else {
            $members = $this->tryMemberList($organization);
        }

        if (!$members || !is_array($members) || !sizeof($members)) {
            $this->say([$organization, 0]);

//            $this->say(sprintf('%25s%10d', $organization, 0));
            return;
        }

        $export = [];
        $exportIds = [];
        foreach ($members as $package) {
            //  member_list returns weird array
            $package_id = is_array($package) ? $package[0] : $package;
            $dataset = $this->tryPackageShow($package_id);

            if ('dataset' != $dataset['type']) {
                echo $package_id . ' :: NOT A DATASET' . PHP_EOL;
                continue;
            }

            if ('deleted' == $dataset['state']) {
                echo $package_id . ' :: DELETED' . PHP_EOL;
                continue;
            }

            if ($options & self::EXPORT_PRIVATE_ONLY && !$dataset['private']) {
                echo $package_id . ' :: IS PUBLIC' . PHP_EOL;
                continue;
            }

            if ($options & self::EXPORT_PUBLIC_ONLY && $dataset['private']) {
                echo $package_id . ' :: IS PRIVATE' . PHP_EOL;
                continue;
            }

            $package_id = $dataset['name'];
            $dataset = json_encode($dataset, JSON_PRETTY_PRINT);

            if ($options & self::EXPORT_DMS_ONLY && !(strstr($dataset, '"dms"') && strstr($dataset,
                        '"metadata-source"'))
            ) {
                echo $package_id . ' :: IS DMS' . PHP_EOL;
                continue;
            }

            $exportIds[] = $package_id;
            $export[] = $dataset;
        }

        $total = sizeof($exportIds);
//        $this->say(sprintf('%25s%10d', $organization, $total));
        $this->say([$organization, $total]);

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
     * @param string $member_id
     * @param int $try
     *
     * @return bool|mixed
     */
    private function tryMemberList(
        $member_id,
        $try = 3
    ) {
        $list = false;
        while ($try) {
            try {
                $list = $this->Ckan->member_list($member_id);
                $list = json_decode($list, true); // as array

                if (!$list['success']) {
                    return false;
                }

                if (!isset($list['result']) || !sizeof($list['result'])) {
                    return false;
                }

                $list = $list['result'];

                $try = 0;
            } catch (NotFoundHttpException $ex) {
                echo "Organization not found: " . $member_id . PHP_EOL;

                return false;
            } catch (\Exception $ex) {
                sleep(3);
                echo '      zzz   ' . $member_id . PHP_EOL;
                $try--;
                if (!$try) {
                    echo 'Too many attempts: ' . $member_id . PHP_EOL;

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

     */
    public function organizations_stats()
    {
        /**
         * curl --data '{"all_fields":true}' "https://catalog.data.gov/api/action/organization_list" > organizations.json
         */
        $isInventory = false;
        if (false === strstr($this->apiUrl, 'https')) {
            $ckan_url = 'https://catalog.data.gov/';
            $orgs = file_get_contents(CKANMNGR_DATA_DIR . '/organizations.json');
            $filename = '/catalog_orgs_list_' . (START ?: '') . '.csv';
        } else {
            $isInventory = true;
            $ckan_url = 'https://inventory.data.gov/';
            $orgs = $this->Ckan->organization_list();
            $filename = '/inventory_orgs_list_' . (START ?: '') . '.csv';
        }

        $filter = false;
        if (is_file(CKANMNGR_DATA_DIR . '/organizations_stats_filter.csv')) {
            $filter = array_filter(explode(PHP_EOL, file_get_contents(CKANMNGR_DATA_DIR . '/organizations_stats_filter.csv')));
            if (!sizeof($filter)) {
                $filter = false;
            } else {
                echo 'Applying filter (' . sizeof($filter) . ' orgs)' . PHP_EOL;
//                $this->say('Applying filter ('.sizeof($filter).' orgs)');
            }
        }

        $orgs = json_decode($orgs);
        $orgs = $orgs->result;


        // initialize log file
        $log_file = $this->resultsDir . $filename;
        $fp_log = fopen($log_file, 'w');

        $csv_header = [
            'Organization ID',
            'Organization Name',
            'Organization URL',
            'organization_show',
            'Public & Active',
            'Private & Active',
            'member_list (incl. private)',
            'State: Active',
            'State: Draft',
            'State: Draft-complete',
            'State: Deleted',
        ];

        if (!$isInventory) {
            $csv_header[] = 'DMS Private';
            $csv_header[] = 'DMS Public';
            $csv_header[] = 'Non-DMS Public';
        } else {
            $csv_header[] = 'No modified & Public & Active';
            $csv_header[] = 'No modified & Private & Active';
        }

        fputcsv($fp_log, $csv_header);

//        $total = $filter ? sizeof($filter) : sizeof($orgs);
        $counter = 0;
        $skip = (bool)START;
        foreach ($orgs as $org) {
            $org_slug = is_object($org) ? $org->name : $org;

            if (defined('LIST_ORGS_ONLY') && LIST_ORGS_ONLY) {
//                $this->say([++$i, $org_slug]);
                continue;
            }

            if ($filter && !in_array($org_slug, $filter)) {
                continue;
            }

            $counter++;

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

//                $this->say(sprintf("[%0{$digits_count}d/%0{$digits_count}d] %s", $i, $total, $org_slug));

                $member_list = $this->tryMemberList($org_slug);
//                $private_count = 'na';
                $private = $public = $notModifiedPublic = $notModifiedPrivate = 0;
                $total_count = 'na';
                $package_states = [];
                $dms_public = $dms_private = $non_dms_public = 0;

                if (is_array($member_list)) {
                    $total_count = sizeof($member_list);
                    $member_digits_count = strlen('' . $total_count);
//                    $private_count = $total_count - $org_results->package_count;

                    $j = 0;
                    foreach ($member_list as $member) {
                        if (!(++$j % 1000)) {
                            printf("   [%0{$member_digits_count}d/%0{$member_digits_count}d] members of: %s" . PHP_EOL,
                                $j, $total_count, $org_slug);
                        }
                        $package_id = $member[0];
                        $package = $this->tryPackageShow($package_id);
                        if ('dataset' !== $package['type']) {
                            continue;
                        }

                        $state = $package['state'];
                        if (!isset($package_states[$state])) {
                            $package_states[$state] = 1;
                        } else {
                            $package_states[$state]++;
                        }

                        if ('active' == $state) {
                            if ($package['private']) {
                                $private++;
                            } else {
                                $public++;
                            }
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
                        } else {
                            if ('active' == $state) {
                                if (isset($package['extras'])) {
                                    $extras = json_encode($package['extras']);
                                    if (!strpos($extras, '"modified"')) {
                                        if ($package['private']) {
                                            $this->say([
                                                'https://inventory.data.gov/dataset/' . $package['name'],
                                                $org_slug,
                                                'private'
                                            ]);
                                            $notModifiedPrivate++;
                                        } else {
                                            $this->say([
                                                'https://inventory.data.gov/dataset/' . $package['name'],
                                                $org_slug,
                                                'public'
                                            ]);
                                            $notModifiedPublic++;
                                        }
                                    }
                                } else {
                                    if ($package['private']) {
                                        $notModifiedPrivate++;
                                        $this->say([
                                            'https://inventory.data.gov/dataset/' . $package['name'],
                                            $org_slug,
                                            'private'
                                        ]);
                                    } else {
                                        $notModifiedPublic++;
                                        $this->say([
                                            'https://inventory.data.gov/dataset/' . $package['name'],
                                            $org_slug,
                                            'public'
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }

                $organization_row = [
                    $org_results->name,
                    $org_results->title,
                    $ckan_url . 'organization/' . $org_results->name,
                    $org_results->package_count,
                    $public,
                    $private,
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
                } else {
                    $organization_row[] = $notModifiedPublic;
                    $organization_row[] = $notModifiedPrivate;
                }

                fputcsv($fp_log, $organization_row);

//                if ($users = $org_results->result->users) {
//
//                    foreach ($users as $org_user) {
//                        $user_id = $org_user->name;
//
//                        try {
//                            $user_results = $this->CkanManager->user_show($user_id);
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
//                                    $org_results->result->title,//                                    $org_results->result->name,//                                    $user->fullname,//                                    $user->name,//                                    $user->email,//                                    $user->number_administered_packages,//                                    $user->number_of_edits,//                                    $org_user->capacity,//                                    $user->sysadmin,//                                    $last_activity//                                );
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

     */
    public function activeUsers()
    {
        /**
         * curl --data '{"all_fields":true}' "https://catalog.data.gov/api/action/organization_list" > organizations.json
         */
        if (false === strstr($this->apiUrl, 'https')) {
            $orgs = file_get_contents(CKANMNGR_DATA_DIR . '/organizations.json');
        } else {
            $orgs = $this->Ckan->organization_list();
        }

        $orgs = json_decode($orgs);
        $orgs = $orgs->result;


        // initialize log file
        $log_file = $this->resultsDir . '/user_list.csv';
        $fp_log = fopen($log_file, 'w');

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
    public function deleteDataset($datasetName, $organizationName = '')
    {
        $dataset = $this->tryPackageShow($datasetName);
        if (!$dataset) {
            $this->say([$datasetName, $organizationName, '404 Not Found']);

            return;
        }

        if ('deleted' == $dataset['state']) {
            $this->say([$datasetName, $organizationName, 'Already deleted']);

            return;
        }

        if (!isset($dataset['private']) || !$dataset['private']) {
            $this->say([$datasetName, $organizationName, 'Not private']);

            return;
        }

        $result = $this->tryPackageDelete($datasetName);
        if ($result) {
            $this->say([$datasetName, $organizationName, 'DELETED']);

            return;
        }

        $this->say([$datasetName, $organizationName, 'ERROR']);
    }

    /**
     * @param $datasetId
     * @param int $try
     *
     * @return bool
     */
    private function tryPackageDelete($datasetId, $try = 3)
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
     * @param $datasetName
     * @param $licenseId
     */
    public function updateLicenseId($datasetName, $licenseId)
    {
        $result = false;
        $dataset = $this->tryPackageShow($datasetName);
        if (!$dataset) {
            $this->say([$datasetName, '404 Not Found']);

            return;
        }
        $dataset['license_id'] = $licenseId;
        try {
            $result = $this->tryPackageUpdate($dataset);
        } catch (\Exception $ex) {
            echo 'Exception';
            file_put_contents($this->resultsDir . '/err.log', $ex->getMessage() . PHP_EOL, FILE_APPEND);
        }
        if ($result) {
            //$this->say($result);
            $this->say([$datasetName, 'UPDATED']);

            return;
        }
        $this->say([$datasetName, 'ERROR']);
    }

    /**
     * @param $datasetName
     */
    public function undeleteDataset($datasetName)
    {
        $dataset = $this->tryPackageShow($datasetName);
        if (!$dataset) {
            $this->say([$datasetName, '404 Not Found']);

            return;
        }

        if ('deleted' !== $dataset['state']) {
            $this->say([$datasetName, 'Already undeleted']);

            return;
        }

        $dataset['state'] = 'active';
        $result = $this->tryPackageUpdate($dataset);

//        if (!isset($dataset['private']) || !$dataset['private']) {
//            $this->say([$datasetName, $organizationName, 'Not private']);
//
//            return;
//        }
//
//        $result = $this->tryPackageDelete($datasetName);
        if ($result) {
            $this->say([$datasetName, 'UNDELETED']);

            return;
        }

        $this->say([$datasetName, 'ERROR']);
    }

    /**
     * @param $resource
     */
    public function resourceCreate($resource)
    {
        $dataset_name_or_id = $resource['package_id'];
        $dataset = $this->tryPackageShow($dataset_name_or_id);
        if (!$dataset) {
            $this->say(
                [$dataset_name_or_id, $resource['url'], 'ERROR', 'could not find dataset by id/name']);

            return;
        }

        if ('dataset' !== $dataset['type']) {
            $this->say([$dataset_name_or_id, $resource['url'], 'ERROR', 'package is not of type "dataset"']);

            return;
        }

        $existing_resources = json_encode($dataset['resources']);
        if (strstr($existing_resources, $resource['url']) || strstr($existing_resources, json_encode($resource['url']))
        ) {
            $this->say(
                [$dataset_name_or_id, $resource['url'], 'ERROR', 'same package url already exists in this dataset']);

            return;
        }

        $resource['package_id'] = $dataset['id'];

        $result = $this->tryResourceCreate($resource);

        $this->say([$dataset_name_or_id, $resource['url'], 'SUCCESS', $result['id']]);

        return;

    }

    /**
     * @param $resource_json
     * @param int $try
     *
     * @return bool|mixed
     */
    private function tryResourceCreate(
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
     * @param $basename
     */
    public function renameDataset(
        $datasetName,
        $newDatasetName,
        $basename
    ) {
        $this->logOutput = '';
        $log_file = $basename . '_rename.log';
        $log_csv = new Writer($this->resultsDir . '/' . $basename . '_rename.csv', 'a');

        $this->say(str_pad($datasetName, 100, ' . '), '');

        try {
            $ckanResult = $this->Ckan->package_show($datasetName);
        } catch (NotFoundHttpException $ex) {
            $log_csv->writeRow([$datasetName, $newDatasetName, 'NOT FOUND']);
            $this->say(str_pad('NOT FOUND', 10, ' . ', STR_PAD_LEFT));
            file_put_contents($this->resultsDir . '/' . $log_file, $this->logOutput, FILE_APPEND);
            $this->logOutput = '';

            return;
        }

        $occupied = $this->tryPackageShow($newDatasetName);
        if ($occupied) {
            $private = $occupied['private'] ? '(private)' : '(public)';
            $log_csv->writeRow([$datasetName, $newDatasetName, 'OCCUPIED ' . $private]);
            $this->say(str_pad('OCCUPIED ' . $private, 18, ' . ', STR_PAD_LEFT));

            return;
        }

        $ckanResult = json_decode($ckanResult, true);
        $dataset = $ckanResult['result'];

        $dataset['name'] = $newDatasetName;
//        $dataset['private'] = true;

        $result = false;
        try {
            $result = $this->tryPackageUpdate($dataset);
        } catch (\Exception $ex) {
            echo 'Exception';
            file_put_contents($this->resultsDir . '/err.log', $ex->getMessage() . PHP_EOL, FILE_APPEND);
        }
        if ($result) {
            $log_csv->writeRow([$datasetName, $newDatasetName, 'OK']);
            $this->say(str_pad('OK', 7, ' '));
        } else {
            $log_csv->writeRow([$datasetName, $newDatasetName, 'FAIL']);
            $this->say(str_pad('FAIL', 7, ' '));
        }

        file_put_contents($this->resultsDir . '/' . $log_file, $this->logOutput, FILE_APPEND);
        $this->logOutput = '';
    }

    /**
     * Moves legacy datasets to parent organization
     *
     * @param $organization
     * @param $termsArray
     * @param $backup_dir
     */
    public function reorganizeDatasets(
        $organization,
        $termsArray,
        $backup_dir
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
        $time = time();
        $log_file = (isset($department) ? $department : '_') . '_' . "$time.log";

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
                        $dataset = json_decode($ckanResult, true);

                        $dataset = $dataset['result'];

                        // note the legacy organization as an extra field
                        $dataset['extras'][] = [
                            'key'   => 'dms_publisher_organization',
                            'value' => $org_slug
                        ];

                        $dataset['owner_org'] = $department_id;

                        $this->tryPackageUpdate($dataset);

                        $output = 'Moved ' . $current_record;
                        $this->say($output);
                    }
                } else {
                    $output = "Couldn't find backup file: " . $file_path;
                    $this->say($output);
                }
            }
        }

        file_put_contents($this->resultsDir . '/' . $log_file, $this->logOutput);

    }

    /**
     * @param $datasetName
     * @param $stagingDataset
     */
    public function diffUpdate(
        $datasetName,
        $stagingDataset
    ) {
        try {
            $freshDataset = $this->getDataset($datasetName);
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

            $this->tryPackageUpdate($freshDataset);

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
    public function getDataset(
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
     *
     * @throws \Exception
     */
    public function exportDatasetsWithTagsByGroup(
        $group
    ) {
        $this->logOutput = '';

        if (!($group = $this->findGroup($group))) {
            throw new \Exception('Group ' . $group . ' not found!' . PHP_EOL);
        }

        $log_file = $this->resultsDir . '/export_group_' . $group['name'] . '_with_tags.csv';
        $csv_log_file = fopen($log_file, 'w');

        $csv_header = [
            'Name of Dataset',
            'Dataset Link',
            'Topic Name',
            'Topic Categories',
        ];

        fputcsv($csv_log_file, $csv_header);

        $ckan_url = 'https://catalog.data.gov/dataset/';

        $ckan_query = $this->escapeSolrValue($group['name']) . ' AND dataset_type:dataset';

        $category_key = ('__category_tag_' . $group['id']);

        $start = 0;
        $per_page = 50;

        echo $ckan_query . PHP_EOL;

        while (true) {
            $datasets = $this->tryPackageSearch($ckan_query, '', $per_page, $start, 5);
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
                    $csv_log_file, [
                        isset($dataset['title']) ? $dataset['title'] : '---',
                        isset($dataset['name']) ? $ckan_url . $dataset['name'] : '---',
                        $group['title'],
                        $tags ? $tags : '---'
                    ]
                );
            }
        }

        fclose($csv_log_file);
    }

    /**
     * @param $search
     *
     * @throws \Exception
     */
    public function exportDatasetsBySearch(
        $search
    ) {
        $this->logOutput = '';

        $date = date('Ymd-His');
        $filename_strip_search = preg_replace("/[^a-zA-Z0-9\\ ]+/i", '', $search);
        $log_file = $this->resultsDir . '/export_' . $filename_strip_search . '_' . $date . '.csv';
        $csv_log_file = fopen($log_file, 'w');

        $csv_header = [
            'data.gov url',
            'topic name'
        ];

        echo $search;

        fputcsv($csv_log_file, $csv_header);

        $ckan_url = 'https://catalog.data.gov/dataset/';

        $ckan_query = '(("' . $search . '") AND (dataset_type:dataset))';
//        $ckan_query = "'data.jsonld'";
//        $ckan_query = $search;
//        $ckan_query = '(organization:"epa-gov") AND (dataset_type:dataset)';
//        $ckan_query = 'metadata-source:dms';
//        $ckan_query = 'organization_type:"State Government" AND (dataset_type:dataset)';

        echo $ckan_query . PHP_EOL;

        $done = false;
        $start = 0;
        $per_page = 100;
        while (!$done) {
            echo $ckan_query . PHP_EOL;
            $datasets = $this->tryPackageSearch($ckan_query, '', $per_page, $start);

            $totalCount = $this->resultCount;
            $count = sizeof($datasets);

//            echo "Found $totalCount results" . PHP_EOL;

            /**
             * Sample title:
             * export_fresc_20141117-121528_[1..33].json
             */
            $out_json_path = $this->resultsDir . '/export_' . $filename_strip_search . '_' . $date
                . '_[' . ($start + 1) . '..' . ($start + $count) . '].json';
            file_put_contents($out_json_path, json_encode($datasets, JSON_PRETTY_PRINT));

            $start += $per_page;
            echo $start . '/' . $totalCount . PHP_EOL;
            if (!$totalCount) {
                $done = true;
                continue;
            }

            if (sizeof($datasets)) {
                foreach ($datasets as $dataset) {
                    fputcsv(
                        $csv_log_file, [
                            isset($dataset['name']) ? $ckan_url . $dataset['name'] : '---',
                            'Local'
//                            $dataset['title'],
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

        fclose($csv_log_file);
    }

    /**
     * @param $topicTitle
     */

    public function cleanUpTagsByTopic(
        $topicTitle
    ) {
        $start = 0;
        $limit = 100;
        while (true) {
            $datasets = $this->tryPackageSearch('(groups:' . $topicTitle . ')', '', $limit, $start);

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
                    $this->
                    removeTagsAndGroupsFromDatasets(
                        [$dataset['name']], $group['name'], 'non-existing-tag&&', $topicTitle);
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
    public function removeTagsAndGroupsFromDatasets(
        $datasetNames,
        $group_to_remove,
        $tags_to_remove,
        $basename
    ) {
        $this->logOutput = '';

//        Getting Group object
        if ('*' !== $group_to_remove && !($group_to_remove = $this->findGroup($group_to_remove))) {
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

            if ('*' == $group_to_remove) {
//                remove all groups and tags
                $groupSize = sizeof($dataset['groups']);
                $dataset['groups'] = [];
                foreach ($dataset['extras'] as $extra) {
                    if (false !== strpos($extra['key'], '__category_tag_')) {
                        $extra['value'] = null;
                    }
                }
                if ($groupSize) {
                    $this->say(str_pad('-ALL GROUPS AND TAGS', 8, ' . ', STR_PAD_LEFT));
                } else {
                    $this->say(str_pad('NO GROUPS HERE', 8, ' . ', STR_PAD_LEFT));
                }
                continue;
            }

//            Removing group from dataset found
            if (!$tags_to_remove) {
//            remove group only if tags list is empty, otherwise remove only TAGS of this group
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

            $extras = $dataset['extras'];

            $newTags = [];
            $dataset['extras'] = [];


//            removing extra tags for the removed group
            $category_tag = '__category_tag_' . $group_to_remove['id'];

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
                $formattedTags = '["' . join('","', $newTags) . '"]';
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

            $this->tryPackageUpdate($dataset);
            $this->say(str_pad('SUCCESS', 10, ' . ', STR_PAD_LEFT));
        }

        file_put_contents($this->resultsDir . '/' . $basename . '_remove.log', $this->logOutput, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array $tagsArray
     *
     * @return array
     */
    private function cleanupTags(
        $tagsArray
    ) {
        $return = [];
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
    public function updateExtraFields($datasetNames, $extraField, $oldValue, $newValue, $basename)
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

            $extras = $dataset['extras'];
            $dataset['extras'] = [];

            $updated = false;
            $value = '';
            foreach ($extras as $extra) {
                if ($extraField == $extra['key'] && $oldValue == $extra['value']) {
                    $extra['value'] = $newValue;
                    $updated = true;
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
                $this->tryPackageUpdate($dataset);
                $this->say('SUCCESS');
            } catch (\Exception $ex) {
                $this->say('ERROR: CHECK LOG');
                file_put_contents($this->resultsDir . '/error.log', $ex->getMessage() . PHP_EOL,
                    FILE_APPEND | LOCK_EX);
            }
        }

        file_put_contents(
            $this->resultsDir . '/' . $basename . '_update_extra_fields.log.csv', $this->logOutput,
            FILE_APPEND | LOCK_EX);
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
    public function assignGroupsAndCategoriesToDatasets(
        $datasetNames,
        $group,
        $categories = null,
        $basename
    ) {
        static $counter = 0;
        $this->logOutput = '';

        $group_obj = $this->findGroup($group);
        $this->expectedFieldsDiff = [
            'groups'
        ];

        foreach ($datasetNames as $datasetName) {

            $datasetName = strtolower($datasetName);

            echo str_pad(++$counter, 6);
//            $this->say(str_pad($datasetName, 100, ' . '), '');
            $this->say($datasetName . ',', '');

            if (!$group_obj) {
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
                'name' => $group_obj['name'],
            ];

            if (is_array($categories) || strlen($categories)) {
                $extras = $dataset['extras'];
                $dataset['extras'] = [];

                foreach ($extras as $extra) {
                    if ('__category_tag_' . $group_obj['id'] == $extra['key']) {
                        $oldCategories = trim($extra['value'], '"[], ');
                        $oldCategories = explode('","', $oldCategories);
                        $categories = array_merge($categories, $oldCategories);
                        $categories = $this->cleanupTags($categories);
                        continue;
                    }
                    $dataset['extras'][] = $extra;
                }

                if ($categories) {
                    $formattedCategories = '["' . join('","', $categories) . '"]';
                    $dataset['extras'][] = [
                        'key'   => '__category_tag_' . $group_obj['id'],
                        'value' => $formattedCategories,
                    ];
                }
            }

            try {
                $this->tryPackageUpdate($dataset);
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
            $this->resultsDir . '/' . $basename . '_tags.log.csv', $this->logOutput, FILE_APPEND | LOCK_EX);
        $this->logOutput = '';
    }

    /**
     * @param mixed $tree
     * @param string|bool $start
     * @param int|bool $limit
     */
    public function getRedirectList(
        $tree,
        $start = false,
        $limit = 1
    ) {
        $countOfRootOrganizations = sizeof($tree);
        $counter = 0;
        $processed = 0;
        foreach ($tree as $rootOrganization) {
            $counter++;

            if (!$start || $start == $rootOrganization['id']) {
                $start = false;
                echo "::Processing Root Organization #$counter of $countOfRootOrganizations::" . PHP_EOL;
                $this->getRedirectListByOrganization($rootOrganization);
            }

            if (isset($rootOrganization['children'])) {
                foreach ($rootOrganization['children'] as $subAgency) {
                    if (!$start || $start == $subAgency['id']) {
                        $this->getRedirectListByOrganization($subAgency);
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
     * @return bool
     */
    private function getRedirectListByOrganization(
        $organization
    ) {
        $return = [];

        if (ERROR_REPORTING == E_ALL) {
            echo PHP_EOL . "Getting member list of: " . $organization['id'] . PHP_EOL;
        }

        $list = $this->tryMemberList($organization['id']);

        if (!$list) {
            return;
        }

        $counter = 0;
        $size = sizeof($list);
        foreach ($list as $package) {
            if (!(++$counter % 500)) {
                echo str_pad($counter, 7, ' ', STR_PAD_LEFT) . ' / ' . $size . PHP_EOL;
            }
            $dataset = $this->tryPackageShow($package[0]);
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

            $newDataset = $this->tryFindNewDatasetByIdentifier($package[0]);
            if (!$newDataset) {
                $newDataset = $this->tryFindNewDatasetByTitle(trim($dataset['title']));
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
            $fp_csv = fopen(($filename = $this->resultsDir . '/' . $organization['id'] . '.csv'), 'w');

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
    private function tryFindNewDatasetByIdentifier(
        $identifier,
        $try = 3
    ) {
        $dataset = false;
        while ($try) {
            try {
                $dataset = $this->Ckan->package_search(
                    '', 'identifier:' . $identifier, 1, 0);
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
    private function tryFindNewDatasetByTitle(
        $title,
        $try = 3
    ) {
        $dataset = false;
        $title = $this->escapeSolrValue($title);
        while ($try) {
            try {
                $ckanResult = $this->Ckan->package_search(
                    '', 'title:' . $title, 50, 0);
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
     * @param mixed $tree
     * @param string|bool $start
     * @param int|bool $limit
     */
    public function getPrivateList(
        $tree,
        $start = false,
        $limit = 1
    ) {
        $this->return = [];

        $countOfRootOrganizations = sizeof($tree);
        $counter = 0;
        $processed = 0;
        foreach ($tree as $rootOrganization) {
            $counter++;

            if (!$start || $start == $rootOrganization['id']) {
                $start = false;
                echo "::Processing Root Organization #$counter of $countOfRootOrganizations::" . PHP_EOL;
                $this->getPrivateListByOrganization($rootOrganization);
            }

            if (isset($rootOrganization['children'])) {
                foreach ($rootOrganization['children'] as $subAgency) {
                    if (!$start || $start == $subAgency['id']) {
                        $this->getPrivateListByOrganization($subAgency);
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
     * @return bool
     */
    private function getPrivateListByOrganization(
        $organization
    ) {
        if (ERROR_REPORTING == E_ALL) {
            echo PHP_EOL . "Getting member list of: " . $organization['id'] . PHP_EOL;
        }

        $list = $this->tryMemberList($organization['id']);

        if (!$list) {
            return;
        }

        foreach ($list as $package) {
            $dataset = $this->tryPackageShow($package[0]);
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
            file_put_contents($this->resultsDir . '/' . $organization['id'] . '_PRIVATE_ONLY.json', $json);
        }
    }

    /**
     * @param $socrata_list
     */
    public function getSocrataPairs(
        $socrata_list
    ) {
        $socrata_redirects = ['from,to'];
        $ckan_rename_legacy = ['from,to'];
        $ckan_rename_public = ['from,to'];
        $ckan_redirects = ['from,to'];
        $socrata_txt_log = ['socrata_id,ckan_id,status,private,public'];

        $notFound = $publicFound = $privateOnly = $alreadyLegacy = $mustRename = $socrataNotFound = 0;

        $ckan_url = 'https://catalog.data.gov/dataset/';

        $SocrataApi = new ExploreApi('http://explore.data.gov/api/');

        $size = sizeof($socrata_list);
        $counter = 0;
        foreach ($socrata_list as $socrata_line) {
            echo ++$counter . " / $size $socrata_line" . PHP_EOL;
            if (!strlen($socrata_line = trim($socrata_line))) {
                continue;
            }
            list($socrata_id, $ckan_id) = explode(': ', $socrata_line);
            $socrata_id = trim($socrata_id);
            $ckan_id = trim($ckan_id);

            $socrataDatasetTitle = $this->tryFindSocrataTitle($SocrataApi, $socrata_id);

            if (!$socrataDatasetTitle) {
                $socrataNotFound++;
                echo 'socrata not found' . PHP_EOL;
                $socrata_txt_log [] = join(
                    ',', [$socrata_id, $ckan_id, 'socrata not found', '-', '-']);
                continue;
            }

            /**
             * Try to find dataset with same id
             */
            $dataset = $this->tryPackageShow($ckan_id);

            if (!$dataset) {
                /**
                 * Let's try to get original explore.data.gov dataset title
                 * and search public dataset with same title
                 */
                $public_dataset = $this->tryFindNewDatasetByTitle($socrataDatasetTitle);

                if ($public_dataset) {
                    $publicFound++;
                    echo 'ckan public found by socrata title' . PHP_EOL;
                    $socrata_txt_log [] = join(
                        ',', [
                        $socrata_id,
                        $ckan_id,
                        'ckan public found by socrata title',
                        '-',
                        $ckan_url . $public_dataset['name']
                    ]);
                    $socrata_redirects [] = join(',', [$socrata_id, $ckan_url . $public_dataset['name']]);
//                    $ckan_redirects []    = join(',', [$ckan_url . $ckan_id, $ckan_url . $public_dataset['name']]);
                    continue;
                }

//                else
                $notFound++;
                echo 'ckan nothing found' . PHP_EOL;
                $socrata_txt_log [] = join(
                    ',', [$socrata_id, $ckan_id, 'ckan nothing found', '-', '-']);
                continue;
            }

            /**
             * if PUBLIC
             */
            if (!$dataset['private']) {
                $publicFound++;
                echo 'ckan public found by id' . PHP_EOL;
                $socrata_txt_log [] = join(
                    ',', [$socrata_id, $ckan_id, 'ckan public found by id', '-', $ckan_url . $dataset['name']]);
                $socrata_redirects [] = join(',', [$socrata_id, $ckan_url . $dataset['name']]);
//                $ckan_redirects []    = join(',', [$ckan_url . $ckan_id, $ckan_url . $dataset['name']]);
                continue;
            }

            /**
             * Dataset is private, let's try to find his public brother
             */
            $publicDataset = $this->tryFindNewDatasetByTitle($dataset['title']);

            if (!$publicDataset) {
                echo $dataset['title'] . ' :: not found' . PHP_EOL;
                /**
                 * Let's try to get original explore.data.gov dataset title
                 * and search public dataset with same title
                 */

                $public_dataset = $this->tryFindNewDatasetByTitle($socrataDatasetTitle);

                if ($public_dataset) {
                    $publicFound++;
                    echo 'ckan public found by socrata title' . PHP_EOL;
                    $socrata_txt_log [] = join(
                        ',', [
                        $socrata_id,
                        $ckan_id,
                        'ckan public found by socrata title',
                        '-',
                        $ckan_url . $public_dataset['name']
                    ]);
                    $socrata_redirects [] = join(',', [$socrata_id, $ckan_url . $public_dataset['name']]);
//                    $ckan_redirects []    = join(',', [$ckan_url . $ckan_id, $ckan_url . $public_dataset['name']]);
                    continue;
                }

//                else
                $privateOnly++;
                echo 'ckan private only' . PHP_EOL;
                $socrata_txt_log [] = join(
                    ',', [$socrata_id, $ckan_id, 'ckan private only', $ckan_url . $dataset['name'], '-']);
                continue;
            }

            /**
             * Public dataset found, but private dataset already has _legacy postfix
             */
            if (strpos($dataset['name'], '_legacy')) {
                $alreadyLegacy++;
                echo 'ckan private already _legacy; public brother ok; no renaming' . PHP_EOL;
                $socrata_txt_log [] = join(
                    ',', [
                    $socrata_id,
                    $ckan_id,
                    'ckan private already _legacy; public brother ok; no renaming',
                    $ckan_url . $dataset['name'],
                    $ckan_url . $publicDataset['name']
                ]);
                $socrata_redirects [] = join(',', [$socrata_id, $ckan_url . $publicDataset['name']]);
//                $ckan_redirects []    = join(',', [$ckan_url . $ckan_id, $ckan_url . $publicDataset['name']]);
                continue;
            }

            /**
             * Public dataset found, let's rename
             */
            $mustRename++;
            echo 'ckan private and public found; need to rename' . PHP_EOL;
            $socrata_txt_log [] = join(
                ',', [
                $socrata_id,
                $ckan_id,
                'ckan private and public found; need to rename',
                $ckan_url . $dataset['name'],
                $ckan_url . $publicDataset['name']
            ]);
            $socrata_redirects [] = join(',', [$socrata_id, $ckan_url . $dataset['name']]);
//            $ckan_redirects []    = join(',', [$ckan_url . $ckan_id, $ckan_url . $dataset['name']]);
            $ckan_redirects [] = join(
                ',', [$ckan_url . $publicDataset['name'], $ckan_url . $dataset['name']]);
            $ckan_rename_legacy[] = join(
                ',', [$ckan_url . $dataset['name'], $ckan_url . $dataset['name'] . '_legacy']);
            $ckan_rename_public[] = join(
                ',', [$ckan_url . $publicDataset['name'], $ckan_url . $dataset['name']]);
            continue;
        }

        $socrata_txt_log = join("\n", $socrata_txt_log);
        file_put_contents($this->resultsDir . '/socrata_txt_log.csv', $socrata_txt_log);

        $ckan_rename_legacy = join("\n", $ckan_rename_legacy);
        file_put_contents($this->resultsDir . '/rename_private_datasets_legacy.csv', $ckan_rename_legacy);

        $ckan_rename_public = join("\n", $ckan_rename_public);
        file_put_contents($this->resultsDir . '/rename_public_datasets.csv', $ckan_rename_public);

        $ckan_redirects = join("\n", $ckan_redirects);
        file_put_contents($this->resultsDir . '/redirects_ckan.csv', $ckan_redirects);

        $socrata_redirects = join("\n", $socrata_redirects);
        file_put_contents($this->resultsDir . '/redirects_socrata.csv', $socrata_redirects);

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
    private function tryFindSocrataTitle(
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
                $try = 0;
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
    public function breakdownByGroup(
        Writer $csv_agencies,
        Writer $csv_categories
    ) {
        $offset = 0;
        $per_page = 50;
        $counter = 0;

        $organizations = [];
        $tags = [];

        $search_query = '(' . GROUP_TO_EXPORT . ') AND (dataset_type:dataset)';
//        $group = $this->findGroup(GROUP_TO_EXPORT);

        while (true) {
            $datasets = $this->tryPackageSearch($search_query, '', $per_page, $offset);

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
     * @param $limit
     * @param $start
     */
    public function orphanedTagsSeek(
        $limit,
        $start
    ) {
        $counter = 0;
        $offset = $start;
        $finish = $start + $limit;
        $per_page = 50;

        $groups = $this->tryGetGroupsArray();

        while (true) {
            $datasets = $this->tryPackageSearch('(dataset_type:dataset)', '', $per_page, $offset);

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
                    $group = isset($groups[$group_id]) ? $groups[$group_id] : $group_id;

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
                        file_put_contents($this->resultsDir . '/orphaned_tags.csv', $out, FILE_APPEND);
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
    private function tryGetGroupsArray()
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
    public function groupsArray()
    {
        return $this->tryGetGroupsArray();
    }

    /**
     * @param             $category
     * @param CkanManager $CkanManagerProduction
     */
    public function checkGroupAgainstProd(
        $category,
        self $CkanManagerProduction
    ) {
        $csv = new Writer($this->resultsDir . '/' . $category . date('_Ymd-His') . '.csv');
        $csv->writeRow(
            ['Staging dataset name', 'Staging Source', 'Prod exists', 'Prod has ' . $category, 'Prod Source']);

        $ckan_query = '((groups:' . $category . ') + dataset_type:dataset)';
        $start = 0;
        $per_page = 20;
        while (true) {
            $packages = $this->tryPackageSearch($ckan_query, '', $per_page, $start);
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

                $prod_package = $CkanManagerProduction->tryPackageShow($package['name']);
                $exists = $prod_package ? 'EXISTS' : 'NOT FOUND';
                $prod_category_found = '';
                $prod_resource_type = '';

                if ($prod_package) {
                    $prod_category_found = 'FALSE';

                    if (isset($prod_package['groups']) && sizeof($prod_package['groups']) && strpos(
                            json_encode($prod_package['groups']),
                            $category
                        )
                    ) {
                        $prod_category_found = 'HAS';
                    }

                    if (is_array($prod_package['extras']) && sizeof($prod_package['extras']) && (strpos(json_encode($prod_package['extras']),
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
                    [$package['name'], $resource_type, $exists, $prod_category_found, $prod_resource_type]);
            }

            $start += $per_page;
        }
    }
}
