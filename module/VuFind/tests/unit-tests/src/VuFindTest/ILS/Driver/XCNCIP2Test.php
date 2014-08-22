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
 * @author   Matus Sabik <sabik@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\ILS\Driver;
use VuFind\ILS\Driver\XCNCIP2;
use VuFindHttp\HttpService;
use RuntimeException;
use \Zend\Http\Response;

/**
 * ILS driver test
 *
 * @category VuFind2
 * @package  Tests
 * @author   Matus Sabik <sabik@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class XCNCIP2Test extends \VuFindTest\Unit\ILSDriverTestCase
{
	const FIXTURE_DIRECTORY = '/fixtures/ils/xcncip2/';
	//const DLF_API_BASE_URL = 'http://myuniversity.edu:8080/ncipv2/NCIPResponder';

	protected $driver;

	protected $mockZendClient;

	// Sample data - array returned by the patronLogin method.
	protected $samplePatron = array(
	        'id'        => '700',
	        'firstname' => 'John',
	        'lastname'  => 'Smith',
	        'cat_username' => 'johnsmith',
	        'cat_password' => 'ax123ax',
	        'email'     => 'john.smith@example.com',
	        'major' => '',
	        'college' => '',
	    );

	// Sample data - one of the individual item arrays returned by the getMyHolds method.
	protected $sampleOneHold = array(
	    'type' => '',
	    'id'  => '20000',
	    'location' => '',
	    'reqnum' => '',
	    'expire'     => '',
	    'create' => '',
	    'position' => '',
	    'available' => '',
	    'item_id' => '',
	    'volume' => '',
	    'publication_year' => '',
	    'title' => '',
	    'isbn' => '',
	    'issn' => '',
	    'oclc' => '',
	    'upc' => '',
	);

	protected function setUp()
	{
	    $this->setUpDriver('v2.02.ini');
	}

	// @Override
	public function testMissingConfiguration()
	{
	    $this->driver = new XCNCIP2();
	    $this->setExpectedException('VuFind\Exception\ILS');
	    $this->driver->init();
	}

	/***************************************** VuFind methods tests ****************************************/

	public function testCancelHolds()
	{
	    $sampleResponse1 = $this->getResponse('v2.02', 'cancelHolds1.xml');
	    $sampleResponse2 = $this->getResponse('v2.02', 'cancelHolds2.xml');
	    $this->mockZendClient->expects($this->any())->method('send')
	    ->will($this->onConsecutiveCalls($sampleResponse1, $sampleResponse2));
	    $cancelDetails = array(
	        'patron' => $this->samplePatron,
            'details' => array('25001', '25002'),
        );
        $expectedResult = array(
            'count' => 2,
            'items' => array(
                '25001' => array( // 12345 is item_id from method getMyHolds
	                'success' => true, // boolean
                    'status' => '',
                    'sysMessage' => '',),
                '25002' => array(
                    'success' => true,
                    'status' => '',
                    'sysMessage' => '',),
	        ),
	    );
	    $actualResult = $this->driver->cancelHolds($cancelDetails);
	    $this->assertEquals($expectedResult, $actualResult);
	}

	public function checkRequestIsValid()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function findReserves()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getCancelHoldDetails()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getCancelHoldLink()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getConfig()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getCourses()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getDefaultPickUpLocation()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getDepartments()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getFunds()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getHoldDefaultRequiredDate()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function testGetHolding()
	{
	    $sampleResponse = $this->getResponse('v2.02', 'getStatuses2.xml');
	    $this->mockZendClient->expects($this->once())->method('send')->will($this->returnValue($sampleResponse));

	    $expectedResult[] = array(
	            'id'        => '20002',
	            'availability'        => false,
	            'status'        => 'On demand',
	            'location'        => 'mzk',
	            'reserve'        => 'N',
	            'callnumber'        => '722 111 229',
	            'duedate'        => '',
	            'returnDate'        => '',
	            'number'        => '1',
	            'requests_placed'        => '',
	            'barcode'        => 'placeholder1',
	            'notes'        => '',
	            'summary'        => '',
	            'supplements'        => '',
	            'indexes'        => '',
	            'is_holdable'        => '',
	            'holdtype'        => '',
	            'addLink'        => '',
	            'item_id'        => '',
	            'holdOverride'        => '',
	            'addStorageRetrievalRequestLink'        => '',
	            'addILLRequestLink'        => '',

	    );
	    $actualResult = $this->driver->getHolding('20002', $this->samplePatron);
	    // $actualResult = $this->driver->getHolding('20001');
	    $this->assertEquals($expectedResult, $actualResult);
	}

	public function getHoldings()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getHoldLink()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getInstructors()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function testGetMyFines()
	{
	    $sampleResponse = $this->getResponse('v2.02', 'getMyFines.xml');
	    $this->mockZendClient->expects($this->once())->method('send')->will($this->returnValue($sampleResponse));

	    $expectedResult[] = array(
	        'amount' => '56',
	        'checkout'  => '',
	        'fine' => 'Overdue',
	        'balance' => '10',
	        'createdate'     => '2010-10-29T10:49:00',
	        'duedate' => '',
	        'id' => '',
	    );
	    $actualResult = $this->driver->getMyFines($this->samplePatron);
	    $this->assertEquals($expectedResult, $actualResult);
	}

	public function testGetMyHolds()
	{
	    $sampleResponse = $this->getResponse('v2.02', 'getMyHolds.xml');
	    $this->mockZendClient->expects($this->once())->method('send')->will($this->returnValue($sampleResponse));

	    $expectedResult = array(array(
    	        'type' => 'absent',
    	        'id'  => '99001',
    	        'location' => 'prepazka 30',
    	        'reqnum' => 'req0001',
    	        'expire'     => '2014-08-25T12:30:00',
    	        'create' => '2014-08-20T12:30:00',
    	        'position' => '2',
    	        'available' => '',
    	        'item_id' => 'i99001',
    	        'volume' => '',
    	        'publication_year' => '',
    	        'title' => 'Rezervovana kniha',
    	        'isbn' => '',
    	        'issn' => '',
    	        'oclc' => '',
    	        'upc' => '',),
	        array(
	            'type' => 'present',
	            'id'  => '99002',
	            'location' => 'prepazka 35',
	            'reqnum' => 'req0002',
	            'expire'     => '2014-09-24T12:30:00',
	            'create' => '2014-09-15T12:30:00',
	            'position' => '1',
	            'available' => '',
	            'item_id' => 'i99002',
	            'volume' => '',
	            'publication_year' => '',
	            'title' => 'Dalsia rezervovana',
	            'isbn' => '',
	            'issn' => '',
	            'oclc' => '',
	            'upc' => '',),
	    );
	    $actualResult = $this->driver->getMyHolds($this->samplePatron);
	    $this->assertEquals($expectedResult, $actualResult);
	}

	public function testGetMyProfile()
	{
	    $sampleResponse = $this->getResponse('v2.02', 'getMyProfile.xml');
	    $this->mockZendClient->expects($this->once())->method('send')->will($this->returnValue($sampleResponse));

	    $expectedResult = array(
	            'firstname' => 'John',
	            'lastname'  => 'Smith',
	            'address1' => 'Ceska',
	            'address2' => '92',
	            'city'     => 'Brno',
	            'country' => 'Czech republic',
	            'zip' => '582786',
	            'phone' => '788 123 456',
	            'group' => 'ctenar',
	    );
	    $actualResult = $this->driver->getMyProfile($this->samplePatron);
	    $this->assertEquals($expectedResult, $actualResult);
	}

	public function testGetMyTransactions()
	{
	    $sampleResponse = $this->getResponse('v2.02', 'getMyTransactions.xml');
	    $this->mockZendClient->expects($this->once())->method('send')->will($this->returnValue($sampleResponse));
	    $expectedResult = array(array(
    	        'duedate' => '2014-10-01T12:30:00',
    	        'id'  => 'i20000',
    	        'barcode' => '',
    	        'renew' => '',
    	        'renewLimit'     => '',
    	        'request' => '',
    	        'volume' => '',
    	        'publication_year' => '',
    	        'renewable' => '',
    	        'message' => '',
    	        'title' => 'Pozicana kniha',
    	        'item_id' => '',
    	        'institution_name' => '',
    	        'isbn' => '',
    	        'issn' => '',
    	        'oclc' => '',
    	        'upc' => '',
    	        'borrowingLocation' => '',),
	        array(
	            'duedate' => '2014-05-01T12:30:00',
	            'id'  => 'i20001',
	            'barcode' => '',
	            'renew' => '',
	            'renewLimit'     => '',
	            'request' => '',
	            'volume' => '',
	            'publication_year' => '',
	            'renewable' => '',
	            'message' => '',
	            'title' => 'Dalsia pozicana',
	            'item_id' => '',
	            'institution_name' => '',
	            'isbn' => '',
	            'issn' => '',
	            'oclc' => '',
	            'upc' => '',
	            'borrowingLocation' => '',),
	    );

	    $actualResult = $this->driver->getMyTransactions($this->samplePatron);
	    $this->assertEquals($expectedResult, $actualResult);
	}

	public function getNewItems()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getOfflineMode()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getPickUpLocations()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getPurchaseHistory($id)
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getRenewDetails()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function testGetStatusX()
	{
	    $sampleResponse = $this->getResponse('v2.02', 'getStatuses2.xml');
	    $this->mockZendClient->expects($this->once())->method('send')->will($this->returnValue($sampleResponse));

	    $expectedResult[] = array(
	            'id'        => '20002',
	            'status'        => 'On demand',
	            'location'        => 'mzk',
	            'reserve'        => 'N',
	            'callnumber'        => '722 111 229',
	            'availability'        => false,

	    );
	    $actualResult = $this->driver->getStatus('20002');
	    $this->assertEquals($expectedResult, $actualResult);
	}

	public function testGetStatuses()
	{
	    $sampleResponse1 = $this->getResponse('v2.02', 'getStatuses1.xml');
	    $sampleResponse2 = $this->getResponse('v2.02', 'getStatuses2.xml');
	    $this->mockZendClient->expects($this->any())->method('send')
	    ->will($this->onConsecutiveCalls($sampleResponse1, $sampleResponse2, $sampleResponse2));

	    $ids = array(
	        '20000',
	        '20002',
	    );
	    $expectedResult = array(array(array(
                    	                'id' => '20000',
                    	                'status' => 'Loaned',
                    	                'location'        => 'temporary unknown',
                    	                'reserve'        => 'N',
                    	                'callnumber'        => '722 111 229',
                    	                'availability' => false,),),
                    	        array(array(
                    	                'id' => '20002',
                    	                'status' => 'On demand',
                    	                'location'        => 'mzk',
                    	                'reserve'        => 'N',
                    	                'callnumber'        => '722 111 229',
                    	                'availability' => false,),),
	    );
	    $actualResult = $this->driver->getStatuses($ids);
	    $this->assertEquals($expectedResult, $actualResult);
	}

	public function getSuppressedAuthorityRecords()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getSuppressedRecords()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function hasHoldings()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function loginIsHidden()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function testPatronLogin()
	{
	    $sampleResponse = $this->getResponse('v2.02', 'patronLogin.xml');
	    $this->mockZendClient->expects($this->once())->method('send')->will($this->returnValue($sampleResponse));

	    $expectedResult = array(
	            'id'        => '700',
	            'firstname' => 'John',
	            'lastname'  => 'Smith',
	            'cat_username' => 'johnsmith',
	            'cat_password' => 'ax123ax',
	            'email'     => 'john.smith@example.cz',
	            'major' => '',
	            'college' => '',
	    );
	    $actualResult = $this->driver->patronLogin('johnsmith', 'ax123ax');
	    $this->assertEquals($expectedResult, $actualResult);
	}

	public function placeHold()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function renewMyItems()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function renewMyItemsLink()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function supportsMethod()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getMyStorageRetrievalRequests()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function checkStorageRetrievalRequestIsValid()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function placeStorageRetrievalRequest()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function cancelStorageRetrievalRequests()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getCancelStorageRetrievalRequestDetails()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getMyILLRequests()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function checkILLRequestIsValid()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getILLPickupLibraries()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getILLPickupLocations()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function placeILLRequest()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function cancelILLRequests()
	{
	    throw new ILSException('Function not implemented!');
	}

	public function getCancelILLRequestDetails()
	{
	    throw new ILSException('Function not implemented!');
	}

	/**
	 * Setup VuFind driver using given configuration file.
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
	    $this->mockZendClient = $this->getMock('\Zend\Http\Client');
	    $this->mockedHttpService->expects($this->any())->method('createClient')->will($this->returnValue($this->mockZendClient));
	    //$this->mockZendClient->expects($this->any())->method('setRawBody')->will($this->returnValue(null));
	    $this->driver->setHttpService($this->mockedHttpService);
	    $this->driver->setConfig($this->driverConfig);
	    $this->driver->init();
	}

	/**
	 * Retrieve response by version and name
	 *
	 * @param string $version version
	 * @param string $name    name
	 * @param boolean $validity    validity
	 *
	 * @throws RuntimeException
	 * @return \Zend\Http\Response
	 */
	protected function getResponse($version, $name, $validity = true)
	{
// 	    $file = realpath(
// 	            \VUFIND_PHPUNIT_MODULE_PATH . self::FIXTURE_DIRECTORY . 'responses/' .
// 	            ($validity ? 'valid' : 'invalid') . '/' . $version . '/' . $name
// 	    );
	    $file = realpath(
	            \VUFIND_PHPUNIT_MODULE_PATH . self::FIXTURE_DIRECTORY . 'responses/' .
	            $version . '/' . $name
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
