<?php
/**
 * Filters prepared for JavaScript handling echoer.
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
 * @author	Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace CPK\View\Helper\CPK;

use Zend\View\Helper\AbstractHelper;

/**
 * Filters prepared for JavaScript handling echoer.
 *
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class ParseFilterOptions extends AbstractHelper
{

    public function __invoke($holdings, $config)
    {
        if (empty($config))
            return false;

        if (! isset($config->Catalog) ||
             ! isset($config->Catalog->minHoldingsToApplyFilters))
            return false;

            // Parse minHoldingsToApplyFilters
        $minHoldings = intval($config->Catalog->minHoldingsToApplyFilters);

        if ($minHoldings == 0)
            return false;

        if (is_array($holdings) && count($holdings) >= $minHoldings) {
            $yearOptions = [];
            $volumeOptions = [];

            foreach ($holdings as $holding) {
                if (isset($holding['year'])) {
                    array_push($yearOptions, $holding['year']);
                }

                if (isset($holding['volume'])) {
                    array_push($volumeOptions, $holding['volume']);
                }
            }

            $yearOptions = array_unique($yearOptions, SORT_NUMERIC);
            $volumeOptions = array_unique($volumeOptions, SORT_NUMERIC);

            array_multisort($yearOptions, SORT_DESC);
            array_multisort($volumeOptions);

            return array(
                'year' => $yearOptions,
                'volume' => $volumeOptions,
            );

        }
    }
}
