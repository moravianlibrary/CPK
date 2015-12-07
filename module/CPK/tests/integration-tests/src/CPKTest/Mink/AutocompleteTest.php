<?php
/**
 * Mink AutocompleteTest class.
 *
 * PHP version 5
 *
 * Copyright (C) Moravská Zemská Knihovna 2015.
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
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace CPKTest\Mink;

use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use WebDriver\Exception\NoSuchElement;
use WebDriver\Exception\UnknownError;
use WebDriver\Exception;
use WebDriver\Key;

/**
 * Mink AutocompleteTest class.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class AutocompleteTest extends \PHPUnit_Framework_TestCase
{
    /**
	 * Tests whether autocomplete returns non-empty results when performing 
	 * "php" search.
	 * 
	 * @throws Behat\Mink\Exception\DriverException
	 * @throws Exception
	 * @throws WebDriver\Exception\NoSuchElement
	 * @throws WebDriver\Exception\UnknownError
	 * @throws WebDriver\Exception
	 * 
	 * @return void
	 */
	public function testAutocompleteReturnsResults()
	{
		$protocol = "https://";
		//$host     = $_SERVER['HTTP_HOST'];
		$host = 'beta.knihovny.cz';
		$port     = "";
	    $path     = "/Search/Results";
		$query    = "?lookfor=php&type=AllFields&limit=10&sort=relevance";
		
		$url = $protocol . $host . (! empty($port) ? ':' . $port : '') . $path . $query;
		$browser = 'firefox';
		
		try {
    		$driver = new Selenium2Driver($browser, null, $url);
    		$mink = new Mink(array(
    		    'selenium2' => new Session($driver),
    		));
    		
    		$session = $mink->getSession('selenium2');
		} catch (\Exception $e) {
		    throw new DriverException(
		        'Could not open connection: ' . $e->getMessage(), 0, $e
		    );
		}
		
		if (! $session) {
		    throw new DriverException(
		        'Could not connect to a Selenium 2 / WebDriver server', 0, $e
		    );
		}
    		
		try {
		    $page = $session->getPage();
		    sleep(3);
		    
		    $lookForInputXpath = "//input[@id='searchForm_lookfor']";
		    
		    $count = $page->findAll('xpath', $lookForInputXpath);

		    if (! count($count)) {
		        throw new \InvalidArgumentException("Could not evaluate XPath: $lookForInputXpath. ".implode(", ", $count));
		    }
		    //$lookForInputElement = $driver->findElementXpaths($lookForInputXpath);
		    //print_r($lookForInputElement);
        	$count[0]->click();
        	
        	$driver->keyUp($lookForInputXpath, Key::CONTROL + "a");
        	$driver->keyUp($lookForInputXpath, Key::DELETE);
        	$driver->keyUp($lookForInputXpath, "a");
        	$driver->keyUp($lookForInputXpath, "h");
        	$driver->keyUp($lookForInputXpath, "o");
        	$driver->keyUp($lookForInputXpath, "j");
        	
        	sleep(2);
        	
        	$resultsXpath = "//span[@class='twitter-typeahead']"
        	               ."/span[@class='tt-dropdown-menu']"
        	               ."/div[@class='tt-dataset-0']"
        	               ."/span[@class='tt-suggestions']"
        	               ."/div[@class='tt-suggestion']";
			$results = $driver.findElementXpaths($resultsXpath);
			
			if (count($results) > 0)
				return;
			
			$driver->stop();
        	
        	$this->fail("No suggestions returned. Autocomplete seems broken.");

        } catch (NoSuchElement $e) {
            $this->fail("Exception was rised: " . $e->getMessage() . $e->getTraceAsString());
        } catch (UnknownError $e) {
            $this->fail("Exception was rised: " . $e->getMessage() . $e->getTraceAsString());
        } catch (Exception $e) {
        	$this->fail("Exception was rised: " . $e->getMessage() . $e->getTraceAsString());
        }
    }
}
