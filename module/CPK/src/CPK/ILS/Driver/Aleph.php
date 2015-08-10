<?php
/**
 * Aleph ILS driver
 *
 * PHP version 5
 *
 * Copyright (C) UB/FU Berlin
 *
 * last update: 7.11.2007
 * tested with X-Server Aleph 18.1.
 *
 * TODO: login, course information, getNewItems, duedate in holdings,
 * https connection to x-server, ...
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
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace CPK\ILS\Driver;
use MZKCommon\ILS\Driver\Aleph as AlephBase;

class Aleph extends AlephBase
{

    public function getMyProfile($user) {
        $profile = parent::getMyProfile($user);

        $eppnScope = $this->config['Catalog']['eppnScope'];

        $blocks = null;

        foreach ($profile['blocks'] as $block) {
            if (! empty($eppnScope)) {
                $blocks[$eppnScope] = (string) $block;
            } else
                $blocks[] = (string) $block;
        }

        $profile['blocks'] = $blocks;

        return $profile;

    }
}