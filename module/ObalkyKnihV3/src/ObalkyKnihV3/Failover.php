<?php
/**
 * Class to check availability of obalkyknih.cz servers
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
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
 * @package  Theme
 * @author   Jakub Sestak <sestak@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace ObalkyKnihV3;
use Zend\Console\Console;

/**
 * Class to check availability of obalkyknih.cz servers
 *
 * @category VuFind2
 * @package  Theme
 * @author   Jakub Sestak <sestak@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Failover
{

    /**
     * Console log?
     *
     * @var bool
     */
    protected $verbose;

    /**
     * Constructor
     *
     * @param bool $verbose Display messages while testing?
     */
    public function __construct($verbose = false)
    {
        $this->verbose = $verbose;
    }


    /**
     * saves server to use to config
     */
    public function checkAvailability()
    {
        $server1base = "https://cache.obalkyknih.cz";
        $server2base = "https://cache2.obalkyknih.cz";

        $aliveSuffix = "/api/runtime/alive";

        $server1alive = $server1base . $aliveSuffix;
        $server2alive = $server2base . $aliveSuffix;

        $status1 = $this->testServer($server1alive);
        $status2 = $this->testServer($server2alive);

        $config = new \Zend\Config\Config(array(), true);
        $config->ObalkyKnih = array();

        if($status1)
        {
            $this->logMessage("Server to use: " . $server1base);
            $config->ObalkyKnih->server = $server1base;
        }
        else if($status2)
        {
            $this->logMessage("Server to use: " . $server2base);
            $config->ObalkyKnih->server = $server2base;
        }
        else
        {
            $result = "not available";
            $this->logMessage("Server to use: " . $result);
            $config->ObalkyKnih->server = $result;
        }

        $writer = new \Zend\Config\Writer\Ini();
        $writer->toFile("config/vufind/ObalkyKnih.ini",$config);


    }

//    protected function getCurrentSetting(){
//        $configLoader = $this->getServiceLocator()->get('VuFind\Config');
//        $url = $configLoader->get('config')->Site->url;
//    }

    /**
     * @param $server - url address of testing page
     * @return bool - true if server responded correctly in timeout
     */
    protected function testServer($server)
    {
        $this->logMessage("Testuji server: " . $server);
        $client = new \Zend\Http\Client($server);
        $start = microtime(true);
        try {
            $response = $client->send();
        } catch (TimeoutException $ex) {
            return false;
        }
        $this->logMessage("Response time: " . (microtime(true) - $start) . " sec");
        //check response Status code
        $responseStatusCode = $response->getStatusCode();
        $this->logMessage("Status code: " . $responseStatusCode);
        if($responseStatusCode!=200)
            return false;

        //check response Body
        $responseBody = $response->getBody();
        $this->logMessage("Response body: " . $responseBody);
        if($responseBody!="ALIVE")
            return false;

        $this->logMessage("OK");
        return true;

    }

    /**
     * Log a message to the console
     *
     * @param string $str message string
     *
     * @return void
     */
    protected function logMessage($str)
    {
        if ($this->verbose) {
            Console::writeLine($str);
        }
    }
}