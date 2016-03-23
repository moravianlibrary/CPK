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

use Zend\Config\Config, CPK\Auth\Manager as AuthManager;

class IdentityProviders extends \Zend\View\Helper\AbstractHelper
{

    /**
     * Auth Manager to create valid Shibboleth link for all entityIDs
     *
     * @var AuthManager
     */
    protected $authManager;

    protected $libraries = [];

    protected $others = [];

    /**
     * C'tor
     *
     * @param AuthManager $authManager            
     * @param \Zend\Config\Config $config            
     * @param string $lang            
     */
    public function __construct(AuthManager $authManager, \Zend\Config\Config $config, $lang)
    {
        $this->authManager = $authManager;
        
        $idps = $config->toArray();
        
        foreach ($idps as $idp) {
            
            if (isset($idp['entityId']))
                if (isset($idp['cat_username'])) {
                    array_push($this->libraries, $idp);
                } else {
                    array_push($this->others, $idp);
                }
        }
        
        $this->lang = substr($lang, 0, 2);
    }

    public function getLibraries()
    {
        return $this->produceListForTemplate($this->libraries);
    }

    public function getOthers()
    {
        return $this->produceListForTemplate($this->others);
    }

    /**
     * Adds a href to redirect user to in order to authenticate him with Shibboleth
     *
     * @param array $institutions            
     */
    protected function produceListForTemplate(array $institutions)
    {
        $idps = [];
        
        foreach ($institutions as $institution) {
            
            $idp = [
                'href' => $this->authManager->getSessionInitiatorForEntityId(null, $institution['entityId']),
                'name' => $this->lang === 'en' ? $institution['name_en'] : $institution['name_cs'],
                'name_cs' => $institution['name_cs'],
                'name_en' => $institution['name_en'],
                'logo' => $institution['logo']
            ];
            
            array_push($idps, $idp);
        }
        
        return $idps;
    }
}
