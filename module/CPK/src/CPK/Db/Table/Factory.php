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
     * Construct the notification_types table.
     *
     * @param ServiceManager $sm
     *            Service manager.
     *
     * @return NotificationTypes
     */
    public static function getNotificationTypes(ServiceManager $sm)
    {
        return new NotificationTypes($sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config'));
    }

    /**
     * Construct the koha_tokens table.
     *
     * @param ServiceManager $sm
     * @return KohaTokens
     */
    public static function getKohaTokens(ServiceManager $sm) {
        return new KohaTokens($sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config'));
    }

    /**
     * Construct the inst_configs table.
     *
     * @param ServiceManager $sm
     *            Service manager.
     *
     * @return InstConfigs
     */
    public static function getInstitutionsConfigs(ServiceManager $sm)
    {
        return new InstConfigs($sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config'));
    }

    /**
     * Construct the libraries_geolocations table.
     *
     * @param ServiceManager $sm
     *            Service manager.
     *
     * @return LibrariesGeolocations
     */
    public static function getLibrariesGeolocations(ServiceManager $sm)
    {
        return new LibrariesGeolocations($sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config'));
    }

    /**
     * Construct the frontend table.
     *
     * @param ServiceManager $sm
     *            Service manager.
     *
     * @return Frontend
     */
    public static function getFrontend(ServiceManager $sm)
    {
        return new Frontend($sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config'));
    }

    /**
     * Construct the widget table.
     *
     * @param ServiceManager $sm
     *            Service manager.
     *
     * @return Widgets
     */
    public static function getWidget(ServiceManager $sm)
    {
        return new Widget($sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config'));
    }

    /**
     * Construct the widget_content table.
     *
     * @param ServiceManager $sm
     *            Service manager.
     *
     * @return WidgetContent
     */
    public static function getWidgetContent(ServiceManager $sm)
    {
        return new WidgetContent($sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config'));
    }

    /**
     * Construct the infobox table.
     *
     * @param ServiceManager $sm
     *            Service manager.
     *
     * @return Infobox
     */
    public static function getInfobox(ServiceManager $sm)
    {
        return new Infobox($sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config'));
    }

    /**
     * Construct the email_delayer table.
     *
     * @param ServiceManager $sm
     *            Service manager.
     *
     * @return EmailDelayer
     */
    public static function getEmailDelayer(ServiceManager $sm)
    {
        return new EmailDelayer($sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config'));
    }

    /**
     * Construct the email_types table.
     *
     * @param ServiceManager $sm
     *            Service manager.
     *
     * @return EmailTypes
     */
    public static function getEmailTypes(ServiceManager $sm)
    {
        return new EmailTypes($sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config'));
    }

    /**
     * Construct the system table.
     *
     * @param ServiceManager $sm
     *            Service manager.
     *
     * @return System
     */
    public static function getSystem(ServiceManager $sm)
    {
        return new System($sm->getServiceLocator()
            ->get('VuFind\Config')
            ->get('config'));
    }
}
