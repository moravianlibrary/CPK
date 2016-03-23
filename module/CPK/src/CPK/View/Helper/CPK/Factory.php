<?php
/**
 * Factory for Bootstrap view helpers.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace CPK\View\Helper\CPK;

use Zend\ServiceManager\ServiceManager, CPK\Db\Table\PortalPage as PortalPageTable;

/**
 * Factory for Bootstrap view helpers.
 *
 * @category VuFind2
 * @package View_Helpers
 * @author Demian Katz <demian.katz@villanova.edu>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org/wiki/vufind2:developer_manual Wiki
 *       @codeCoverageIgnore
 */
class Factory
{

    public static function getRecord(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config');
        return new Record($config);
    }

    /**
     * Construct the Flashmessages helper.
     *
     * @param ServiceManager $sm
     *            Service manager.
     *            
     * @return Flashmessages
     */
    public static function getFlashmessages(ServiceManager $sm)
    {
        $messenger = $sm->getServiceLocator()
            ->get('ControllerPluginManager')
            ->get('FlashMessenger');
        $config = $sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config');
        return new Flashmessages($messenger, $config);
    }

    public static function getLogos(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('shibboleth');
        
        return new Logos($config);
    }

    public static function getGlobalNotifications(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('notifications');
        
        $lang = $sm->getServiceLocator()->has('VuFind\Translator') ? $sm->getServiceLocator()
            ->get('VuFind\Translator')
            ->getLocale() : 'en';
        
        return new GlobalNotifications($config, $lang, $sm->get('transesc'));
    }

    public static function getPortalPages(ServiceManager $sm)
    {
        $portalPageTable = $sm->getServiceLocator()
            ->get('VuFind\DbTablePluginManager')
            ->get("portalpages");
        
        $languageCode = $sm->getServiceLocator()->has('VuFind\Translator') ? $sm->getServiceLocator()
            ->get('VuFind\Translator')
            ->getLocale() : 'en';
        
        return new PortalPages($portalPageTable, $languageCode);
    }

    public static function getIdentityProviders(ServiceManager $sm)
    {
        $authManager = $sm->getServiceLocator()->get('VuFind\AuthManager');
        
        $config = $sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('shibboleth');
        
        $lang = $sm->getServiceLocator()->has('VuFind\Translator') ? $sm->getServiceLocator()
            ->get('VuFind\Translator')
            ->getLocale() : 'en';
        
        return new IdentityProviders($authManager, $config, $lang);
    }
}
