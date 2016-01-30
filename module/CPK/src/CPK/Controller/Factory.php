<?php
/**
 * Factory for controllers
 *
 * PHP version 5
 *
 * Copyright (C) MZK 2015.
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
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace CPK\Controller;
use Zend\ServiceManager\ServiceManager;
/**
 * Factory for controllers
 *
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class Factory
{
    /**
     * Construct the RecordController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return RecordController
     */
    public static function getRecordController(ServiceManager $sm)
    {
        return new RecordController(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the PortalController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return PortalController
     */
    public static function getPortalController(ServiceManager $sm)
    {
        return new PortalController(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }
}