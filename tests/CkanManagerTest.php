<?php
/**
 * Created by IntelliJ IDEA.
 * User: alexandr.perfilov
 * Date: 3/31/15
 * Time: 4:38 PM
 */

namespace CKAN\Manager;

use CKAN\Exceptions\NotFoundHttpException;

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

    public function setUp()
    {
        $this->CkanManager = new CkanManager('mock://dummy_api_url.gov/');
    }

    public function testTryPackageSearchWithResults()
    {
        $CkanClient = $this->prophesize('CKAN\Core\CkanClient');
        $CkanClient->package_search('testorg', 100, 0, 'q')->willReturn(
            json_encode([
                'success' => true,
                'result' => [
                    'count' => 5,
                    'results' => [1, 2, 3, 5, 4]
                ]
            ])
        );

        $this->CkanManager->setCkan($CkanClient->reveal());

        $datasets = $this->CkanManager->tryPackageSearch('testorg');
        $this->assertEquals([1, 2, 3, 5, 4], $datasets);
    }

    public function testTryPackageSearchWithNoResults()
    {
        $CkanClient = $this->prophesize('CKAN\Core\CkanClient');
        $CkanClient->package_search('notfound', 100, 0, 'q')->willThrow(new NotFoundHttpException());

        $this->CkanManager->setCkan($CkanClient->reveal());

        $this->expectOutputString("Nothing found".PHP_EOL);

        $datasets = $this->CkanManager->tryPackageSearch('notfound');
        $this->assertEquals(false, $datasets);
    }
}
