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
use VuFind\ILS\Driver\Aleph;
use VuFindHttp\HttpServiceInterface;
use \Zend\Http\Response;

use RuntimeException;

/**
 * ILS driver test
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class AlephTest extends \VuFindTest\Unit\ILSDriverTestCase
{
    
    /**
     * Mocked HTTP service
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected $mockedHttpService;
    
    /**
     * Aleph driver configuration
     *
     * @var array
     */    
    protected $driverConfig;
    
    /**
     * Aleph driver
     *
     * @var  \VuFind\ILS\Driver\Aleph
     */
    protected $driver;
    
    /**
     * Constructor
     */
    public function __construct()
    {
    }
    
    public function setUp() {
    }
    
    public function testMissingConfiguration()
    {
        $this->setUpDriver('Aleph_XServer_enabled.ini');
        $this->driver->setConfig(null);
        $this->setExpectedException('VuFind\Exception\ILS');
        $this->driver->init();
    }
    
    public function testGetHoldingInfoForItem()
    {
        $this->setUpDriver('Aleph_XServer_enabled.ini');
        $response = $this->getResponse('v20.2.10', 'itemsPerPatronResponse.xml');
        $patronId = 'TEST';
        $lib = $this->driverConfig['Catalog']['bib'];
        $recordId = '000748028';
        $libAndRecordId = $lib . $recordId; 
        $itemId = 'LIB50000748028000010';
        
        $expectedUrl = "http://aleph.mylibrary.edu:1892/rest-dlf/patron/$patronId/record/$libAndRecordId/items/$itemId";
        
        $this->mockedHttpService->expects($this->any())->method('get')
                ->with($this->equalTo($expectedUrl),
                    $this->equalTo(array()), $this->equalTo(null))
                    ->will($this->returnValue($response));
        $expectedResult = array(
            'pickup-locations' => array(
                'MZK' => 'Loan Department - Ground floor',
            ),
            'last-interest-date' => '29.11.2013',
            'order' => 1
        );
        $realResult = $this->driver->getHoldingInfoForItem($patronId, $recordId, $itemId);
        $this->assertEquals($expectedResult, $realResult);
    }
    
    public function testGetMyProfileUsingRestDLF() {
        $this->setUpDriver('Aleph_XServer_disabled.ini');
        $patronId = 'TEST';
        $user = array('id' => 'TEST');
        
        $patronAddressUrl = "http://aleph.mylibrary.edu:1892/rest-dlf/patron/$patronId/patronInformation/address";
        $patronAddressResponse = $this->getResponse('v20.2.10', 'patronInformationAddressResponse.xml');
        $this->mockedHttpService->expects($this->at(0))->method('get')
                ->with($this->equalTo($patronAddressUrl),
                    $this->equalTo(array()), $this->equalTo(null))
                    ->will($this->returnValue($patronAddressResponse));
        
        $patronRegistrationUrl = "http://aleph.mylibrary.edu:1892/rest-dlf/patron/$patronId/patronStatus/registration";
        $patronRegistrationResponse = $this->getResponse('v20.2.10', 'patronStatusRegistration.xml');
        $this->mockedHttpService->expects($this->at(1))->method('get')
        ->with($this->equalTo($patronRegistrationUrl),
            $this->equalTo(array()), $this->equalTo(null))
            ->will($this->returnValue($patronRegistrationResponse));
        
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
        $realResult = $this->driver->getMyProfile($user);
        $this->assertEquals($expectedResult, $realResult);
    }

    /**
     * Setup Aleph driver using given configuration file
     * @param string $config
     * @throws RuntimeException
     * @return void
     */
    protected function setUpDriver($config)
    {
        $file = realpath(
                \VUFIND_PHPUNIT_MODULE_PATH . '/fixtures/ils/aleph/configs/' . $config
        );
        if (!$file) {
            throw new RuntimeException(
                    sprintf('Unable to get configuration for driver %s', $driver)
            );
        }
        $this->driverConfig = parse_ini_file($file, true);
        $this->driver = new Aleph(new \VuFind\Date\Converter());
        $this->mockedHttpService = $this->getMock('VuFindHttp\HttpServiceInterface');
        $this->driver->setHttpService($this->mockedHttpService);
        $this->driver->setConfig($this->driverConfig);
        $this->driver->init();
    }
    
    /**
     * Retrieve response by name and version
     * 
     * @param string $version
     * @param string $name
     * @throws RuntimeException
     * @return \Zend\Http\Response 
     */
    protected function getResponse($version, $name) {
        $file = realpath(
                \VUFIND_PHPUNIT_MODULE_PATH . '/fixtures/ils/aleph/responses/' . $version .'/' . $name
        );
        if (!$file) {
            throw new RuntimeException(
                    sprintf('Unable to resolve fixture to fixture file: %s', $name)
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
