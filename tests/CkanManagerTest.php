<?php
/**
 * Created by IntelliJ IDEA.
 * User: alexandr.perfilov
 * Date: 3/31/15
 * Time: 4:38 PM
 */

namespace CKAN\Manager;

//use CKAN\Core\CkanClient;


class CkanManagerTest extends \BaseTestCase {

    public function setUp()
    {
        $this->CkanManager = $this->getMockBuilder('CkanManager')
            ->disableOriginalConstructor()
            ->getMock();


        $CkanClient = $this->getMockBuilder('CkanClient')
            ->disableOriginalConstructor()
            ->getMock();

//        $this->testClass = 'CkanManager';
//        parent::setup();

//        $this->setProperty('Ckan', $CkanClient);
//        $this->CkanManager = $this->testClass;∂
    }

    public function testTryPackageSearch()
    {
//        $CkanManager = $this->getMockBuilder('CkanManager')
//            ->disableOriginalConstructor()
//            ->getMock();
//
//
//        $CkanClient = $this->getMockBuilder('CkanClient')
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        $CkanClient->method('package_search')
//            ->willReturn(json_encode([
//                'success'=> true,
//                'result' => [
//                    'count'=> 5,
//                    'results' => [1,2,3,4,5]
//                ]
//            ]));
//
//        $CkanManager->Ckan = $CkanClient;
//
//        // Create a stub for the SomeClass class.
//        $stub = $this->getMockBuilder('SomeClass')
//            ->getMock();
//
//        // Configure the stub.
//        $stub->method('doSomething')
//            ->willReturn('foo');


        $this->assertTrue(true);
    }
}
