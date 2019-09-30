<?php
/**
 * IdentityProviders view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  View_Helpers
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace CPK\View\Helper\CPK;

use VuFind\Exception\Auth;
use Zend\Config\Config, CPK\Auth\Manager as AuthManager;
use Zend\View\Helper\AbstractHelper;

class IdentityProviders extends AbstractHelper
{

    /**
     * Auth Manager to create valid Shibboleth link for all entityIDs
     *
     * @var AuthManager
     */
    protected $authManager;

    /**
     * Logos helper to fetch logos properly as desired
     *
     * @var Logos $logosHelper
     */
    protected $logosHelper;

    protected $libraries = [];

    protected $others = [];

    protected $idps = [];

    /**
     * C'tor
     *
     * @param AuthManager $authManager
     * @param Logos $logosHelper
     * @param Config $config
     * @param string $lang
     */
    public function __construct(AuthManager $authManager, Logos $logosHelper, Config $config, $lang)
    {
        $this->authManager = $authManager;
        $this->logosHelper = $logosHelper;

        $this->idps = $config->toArray();

        foreach ($this->idps as $source => $idp) {

            if (isset($idp['entityId']))
                if (isset($idp['cat_username'])) {
                    $this->libraries[$source] = $idp;

                } elseif ($idp['entityId']) {
                    $this->others[$source] = $idp;
                }
        }

        $this->lang = substr($lang, 0, 2);
    }

    public function getLibraries()
    {
        if ($this->authManager->isLoggedIn())
            $isConsolidation = true;
        else
            $isConsolidation = false;

        return $this->produceListForTemplate($this->libraries, $isConsolidation);
    }

    public function getOthers()
    {
        if ($this->authManager->isLoggedIn())
            $isConsolidation = true;
        else
            $isConsolidation = false;

        return $this->produceListForTemplate($this->others, $isConsolidation);
    }


    /**
     * filter institutions by those which available in ziskej
     *
     * @param $institutions array of institutions available in ziskej
     * @return array of filtered institutions
     * @throws Auth
     */
    public function getZiskejLibraries($institutions) {
        if ($this->authManager->isLoggedIn())
            $isConsolidation = true;
        else
            $isConsolidation = false;

        $filtered = array_filter($this->idps, function ($key) use ($institutions) {
            return in_array($key, $institutions);
        }, ARRAY_FILTER_USE_KEY);

        return $this->produceListForTemplate($filtered, $isConsolidation);
    }

    /**
     * Adds a href to redirect user to in order to authenticate him with Shibboleth
     *
     * @param array $institutions
     * @param $isConsolidation
     * @return array of parsed institutions
     * @throws Auth
     */
    protected function produceListForTemplate(array $institutions, $isConsolidation)
    {
        $idps = [];

        foreach ($institutions as $source => $institution) {

            if ($isConsolidation)
                $href = $this->authManager->getAccountConsolidationUrl($institution['entityId']);
            else
                $href = $this->authManager->getSessionInitiatorForEntityId($institution['entityId']);

            $idp = [
                'href' => $href,
                'name' => $this->lang === 'en' ? $institution['name_en'] : $institution['name_cs'],
                'name_cs' => $institution['name_cs'],
                'name_en' => $institution['name_en'],
                'logo' => $this->logosHelper->getLogo($source, '_small'),
                'isConsolidation' => $isConsolidation,
                'warn_msg' => $institution['warn_msg']
            ];

            array_push($idps, $idp);
        }

        return $idps;
    }
}
