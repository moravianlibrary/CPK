<?php

/**
 * Solr hierarchical facet listener.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2013.
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
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\Solr;

use VuFindSearch\Backend\BackendInterface;

use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\EventInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Solr hierarchical facet handling listener.
 *
 * @category VuFind2
 * @package  Search
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class NestedFacetListener
{

    const SOLR_LOCAL_PARAMS = "/(\\{[^\\}]*\\})*(\S+)/";

    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     *
     *
     */
    protected $nestedFacets = [];

    /**
     *
     *
     */
    protected $orFacets = [];

    protected $allFacetsAreOr = false;

    protected $enabledForAllFacets = false;

    /**
     * Constructor.
     *
     * @param BackendInterface $backend   Backend
     * @param string           $fieldList Field(s) to highlight (hl.fl param)
     *
     * @return void
     */
    public function __construct(BackendInterface $backend, $nestedFacets, $orFacets, $enabledForAllFacets = false)
    {
        $this->backend = $backend;
        $this->nestedFacets = $nestedFacets;
        $this->orFacets = $orFacets;
        $this->enabledForAllFacets = $enabledForAllFacets;
        if (!empty($this->orFacets) && $this->orFacets[0] == "*") {
            $this->allFacetsAreOr = true;
        }
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
        $manager->attach('VuFind\Search', 'pre', [$this, 'onSearchPre']);
    }

    /**
     *
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        if ($event->getParam('context') != 'search') {
            return $event;
        }
        $backend = $event->getTarget();
        if ($backend === $this->backend) {
            $this->process($event);
        }
        return $event;
    }

    protected function process($event) {
        $params = $event->getParam('params');
        if (!$params) {
            return;
        }

        $facets = $this->nestedFacets;
        if ($this->enabledForAllFacets) {
            foreach ($params->get('facet.field') as $facetField) {
                if (preg_match(self::SOLR_LOCAL_PARAMS, $facetField, $matches)) {
                    $facets[] = $matches[2];
                }
            }
            $params->remove('facet.field');
        }

        $data = [];
        foreach ($facets as $facetField) {
            $data[$facetField] = $this->getFacetConfig($facetField);
        }
        if (!empty($data)) {
            $params->set('json.facet', json_encode($data));
        }

        $fqs = $params->get('fq');
        if (is_array($fqs) && !empty($fqs)) {
            $newfqs = array();
            foreach ($fqs as &$query) {
                $newfqs[] = $this->transformFacetQuery($query);
            }
            $params->set('fq', $newfqs);
        }
    }

    protected function getFacetConfig($field) {
        $data = [
                'type' => 'terms',
                'field' => $field
        ];
        if (in_array($field, $this->nestedFacets)) {
            $data['domain'] = [ 'blockChildren' => 'merged_boolean:true' ];
        }
        if ($this->allFacetsAreOr || in_array($field, $this->orFacets)) {
            $data['excludeTags'] = [ $field . '_filter' ];
        }
        return $data;
    }

    protected function transformFacetQuery($fq) {
        list($field, $query) = explode(":", $fq, 2);
        $params = null;
        $matches = [];
        if (preg_match(self::SOLR_LOCAL_PARAMS, $field, $matches)) {
            $params = $matches[1];
            $field = $matches[2];
        }
        if (!in_array($field, $this->nestedFacets)) {
            return $fq;
        }
        if ($params != null) {
            $params = rtrim($params, "}") . " parent which='merged_boolean:true'" . "}";
            return $params . $field . ':' . $query;
        } else {
            return "{!parent which='merged_boolean:true'}" . $fq;
        }
    }

}