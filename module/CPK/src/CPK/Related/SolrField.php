<?php

/**
 * Class SolrField
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2019.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  CPK\Related
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */

namespace CPK\Related;

class SolrField implements \VuFind\Related\RelatedInterface
{
    /**
     * Similar records
     *
     * @var array
     */
    protected $results;

    /**
     * Establishes base settings for making recommendations.
     *
     * @param string $settings Settings from config.ini
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver object
     *
     * @return void
     */
    public function init($settings, $driver)
    {
        $this->results = $driver->getSimilarFromSolrField();
    }

    /**
     * Get an array of StdObjects representing items similar to the one
     * passed to the constructor.
     *
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }
}