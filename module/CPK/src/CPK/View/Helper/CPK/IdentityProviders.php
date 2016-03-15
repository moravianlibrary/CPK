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

use Zend\Config\Config, CPK\Auth\Manager as AuthManager, VuFindHttp\HttpServiceAwareTrait;
use CPK\Db\Table\Institutions;

class IdentityProviders extends \Zend\View\Helper\AbstractHelper
{

    /**
     * Auth Manager to create valid Shibboleth link for all entityIDs
     *
     * @var AuthManager
     */
    protected $authManager;

    /**
     * Institutions table to retrieve all institutions from DB
     *
     * @var Institutions
     */
    protected $institutionsTable;

    /**
     * Constructor
     *
     * @param
     *            \Zend\Config\Config VuFind configuration
     */
    public function __construct(AuthManager $authManager, Institutions $institutionsTable, $lang)
    {
        $this->authManager = $authManager;
        
        $this->institutionsTable = $institutionsTable;
        
        $this->lang = substr($lang, 0, 2);
    }

    public function getAll()
    {
        $institutions = $this->institutionsTable->getAll();
        
        $idps = [];
        
        foreach ($institutions as $institution) {
            
            $idp = [
                'href' => $this->authManager->getSessionInitiatorForEntityId(null, $institution['entity_id']),
                'name' => $this->lang === 'en' ? $institution['name_en'] : $institution['name_cs'],
                'logo' => $institution['logo_url']
            ];
            
            array_push($idps, $idp);
        }
        
        return $idps;
    }
}
