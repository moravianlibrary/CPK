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
    
    protected $mockedHttpService;
    protected $driverConfig;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->driver = new Aleph(new \VuFind\Date\Converter());
        $this->mockedHttpService = $this->getMock('VuFindHttp\HttpServiceInterface');
  
        $this->driver->setHttpService($this->mockedHttpService);
        $this->driverConfig = $this->getDriverConfig('Aleph','2.0');
    }
    
    public function setUp() {
        $this->driver->setConfig($this->driverConfig);
        $this->driver->init();
    }
    
    public function testMissingConfiguration()
    {
        $this->driver->setConfig(null);
        $this->setExpectedException('VuFind\Exception\ILS');
        $this->driver->init();
    }
    
    public function testGetHoldingInfoForItem()
    {
        $response = new Response();
        $response->setContent($this->getResponse('aleph', 'response_700.xml'));
        $response->setStatusCode(Response::STATUS_CODE_200);
        $response->setReasonPhrase('OK');
        
        $this->mockedHttpService->expects($this->any())->method('get')
                ->with($this->equalTo("http://aleph.mylibrary.edu:1892/rest-dlf/patron/700/record/LIB01MZK01000748028/items/MZK50000748028000010"),
                    $this->anything(),$this->anything())
                ->will($this->returnValue($response));
        
        $this->driver->getHoldingInfoForItem('700', 'MZK01000748028', 'MZK50000748028000010');
    } 

    /**
     * Retrieve configuration from /fixtures/configs/ @param $version / @param $driver
     * @param string $driver
     * @param string $version
     * @throws RuntimeException
     * @return array
     */
    protected function getDriverConfig($driver, $version = '2.0')
    {
        $file = realpath(
                \VUFIND_PHPUNIT_MODULE_PATH . '/fixtures/configs/'.$version.'/'.$driver.'.ini'
        );
        if (!$file) {
            throw new RuntimeException(
                    sprintf('Unable to get configuration for driver %s', $driver)
            );
        }
        $config = parse_ini_file($file,true);
        return $config;
    }
    
    /**
     * Retrieve configuration from /fixtures/response/ @param $type / @param $name
     * @param string $type
     * @param string $name
     * @throws RuntimeException
     * @return string
     */
    protected function getResponse($type, $name) {
        $file = realpath(
                \VUFIND_PHPUNIT_MODULE_PATH . '/fixtures/response/'.$type.'/'.$name
        );
        if (!$file) {
            throw new RuntimeException(
                    sprintf('Unable to resolve fixture to fixture file: %s', $name)
            );
        }
        $response = file_get_contents($file);
        return $response;
    }
}