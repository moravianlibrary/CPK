<?php
/**
 * Factory for controllers.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @category MZK
 * @package  Controller
 * @author   Jiri Kozlovsky <Jiri.Kozlovsky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace MZKApi\Controller;
use MZKApi\Formatter\ItemFormatter;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for controllers.
 *
 * @category MZK
 * @package  Controller
 * @author   Jiri Kozlovsky <Jiri.Kozlovsky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Construct the ApiController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ApiController
     */
    public static function getApiController(ServiceManager $sm)
    {
        $controller = new ApiController($sm);
        $controller->addApi($sm->get('SearchApi'));
        $controller->addApi($sm->get('ItemApi'));
        return $controller;
    }

    /**
     * Construct the ItemApiController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ItemApiController
     */
    public static function getItemApiController(ServiceManager $sm)
    {
        $itemFields = $sm->getServiceLocator()
            ->get('VuFind\YamlReader')->get('ItemApiFields.yaml');

        $helperManager = $sm->getServiceLocator()->get('ViewHelperManager');

        $if = new ItemFormatter($itemFields, $helperManager);
        $controller = new ItemApiController($sm, $if);
        return $controller;
    }
}
