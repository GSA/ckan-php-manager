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
     * @var string
     */
    public $log_output = '';

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
     * @param string $apiUrl
     * @param null   $apiKey
     */
    public function __construct($apiUrl, $apiKey = null)
    {
        $this->Ckan = new CkanClient($apiUrl, $apiKey);
    }

    /**
     * @param $search_list
     * @param $results_dir
     */
    public function search_terms($search_list, $results_dir)
    {
        $log_file = $results_dir . '/search_' . sizeof($search_list) . '_terms.csv';
        $fp       = fopen($log_file, 'w');

        $csv_header = [
            'Name of Dataset',
            'Agency',
            'Data.gov URL',
            'Keyword',
        ];

        fputcsv($fp, $csv_header);

        $ckan_url = 'https://catalog.data.gov/dataset/';
        $i        = 1;

//        http://catalog.data.gov/api/3/action/package_search?q=Asian+AND+dataset_type:dataset&sort=score+desc
        foreach ($search_list as $term) {
            echo $i++ . '/' . sizeof($search_list) . ' : ' . $term . PHP_EOL;
            if (!sizeof($term = trim($term))) {
                continue;
            }
            $ckan_query = $this->escapeSolrValue($term);

            $done     = false;
            $start    = 0;
            $per_page = 500;
            while (!$done) {
                $ckanResult = $this->Ckan->package_search($ckan_query, $per_page, $start);
                $start += $per_page;

                $ckanResult = json_decode($ckanResult, true); //  decode json as array
                $ckanResult = $ckanResult['result'];

                $count = $ckanResult['count'];
                echo $start . '/' . $count . PHP_EOL;
                if (!$count) {
                    $done = true;
                    continue;
                }

                if (sizeof($ckanResult['results'])) {
                    foreach ($ckanResult['results'] as $dataset) {
                        fputcsv(
                            $fp,
                            [
                                isset($dataset['title']) ? $dataset['title'] : '---',
                                isset($dataset['organization']) && isset($dataset['organization']['title']) ?
                                    $dataset['organization']['title'] : '---',
                                isset($dataset['organization']) && isset($dataset['name']) ?
                                    $ckan_url . $dataset['name'] : '---',
                                $term
                            ]
                        );
                    }
                } else {
                    echo 'no results: ' . $term . PHP_EOL;
                    continue;
                }
                if ($start > $count) {
                    $done = true;
                }
            }

        }

        fclose($fp);
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
        foreach ($terms as $term => $agency) {
            $page    = 0;
            $count   = 0;
            $results = [];
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
                $count      = $ckanResult['count'];
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

            $json = (json_encode($results, JSON_PRETTY_PRINT));
            file_put_contents($results_dir . '/' . $term . '.json', $json);
        }
        file_put_contents($results_dir . '/_' . PARENT_TERM . '.log', $this->log_output);
    }

    /**
     * Shorthand for sending output to stdout and appending to log buffer at the same time.
     */
    public
    function say(
        $output = '',
        $eol = PHP_EOL
    ) {
        echo $output . $eol;
        $this->log_output .= $output . $eol;
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
        $this->log_output = '';
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
        file_put_contents($results_dir . '/_' . PARENT_TERM . '.log', $this->log_output);
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
        $this->log_output = '';
        $page             = 0;
        $processed        = 0;
        $tag_template     = [
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

                $this->Ckan->package_update($dataset);
                $marked_true++;
            }

            echo "processed $processed ( $tag_name true = $marked_true, other = $marked_other) / " . $count . ' total ' . PHP_EOL;
            if ($count - $this->packageSearchPerPage < $start) {
                break;
            }
        }
        file_put_contents($results_dir . '/_' . $tag_name . '.log', $this->log_output);
    }

    /**
     * Ability to Add legacy tag to all dms datasets for an organization and make all those datasets private
     */
    public
    function tag_legacy_dms(
        $termsArray,
        $tag_name,
        $results_dir
    ) {
        $this->log_output = '';

//        get all datasets to update
        $datasets = $this->get_dms_public_datasets($termsArray);

        $count = sizeof($datasets);

        $log_file = PARENT_TERM . "_add_legacy_make_private.log";

//        update dataset tags list
        foreach ($datasets as $key => $dataset) {
            echo str_pad("$key / $count ", 10, ' ');


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

                try {
                    $this->Ckan->package_update($dataset);
                    $this->say(str_pad('OK', 7, ' '));
                } catch (\Exception $ex) {
                    $this->say(str_pad('ERROR', 7, ' '));
//                die(json_encode($dataset, JSON_PRETTY_PRINT) . PHP_EOL . $ex->getMessage() . PHP_EOL . PHP_EOL);
                    file_put_contents($results_dir . '/err.log', $ex->getMessage() . PHP_EOL, FILE_APPEND);
                }
            }

            file_put_contents($results_dir . '/' . $log_file, $this->log_output, FILE_APPEND);
            $this->log_output = '';
        }
    }

    /**
     * Use organization terms array to filter, use null to tag all datasets
     *
     * @param array $terms
     *
     * @return array
     */
    private
    function get_dms_public_datasets(
        $terms = null
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
                    $dms_datasets[] = $dataset;
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
        $this->log_output = '';
        $log_file = $basename . '_rename.log';

        $this->say(str_pad($datasetName, 100, ' . '), '');

        try {
            $ckanResult = $this->Ckan->package_show($datasetName);
        } catch (NotFoundHttpException $ex) {
            $this->say(str_pad('NOT FOUND', 10, ' . ', STR_PAD_LEFT));
            file_put_contents($results_dir . '/' . $log_file, $this->log_output, FILE_APPEND);
            $this->log_output = '';

            return;
        }

        $ckanResult = json_decode($ckanResult, true);
        $dataset    = $ckanResult['result'];

        $dataset['name'] = $newDatasetName;

        try {
            $this->Ckan->package_update($dataset);
            $this->say(str_pad('OK', 7, ' '));
        } catch (\Exception $ex) {
            $this->say(str_pad('ERROR', 7, ' '));
            file_put_contents($results_dir . '/err.log', $ex->getMessage() . PHP_EOL, FILE_APPEND);
        }

        file_put_contents($results_dir . '/' . $log_file, $this->log_output, FILE_APPEND);
        $this->log_output = '';
    }

    /**
     * Moves legacy datasets to parent organization
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
        $this->log_output = '';
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
                        $dataset    = json_decode($ckanResult, true);

                        $dataset = $dataset['result'];

                        // note the legacy organization as an extra field
                        $dataset['extras'][] = [
                            'key' => 'dms_publisher_organization',
                            'value' => $org_slug
                        ];

                        $dataset['owner_org'] = $department_id;

                        $this->Ckan->package_update($dataset);

                        $output = 'Moved ' . $current_record;
                        $this->say($output);
                    }
                } else {
                    $output = "Couldn't find backup file: " . $file_path;
                    $this->say($output);
                }
            }
        }

        file_put_contents($results_dir . '/' . $log_file, $this->log_output);

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

            $this->Ckan->package_update($freshDataset);

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
     * @param      $datasetNames
     * @param      $group
     * @param null $categories
     * @param      $results_dir
     * @param      $basename
     *
     * @throws \Exception
     */
    public
    function assign_groups_and_categories_to_datasets(
    $datasetNames,
        $group,
        $categories = null,
        $results_dir,
        $basename
    ) {
        $this->log_output = '';

        if (!($group = $this->findGroup($group))) {
            throw new \Exception('Group ' . $group . ' not found!' . PHP_EOL);
        }

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

            $dataset             = $dataset['result'];
            $dataset['groups'][] = [
                'name' => $group['name'],
            ];

            $extras            = $dataset['extras'];
            $dataset['extras'] = [];

            foreach ($extras as $extra) {
                if ('__category_tag_' . $group['id'] == $extra['key']) {
                    $oldCategories = trim($extra['value'], '"[]');
                    $oldCategories = explode('","', $oldCategories);
                    $categories    = array_merge($categories, $oldCategories);
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
            try {
                $this->Ckan->package_update($dataset);
                $this->say(str_pad('SUCCESS', 10, ' . ', STR_PAD_LEFT));
            } catch (\Exception $ex) {
                $this->say(str_pad('ERROR', 10, ' . ', STR_PAD_LEFT));
                file_put_contents($results_dir . '/error.log', $ex->getMessage(), FILE_APPEND | LOCK_EX);
            }


            file_put_contents(
                $results_dir . '/' . $basename . '_tags.log',
                $this->log_output,
                FILE_APPEND | LOCK_EX
            );
            $this->log_output = '';
        }
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
     *
     * @param $datasetNames
     * @param $group_to_remove
     * @param $results_dir
     * @param $basename
     *
     * @throws \Exception
     */
    public
    function remove_tags_and_groups_to_datasets(
        $datasetNames,
        $group_to_remove,
        $results_dir,
        $basename
    ) {
        $this->log_output     = '';

        if (!($group_to_remove = $this->findGroup($group_to_remove))) {
            throw new \Exception('Group ' . $group_to_remove . ' not found!' . PHP_EOL);
        }

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

//            removing extra tags of group
            $category_tag = '__category_tag_' . $group_to_remove['id'];

            $extras = [];
            foreach ($dataset['extras'] as $extra) {
                if ($extra['key'] !== $category_tag) {
                    $extras[] = $extra;
                } else {
                    $extra['value'] = null;
                    $extras[] = $extra;
                    $this->say(str_pad('-TAGS', 7, ' . ', STR_PAD_LEFT), '');
                }
            }

            $dataset['extras'] = $extras;

            $this->Ckan->package_update($dataset);
            $this->say(str_pad('SUCCESS', 10, ' . ', STR_PAD_LEFT));
        }

        file_put_contents($results_dir . '/' . $basename . '_remove.log', $this->log_output, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param mixed       $tree
     * @param string      $results_dir
     * @param string|bool $start
     * @param int|bool    $limit
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
     * @param mixed  $organization
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
     * @param string $id
     * @param int    $try
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
                    echo 'No success: ' . $id . PHP_EOL;
                    die();
                }

                if (!isset($list['result']) || !sizeof($list['result'])) {
                    echo 'No result: ' . $id . PHP_EOL;

                    return false;
                }

                $list = $list['result'];

                $try = 0;
            } catch (NotFoundHttpException $ex) {
                echo "Organization not found: " . $id . PHP_EOL;

                return false;
            } catch (\Exception $ex) {
                $try--;
                if (!$try) {
                    echo 'Too many attempts: ' . $id . PHP_EOL;

                    return false;
                }
            }
        }

        if (ERROR_REPORTING == E_ALL) {
            echo "Member list: " . sizeof($list) . ' records' . PHP_EOL;
        }

        return $list;
    }

    /**
     * @param string $id
     * @param int    $try
     *
     * @return bool|mixed
     */
    private
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

                    return false;
                }

                if (!isset($dataset['result']) || !sizeof($dataset['result'])) {
                    echo 'No result: ' . $id . PHP_EOL;

                    return false;
                }

                $dataset = $dataset['result'];

                $try = 0;
            } catch (NotFoundHttpException $ex) {
                echo "Dataset not found: " . $id . PHP_EOL;

                return false;
            } catch (\Exception $ex) {
                $try--;
                if (!$try) {
                    echo 'Too many attempts: ' . $id . PHP_EOL;

                    return false;
                }
            }
        }

        return $dataset;
    }

    /**
     * @param string $identifier
     * @param int    $try
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
     * @param int    $try
     *
     * @return bool|mixed
     */
    private
    function try_find_new_dataset_by_title(
        $title,
        $try = 3
    ) {
        $dataset = false;
        $title = $this->escapeSolrValue($title);
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
     * @param mixed       $tree
     * @param string      $results_dir
     * @param string|bool $start
     * @param int|bool    $limit
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
     * @param mixed  $organization
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
     * @param int        $try
     *
     * @return bool
     */
    private function try_find_socrata_title(ExploreApi $SocrataApi, $socrata_id, $try = 3)
    {
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
                if (!$try) {
                    echo 'Too many attempts: ' . $socrata_id . PHP_EOL;

                    return false;
                }
            }
        }

        return $title;
    }
}