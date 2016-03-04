<?php
/**
 * Factory for DB tables.
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
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace CPK\Db\Table;

use Zend\ServiceManager\ServiceManager;

/**
 * Factory for DB tables.
 *
 * @category VuFind2
 * @package Db_Table
 * @author Demian Katz <demian.katz@villanova.edu>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org/wiki/vufind2:developer_manual Wiki
 *      
 *       @codeCoverageIgnore
 */
class Factory
{

    /**
     * Construct the User table.
     *
     * @param ServiceManager $sm
     *            Service manager.
     *            
     * @return User
     */
    public static function getUser(ServiceManager $sm)
    {
        return new User($sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config'));
    }

    /**
     * Construct the Citation style table.
     *
     * @param ServiceManager $sm
     *            Service manager.
     *            
     * @return CitationStyle
     */
    public static function getCitationStyle(ServiceManager $sm)
    {
        return new CitationStyle($sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config'));
    }

    /**
     * Construct the user_settings table.
     *
     * @param ServiceManager $sm
     *            Service manager.
     *            
     * @return UserSettings
     */
    public static function getUserSettings(ServiceManager $sm)
    {
        return new UserSettings($sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config'));
    }

    /**
     * Construct the portal_pages table.
     *
     * @param ServiceManager $sm
     *            Service manager.
     *            
     * @return PortalPages
     */
    public static function getPortalPages(ServiceManager $sm)
    {
        return new PortalPage($sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config'));
    }

    /**
     * Construct the notifications table.
     *
     * @param ServiceManager $sm
     *            Service manager.
     *            
     * @return Notifications
     */
    public static function getNotifications(ServiceManager $sm)
    {
        return new Notifications($sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config'));
    }
}