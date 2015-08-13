<?php
/**
 * Created by IntelliJ IDEA.
 * User: alexandr.perfilov
 * Date: 3/31/15
 * Time: 4:38 PM
 */

namespace CKAN\Manager;

use CKAN\Exceptions\NotFoundHttpException;
use Prophecy\Argument;

/**
 * Class CkanManagerTest
 * @package CKAN\Manager
 */
class CkanManagerTest extends \BaseTestCase
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
        ];
    }

    /**
     * ->tryPackageSearch() with results
     */
    public function testTryPackageSearchWithResults()
    {
        $CkanClient = $this->prophesize('CKAN\Core\CkanClient');
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
        $CkanClient = $this->prophesize('CKAN\Core\CkanClient');
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
        $CkanClient = $this->prophesize('CKAN\Core\CkanClient');
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
        $CkanClient = $this->prophesize('CKAN\Core\CkanClient');
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
        $CkanClient = $this->prophesize('CKAN\Core\CkanClient');
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
        $CkanClient = $this->prophesize('CKAN\Core\CkanClient');
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
     */
//    public function testCheckDatasetConsistencyFail()
//    {
//        $dataset = $this->mockDataset;
//        $dataset['title'] .= ' FAIL';
//        $CkanClient = $this->prophesize('CKAN\Core\CkanClient');
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
