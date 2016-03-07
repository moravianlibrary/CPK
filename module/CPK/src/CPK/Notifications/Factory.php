<?php
/**
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
 * @package  Notifications
 * @author   Jiří Kozlovský
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace CPK\Notifications;

use Zend\ServiceManager\ServiceManager;

class Factory
{
    /**
     * Factory for Notifications.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Notifications
     */
    public static function getNotificationsHandler(ServiceManager $sm)
    {
        return new NotificationsHandler(
                $sm->get('viewmanager')->getRenderer(), // View model for translations
                $sm->get('VuFind\DbTablePluginManager')->get('notifications'),
                $sm->get('VuFind\ILSConnection')
            );
    }

}