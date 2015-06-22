<?php

/**
 * Perun identity resolver
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * @package  Perun
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace MZKPortal\Perun;

use \VuFind\Exception\Auth as AuthException;
use MZKPortal\Auth\ShibbolethWithWAYF;
use MZKPortal\Auth\PerunShibboleth;

/**
 * Class for resolving user's connected identities from Perun (https://github.com/CESNET/perun)
 *
 * @category VuFind2
 * @package Perun
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org Main Site
 */
class IdentityResolver
{

    const USER_KEY = "user";

    const LIBRARY_KEY = "lib";

    protected $initialized = false;

    protected $perunConfig;

    public function init(\Zend\Config\Config $config)
    {
        if (! $this->initialized) {
            $this->perunConfig = $config->Perun;

            if ($this->perunConfig == null)
                throw new AuthException('Could not load Perun config');

            $this->initialized = true;
        }
    }

    /**
     * Sends eduPersonPrincipalName (eppn) to Perun & returns user's perunId & his institutes.
     *
     * Can send to Perun also SIGLA & userId being used in connected library with appropriate eppn.
     * In that case it basically says Perun to include this institute on output as connected institute.
     *
     * Other institutes, where we send only eppn without SIGLA & userId we do not want to include on output.
     * These are connected, but are not connected libraries we can use to e.g. Reserve a book for user.
     *
     * @param string $eppn
     * @param string $sigla
     * @param string $userId
     * @return [string $perunId, array $institutes]
     */
    public function getUserIdentityFromPerun($eppn, $sigla = null, $userId = null)
    {
        // FIXME communicate with perun
        if (empty($sigla) || empty($userId))
            return $this->getDummyEmptyContent($eppn);

        return $this->getDummyContent($eppn, $sigla, $userId);
    }

    protected function getDummyContent($eppn, $sigla, $userId)
    {
        return array(
            $eppn,

            array(
                $sigla . PerunShibboleth::SEPARATOR . $userId,
                "mzkcz.70" . rand(0, 2),
                "KOHALIB1." . rand(3, 5),
                "KOHALIB1." . rand(7, 8),
                "KOHALIB1." . rand(9, 11)
            )
        );
    }

    protected function getDummyEmptyContent($eppn)
    {
        return array(
            $eppn
        );
    }
}