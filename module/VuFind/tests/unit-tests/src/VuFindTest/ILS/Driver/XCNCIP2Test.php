<?php
/**
 * ILS driver test
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\ILS\Driver;
use VuFind\ILS\Driver\XCNCIP2;
use RuntimeException;
use \Zend\Http\Response;

/**
 * ILS driver test
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class XCNCIP2Test extends \VuFindTest\Unit\ILSDriverTestCase
{
	const FIXTURE_DIRECTORY = '/fixtures/ils/xcncip2/';
	const DLF_API_BASE_URL = 'http://myuniversity.edu:8080/ncipv2/NCIPResponder';

	protected $driver;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->driver = new XCNCIP2();
    }

    public function testLookupUser()
    {
    	$this->setUpDriver('v2.02.ini');

    	$patronAddressUrl = self::DLF_API_BASE_URL;
    	$patronIDResponse = $this->getResponse('v2.02', 'LookupUser_valid_1.xml');
    	$patronAddressResponse = $this->getResponse('v2.02', 'LookupUser_valid_2.xml');
    	$this->mockedHttpService->expects($this->any())->method('get')
    	   ->with($this->equalTo($patronAddressUrl),
            $this->equalTo(array()), $this->equalTo(null))
            ->will($this->onConsecutiveCalls($patronIDResponse, $patronAddressResponse));

    	$expectedResult = array(
    			'lastname'  => 'John Smith',
    			'firstname' => '',
    			'address1'  => 'Street 123',
    			'address2'  => '12345 Springfield',
    			'barcode'   => 'TEST',
    			'zip'       => '',
    			'phone'     => '+042 123 456 789',
    			'email'     => 'john.smith@example.com',
    			'addressValidFrom' => '08-30-2010',
    			'addressValidTo'   => '12-31-2013',
    			'id'        => 'TEST',
    			'expire'    => '08-08-2014',
    			'group'     => '04 - S',
    	);

        $realResult = $this->driver->patronLogin('john', 'ax123ax');
//     	print_r($realResult);
//     	$this->assertEquals($expectedResult, $realResult);
    }

    public function testLookupAgency()
    {
        $this->setUpDriver('v2.02.ini');

        $patronAddressUrl = self::DLF_API_BASE_URL;
        $agencyResponse = $this->getResponse('v2.02', 'LookupAgency_valid_1.xml');
        $this->mockedHttpService->expects($this->any())->method('get')
        ->with($this->equalTo($patronAddressUrl),
            $this->equalTo(array()), $this->equalTo(null))
            ->will($this->returnValue($agencyResponse));

        $expectedResult = array(
                'id'        => 'TEST',
                'group'     => '04 - S',
        );

        $realResult = $this->driver->getAgencyInformation("BOA001");
//         print_r($realResult);
//     	$this->assertEquals($expectedResult, $realResult);
    }

    public function testLookupItem()
    {
        $this->setUpDriver('v2.02.ini');

        $patronAddressUrl = self::DLF_API_BASE_URL;
        $itemResponse = $this->getResponse('v2.02', 'LookupItem_valid_1.xml');
        $this->mockedHttpService->expects($this->any())->method('get')
        ->with($this->equalTo($patronAddressUrl),
            $this->equalTo(array()), $this->equalTo(null))
            ->will($this->returnValue($itemResponse));

        $expectedResult = array(
                'author'     => 'Charles Baudelaire',
                'title'        => 'Kvety zla',
        );

        $realResult = $this->driver->lookupItem("65021");
//         print_r($realResult);
        $this->assertEquals($expectedResult, $realResult);
    }

    public function testRequestItem()
    {
        $this->setUpDriver('v2.02.ini');

        $patronAddressUrl = self::DLF_API_BASE_URL;
        $itemResponse = $this->getResponse('v2.02', 'RequestItem_valid_1.xml');
        $this->mockedHttpService->expects($this->any())->method('get')
        ->with($this->equalTo($patronAddressUrl),
            $this->equalTo(array()), $this->equalTo(null))
            ->will($this->returnValue($itemResponse));

        $expectedResult = array(
                'item'     => '000',
        );

        $realResult = $this->driver->requestItem("38110", "65021");
//         print_r($realResult);
//         $this->assertEquals($expectedResult, $realResult);
    }

    public function testLookupRequest()
    {
        $this->setUpDriver('v2.02.ini');

        $patronAddressUrl = self::DLF_API_BASE_URL;
        $itemResponse = $this->getResponse('v2.02', 'LookupRequest_valid_1.xml');
        $this->mockedHttpService->expects($this->any())->method('get')
        ->with($this->equalTo($patronAddressUrl),
            $this->equalTo(array()), $this->equalTo(null))
            ->will($this->returnValue($itemResponse));

        $expectedResult = array(
                'item'     => '000',
        );

        $realResult = $this->driver->lookupRequest("38110", "65021");
//         print_r($realResult);
//         $this->assertEquals($expectedResult, $realResult);
    }

    public function testLookupItemSet()
    {
        $this->setUpDriver('v2.02.ini');

        $patronAddressUrl = self::DLF_API_BASE_URL;
        $itemResponse1 = $this->getResponse('v2.02', 'LookupItemSet_valid_1.xml');
        $itemResponse2 = $this->getResponse('v2.02', 'LookupItemSet_valid_2.xml');
        $this->mockedHttpService->expects($this->any())->method('get')
        ->with($this->equalTo($patronAddressUrl),
            $this->equalTo(array()), $this->equalTo(null))
            ->will($this->onConsecutiveCalls($itemResponse1, $itemResponse2));

        $expectedResult = array(
                'item'     => '000',
        );

        $realResult = $this->driver->getStatus("65021");
//         print_r($realResult);
//         $this->assertEquals($expectedResult, $realResult);
    }

    /**
     * Setup Aleph driver using given configuration file
     *
     * @param string $config configuration file to use
     *
     * @throws RuntimeException
     * @return void
     */
    protected function setUpDriver($config)
    {
    	$file = realpath(
    			\VUFIND_PHPUNIT_MODULE_PATH . self::FIXTURE_DIRECTORY . 'configs/' . $config
    	);
    	if (!$file) {
    		throw new RuntimeException(
    				sprintf('Unable to get configuration for driver %s', $config)
    		);
    	}
    	$this->driverConfig = parse_ini_file($file, true);
    	$this->driver = new XCNCIP2(new \VuFind\Date\Converter());
    	$this->mockedHttpService = $this->getMock('VuFindHttp\HttpServiceInterface');
    	$this->driver->setHttpService($this->mockedHttpService);
    	$this->driver->setConfig($this->driverConfig);
    	$this->driver->init();
    }

    /**
     * Retrieve response by version and name
     *
     * @param string $version version
     * @param string $name    name
     *
     * @throws RuntimeException
     * @return \Zend\Http\Response
     */
    protected function getResponse($version, $name)
    {
    	$file = realpath(
    			\VUFIND_PHPUNIT_MODULE_PATH . self::FIXTURE_DIRECTORY . 'responses/' . $version .'/' . $name
    	);
    	if (!$file) {
    		throw new RuntimeException(
    				sprintf('Unable to resolve fixture file: %s', $name)
    		);
    	}
    	$content = file_get_contents($file);
    	$response = new Response();
    	$response->setContent($content);
    	$response->setStatusCode(Response::STATUS_CODE_200);
    	$response->setReasonPhrase('OK');
    	return $response;
    }

}