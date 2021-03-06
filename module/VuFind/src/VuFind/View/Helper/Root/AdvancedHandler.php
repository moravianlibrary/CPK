<?php
/**
 * "Advanced handler" view helper
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;
use VuFind\Search\Options\PluginManager;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;

/**
 * "Advanced handler" view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Vaclav Rosecky <vaclav.rosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class AdvancedHandler extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Search manager
     *
     * @var PluginManager
     */
    protected $manager;

    /**
     * Constructor
     *
     * @param PluginManager $manager Search manager
     */
    public function __construct(PluginManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Return advanced handlers
     *
     * @param arrays $types The search types of the object to retrieve
     *
     * @return array
     */
    public function __invoke(array $types)
    {
        $translator = $this->getView()->plugin('translate');
        $advancedHandlers = [];
        foreach ($types as $type) {
            try {
                $options = $this->manager->get($type);
                foreach ($options->getAdvancedHandlers() as $key => $value) {
                    $advancedHandlers[$type][$key] = $this->view->translate($value);
                }
            } catch (ServiceNotCreatedException $exception) {
                continue;
            }
        }
        return ['data' => $advancedHandlers];
    }

}