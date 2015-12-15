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
 * @author   Jakub Šesták <sestak@mzk.cz>
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
 * @author   Jakub Šesták <sestak@mzk.cz>
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


    public function check()
    {
        $server1 = "http://cache.obalkyknih.cz/api/runtime/alive";
        $server2 = "http://cache2.obalkyknih.cz/api/runtime/alive";

        $status1 = $this->testServer($server1);
        $status2 = $this->testServer($server2);

        if($status2)
        {
            $this->logMessage("Server to use: " . $server2);
        }
        else if($status1)
        {
            $this->logMessage("Server to use: " . $server1);
        }


    }

    protected function testServer($server)
    {
        $this->logMessage("Testuji server: " . $server);
        $client = new \Zend\Http\Client($server);
        try {
            $response = $client->send();
        } catch (TimeoutException $ex) {
            return null; // TODO what to do when server is not responding
        }

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