<?php
/**
 * Created by IntelliJ IDEA.
 * User: alexandr.perfilov
 * Date: 3/31/15
 * Time: 4:38 PM
 */

namespace CKAN\Manager;

use CKAN\NotFoundHttpException;
use Prophecy\Argument;

/**
 * Class CkanManagerTest
 * @package CKAN\Manager
 */
class CkanManagerTest extends \PHPUnit\Framework\TestCase
{


    /**
     * @var CkanManager
     */
    private $CkanManager;

    /**
     * @var array
     */
    private $mockDataset;

    /**
     *
     */
    public function setUp()
    {
        $this->CkanManager = new CkanManager('mock://dummy_api_url.gov/');
        $this->mockDataset = [
            'id'        => 'dataset-mock-id',
            'name'      => 'dataset-mock-name',
            'type'      => 'dataset',
            'title'     => 'Some Interesting Mock Dataset 2015',
            'resources' => [1, 2, 3, 5, 4],
            'state'     => 'active',
        ];
    }

    /**
     * ->tryPackageSearch() with results
     */
    public function testTryPackageSearchWithResults()
    {
        $CkanClient = $this->prophesize('CKAN\CkanClient');
        $CkanClient->package_search('testorg', '', 100, 0)->willReturn(
            json_encode([
                'help'    => 'some text',
                'success' => true,
                'result'  => [
                    'count'   => 5,
                    'results' => [1, 2, 3, 5, 4]
                ]
            ])
        );

        $this->CkanManager->setCkan($CkanClient->reveal());

        $datasets = $this->CkanManager->tryPackageSearch('testorg');
        $this->assertEquals([1, 2, 3, 5, 4], $datasets);
    }

    /**
     * ->tryPackageSearch() without results
     */
    public function testTryPackageSearchWithNoResults()
    {
        $CkanClient = $this->prophesize('CKAN\CkanClient');
        $CkanClient->package_search('notfound', '', 100, 0)->willThrow(new NotFoundHttpException());

        $this->CkanManager->setCkan($CkanClient->reveal());

        $this->expectOutputString("Nothing found" . PHP_EOL);

        $datasets = $this->CkanManager->tryPackageSearch('notfound');
        $this->assertFalse($datasets);
    }

    /**
     *
     */
    public function testTryPackageShow()
    {
        $CkanClient = $this->prophesize('CKAN\CkanClient');
        $CkanClient->package_show($this->mockDataset['name'])->willReturn(
            json_encode([
                'help'    => 'some text',
                'success' => true,
                'result'  => $this->mockDataset
            ])
        );

        $this->CkanManager->setCkan($CkanClient->reveal());

        $package = $this->CkanManager->tryPackageShow($this->mockDataset['name']);
        $this->assertEquals($this->mockDataset['id'], $package['id']);
        $this->assertEquals($this->mockDataset['title'], $package['title']);
    }

    /**
     *
     */
    public function testTryPackageShowNoResults()
    {
        $CkanClient = $this->prophesize('CKAN\CkanClient');
        $CkanClient->package_show('dataset-not-found')->willThrow(new NotFoundHttpException());

        $this->CkanManager->setCkan($CkanClient->reveal());

        $package = $this->CkanManager->tryPackageShow('dataset-not-found');
        $this->assertFalse($package);
    }

    /**
     *
     */
    public function testTryPackageUpdate()
    {
        $CkanClient = $this->prophesize('CKAN\CkanClient');
        $CkanClient->package_update($this->mockDataset)->willReturn(true);
        $CkanClient->package_show($this->mockDataset['name'])->willReturn(
            json_encode([
                'help'    => 'some text',
                'success' => true,
                'result'  => $this->mockDataset,
            ])
        );

        $this->CkanManager->setCkan($CkanClient->reveal());

        $package = $this->CkanManager->tryPackageUpdate($this->mockDataset);
        $this->assertTrue($package);
    }

    /**
     */
    public function testCheckDatasetConsistency()
    {
        $CkanClient = $this->prophesize('CKAN\CkanClient');
        $CkanClient->package_update($this->mockDataset)->willReturn(true);
        $CkanClient->package_show($this->mockDataset['name'])->willReturn(
            json_encode([
                'help'    => 'some text',
                'success' => true,
                'result'  => $this->mockDataset,
            ])
        );

        $this->CkanManager->setCkan($CkanClient->reveal());

        $check = $this->CkanManager->checkDatasetConsistency($this->mockDataset);
        $this->assertTrue($check);
    }

