<?php
/**
 * Filter values of facet for displaying - keep only facet values specified
 * in configuration
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * @package  Search
 * @author   Vaclav Rosecky <xrosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org   Main Site
*/
namespace VuFind\Search\Solr;

use VuFindSearch\Backend\BackendInterface;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\EventInterface;

/**
 * Filter facet values for displaying.
 *
 * @category VuFind2
 * @package  Search
 * @author   Vaclav Rosecky <xrosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org   Main Site
*/
class KeepFacetValueListener
{
    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * List of facets with values to keep.
     *
     * @var array
     */
    protected $keepFacets = [];

    /**
     * Constructor.
     *
     * @param BackendInterface $backend         Search backend
     * @param array            $keepFacets      Facet config file id
     */
    public function __construct(
        BackendInterface $backend,
        array $keepFacets
    ) {
        $this->backend = $backend;
        $this->keepFacets = $keepFacets;
    }

    /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach(
        SharedEventManagerInterface $manager
    ) {
        $manager->attach('VuFind\Search', 'post', [$this, 'onSearchPost']);
    }

    /**
     * Retain facet values for display
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPost(EventInterface $event)
    {
        $backend = $event->getParam('backend');

        if ($backend != $this->backend->getIdentifier()) {
            return $event;
        }
        $context = $event->getParam('context');
        if ($context == 'search' || $context == 'retrieve') {
            $this->processKeepFacetValue($event);
        }
        return $event;
    }

    /**
     * Process retain facet value
     *
     * @param EventInterface $event Event
     *
     * @return void
     */
    protected function processKeepFacetValue($event)
    {
        $result = $event->getTarget();
        $facets = $result->getFacets()->getFieldFacets();

        foreach ($this->keepFacets as $facet => $value) {
            if (isset($facets[$facet])) {
                $facets[$facet]->retainKeys((array) $value);
            }
        }
        return null;
    }

}