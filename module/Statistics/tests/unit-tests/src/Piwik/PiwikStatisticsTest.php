<?php
/**
 * PiwikStatistics Tests
 *
 * PHP version 5
 *
 * Copyright (C) MZK 2015.
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
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace StatisticsTest\Piwik;

use Statistics\Piwik\PiwikStatistics;
use PHPUnit_Framework_TestCase;

/**
 * PiwikStatistics Test case
 * Calls Piwik's API and returns it's data
 * 
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class PiwikStatisticsTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test query building
     *
     * @return void
     */
    public function testBuildQuery()
    {
        $a = 5;
        $b = 5;
        $this->assertEquals($a, $b);
    }
}