    /**
     *  add tag to dataset
     */
    public function testAssignGroupsAndCategoriesToDatasets()
    {
        $groups = [
            ['name'=>'group1', 'id'=>'gid1'],
            ['name'=>'group2', 'id'=>'gid2'],
        ];
        $tag1 = [
            'key' => '__category_tag_gid1',
            'value' => '["tag1"]',
        ];
        $tag2 = [
            'key' => '__category_tag_gid2',
            'value' => '["tag2"]',
        ];
        $extras = [$tag1, $tag2];
        $dataset_with_tags_groups = $this->mockDataset;
        $dataset_with_tags_groups['groups'] = $groups;
        $dataset_with_tags_groups['extras'] = $extras;

        $tag1_after_update = [
            'key' => '__category_tag_gid1',
            'value' => '["new tag","tag1"]',
        ];
        $extras_after_update = [$tag2, $tag1_after_update];
        $dataset_after_update = $dataset_with_tags_groups;
        $dataset_after_update['extras'] = $extras_after_update;

        $CkanClient = $this->prophesize('CKAN\CkanClient');
        $CkanClient->package_show($this->mockDataset['name'])->willReturn(
            json_encode([
                'help'    => 'some text',
                'success' => true,
                'result'  => $dataset_with_tags_groups
            ])
        );
        $CkanClient->group_list(true)->willReturn(
            json_encode([
                'help'    => 'some text',
                'success' => true,
                'result'  => $groups,
            ])
        );

        $filePutContentsWrapper = $this->prophesize('CKAN\Manager\Adapters\FilePutContentsWrapper');
        $filePutContentsWrapper->filePutContents(Argument::any(),Argument::any(),Argument::any())->willReturn(true);

        $this->CkanManager->setCkan($CkanClient->reveal());
        $this->CkanManager->setFilePutContentsWrapper($filePutContentsWrapper->reveal());

        $package = $this->CkanManager->assignGroupsAndCategoriesToDatasets(['dataset-mock-name'], 'group1', 'some filename', ['new tag']);

        $CkanClient->package_update($dataset_after_update)->shouldBeCalled();
    }

    /**
     *  remove certain tag from dataset
     */
    public function testRemoveTagsAndGroupsFromDatasets()
    {
        $groups = [
            ['name'=>'group1', 'id'=>'gid1'],
            ['name'=>'group2', 'id'=>'gid2'],
        ];
        $tag1 = [
            'key' => '__category_tag_gid1',
            'value' => '["tag1"]',
        ];
        $tag2 = [
            'key' => '__category_tag_gid2',
            'value' => '["tag2"]',
        ];
        $extras = [$tag1, $tag2];
        $dataset_with_tags_groups = $this->mockDataset;
        $dataset_with_tags_groups['groups'] = $groups;
        $dataset_with_tags_groups['extras'] = $extras;

        $tag1_after_update = [
            'key' => '__category_tag_gid1',
            'value' => null,
        ];
        $extras_after_update = [$tag2, $tag1_after_update];
        $dataset_after_update = $dataset_with_tags_groups;
        $dataset_after_update['extras'] = $extras_after_update;

        $CkanClient = $this->prophesize('CKAN\CkanClient');
        $CkanClient->package_show($this->mockDataset['name'])->willReturn(
            json_encode([
                'help'    => 'some text',
                'success' => true,
                'result'  => $dataset_with_tags_groups
            ])
        );
        $CkanClient->group_list(true)->willReturn(
            json_encode([
                'help'    => 'some text',
                'success' => true,
                'result'  => $groups,
            ])
        );

        $filePutContentsWrapper = $this->prophesize('CKAN\Manager\Adapters\FilePutContentsWrapper');
        $filePutContentsWrapper->filePutContents(Argument::any(),Argument::any(),Argument::any())->willReturn(true);

        $this->CkanManager->setCkan($CkanClient->reveal());
        $this->CkanManager->setFilePutContentsWrapper($filePutContentsWrapper->reveal());

        $package = $this->CkanManager->removeTagsAndGroupsFromDatasets(['dataset-mock-name'], 'group1', 'tag1', 'some filename');

        $CkanClient->package_update($dataset_after_update)->shouldBeCalled();
    }

    /**
     */
//    public function testCheckDatasetConsistencyFail()
//    {
//        $dataset = $this->mockDataset;
//        $dataset['title'] .= ' FAIL';
//        $CkanClient = $this->prophesize('CKAN\CkanClient');
//        $CkanClient->package_update($this->mockDataset)->willReturn(true);
//        $CkanClient->package_show($this->mockDataset['name'])->willReturn(
//            json_encode([
//                'help' => 'some text',
//                'success' => true,
//                'result' => $dataset,
//            ])
//        );
//
//        $this->CkanManager->setCkan($CkanClient->reveal());
//
//        $check = $this->CkanManager->checkDatasetConsistency($this->mockDataset);
//        $this->assertFalse($check);
//    }

}
