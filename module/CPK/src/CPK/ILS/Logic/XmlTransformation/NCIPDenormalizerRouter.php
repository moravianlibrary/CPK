<?php
/**
 * XC NCIP Toolkit (v2) ILS Driver Router
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2018.
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
 * @package  ILS_Drivers
 * @author   Inhliziian Bohdan <inhliziian@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */

namespace CPK\ILS\Logic\XmlTransformation;

use CPK\ILS\Driver\NCIPRequests;
use CPK\ILS\Logic\XmlTransformation\Denormalizers\VerbisNCIPDenormalizer;
use CPK\ILS\Logic\XmlTransformation\Denormalizers\ClaviusNCIPDenormalizer;
use CPK\ILS\Logic\XmlTransformation\Denormalizers\ArlNCIPDenormalizer;
use CPK\ILS\Logic\XmlTransformation\Denormalizers\TritiusNCIPDenormalizer;
use CPK\ILS\Logic\XmlTransformation\Denormalizers\AAANCIPDenormalizer;
use CPK\ILS\Logic\XmlTransformation\Denormalizers\NCIPDenormalizer;

class NCIPDenormalizerRouter
{
    /**
     *
     * Create NCIP normalizer by specific system
     *
     * @param $agency
     * @param NCIPRequests $requests
     *
     * @return null|...
     * @throws \Exception
     */
    public function route($method, NCIPRequests $requests)
    {
        $normalizer = null;
        switch ($requests->getILSType()) {
            case 'verbis':
                $normalizer = new VerbisNCIPDenormalizer($method);
                break;
            case 'arl':
                $normalizer = new ArlNCIPDenormalizer($method);
                break;
            case 'aaa001';
                $normalizer = new AAANCIPDenormalizer($method);
                break;
            default:
                $normalizer = new NCIPDenormalizer($method);
        }

        return $normalizer;
    }
}