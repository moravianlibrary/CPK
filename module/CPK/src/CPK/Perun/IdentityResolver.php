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
namespace CPK\Perun;

use VuFind\Exception\Auth as AuthException;
use CPK\Auth\PerunShibboleth;

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

    // TODO: Fetch attribute definition once & keep it in cache

    protected $perunConfig;

    protected $requiredConfigVariables = array(
        "registrar",
        "kerberosRpc",
        "targetNew",
        "targetExtended",
        "attributeNumberId",
        "voManagerLogin",
        "voManagerPassword"
    );

    /**
     *
     * Gets Random identites to substitute for Perun implementation
     *
     * @param string $sigla
     * @param string $userId
     * @return array $institutes
     */
    public function getDummyContent($sigla, $userId)
    {
        return array(
            $sigla . PerunShibboleth::SEPARATOR . $userId,
            "mzk.70" . rand(0, 2),
            "xcncip2." . rand(3, 5),
            "xcncip2." . rand(7, 8),
            "xcncip2." . rand(9, 11)
        );
    }

    /**
     * Validate configuration parameters.
     * This is a support method for getConfig(),
     * so the configuration MUST be accessed using $this->config; do not call
     * $this->getConfig() from within this method!
     *
     * This method is basically called by PerunShibboleth on it's own validateConfig call.
     *
     * @throws AuthException
     * @return void
     */
    public function validateConfig(\Zend\Config\Config $config)
    {
        $this->perunConfig = $config->Perun;

        if ($this->perunConfig == null) {
            throw new AuthException('[Perun] section not found in config.ini');
        }

        foreach ($this->requiredConfigVariables as $reqConf) {
            if (empty($this->perunConfig->$reqConf)) {
                throw new AuthException("Attribute '$reqConf' is not set in config.ini's [Perun] section");
            }
        }
    }

    public function getPerunConfig()
    {
        return $this->perunConfig;
    }
}