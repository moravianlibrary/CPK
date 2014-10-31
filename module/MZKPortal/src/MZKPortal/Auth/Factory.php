<?php
/**
 * Authentication Factory Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
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
 * @package  RecordDrivers
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace MZKPortal\Auth;
use Zend\ServiceManager\ServiceManager;

/**
 * Authentication Factory Class
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
class Factory
{

    /**
     * Factory for AuthManager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Manager
     */
    public function getAuthManager(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config')->get('config');
        try {
            // Check if the catalog wants to hide the login link, and override
            // the configuration if necessary.
            $catalog = $sm->get('VuFind\ILSConnection');
            if ($catalog->loginIsHidden()) {
                $config = new \Zend\Config\Config($config->toArray(), true);
                $config->Authentication->hideLogin = true;
                $config->setReadOnly();
            }
        } catch (\Exception $e) {
            // Ignore exceptions; if the catalog is broken, throwing an exception
            // here may interfere with UI rendering. If we ignore it now, it will
            // still get handled appropriately later in processing.
            error_log($e->getMessage());
        }
        
        // Load remaining dependencies:
        $userTable = $sm->get('VuFind\DbTablePluginManager')->get('user');
        $sessionManager = $sm->get('VuFind\SessionManager');
        $pm = $sm->get('VuFind\AuthPluginManager');
        
        // Build the object:
        return new Manager($config, $userTable, $sessionManager, $pm, $catalog);
    }
    
    /**
     * Factory for ShibbolethWithWAYF.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ShibbolethWithWAYF
     */
    public function getShibbolethWithWAYF(ServiceManager $sm)
    {
        return new \MZKPortal\Auth\ShibbolethWithWAYF(
            $sm->getServiceLocator()->get('VuFind\Config')
        );
    }

}