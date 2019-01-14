<?php
/**
 * ILS Driver Factory Class for CPK
 *
 * PHP version 5
 *
 * Copyright (C) Moravian Library 2016
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
 * @package  ILS_Drivers
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace CPK\ILS\Driver;
use Zend\ServiceManager\ServiceManager;

/**
 * ILS Driver Factory Class for CPK
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
class Factory
{
    /**
     * Factory for Aleph driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Aleph
     */
    public static function getAleph(ServiceManager $sm)
    {
        $sl = $sm->getServiceLocator();
        $dbTablePluginManager = $sl->get('VuFind\DbTablePluginManager');

        return new Aleph(
            $sl->get('VuFind\DateConverter'),
            $sl->get('VuFind\CacheManager'),
            $sl->get('VuFind\Search'),
            $dbTablePluginManager->get('recordstatus')
        );
    }

    /**
     * Factory for MultiBackend driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return MultiBackend
     */
    public static function getMultiBackend(ServiceManager $sm)
    {
        $sl = $sm->getServiceLocator();
        $dbTablePluginManager = $sl->get('VuFind\DbTablePluginManager');

        return new MultiBackend(
            $sl->get('VuFind\Config'),
            $sl->get('VuFind\ILSAuthenticator'),
            $sl->get('VuFind\Search'),
            $dbTablePluginManager->get('InstConfigs')
        );
    }

    public static function getZiskej(ServiceManager $sm)
    {
        $sl = $sm->getServiceLocator();

        return new Ziskej($sl->get('VuFind\Config')->get('config')->SensitiveZiskej);
    }

}