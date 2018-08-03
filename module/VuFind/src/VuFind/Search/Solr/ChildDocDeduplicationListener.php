<?php

/**
 * Solr deduplication (merged records) listener.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2013.
 * Copyright (C) The National Library of Finland 2013.
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
 * Solr merged record handling listener.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ChildDocDeduplicationListener extends DeduplicationListener
{

    /**
     * Record factory
     *
     * @var RecordFactory
     */
    protected $recordFactory;

    /**
     * Constructor.
     *
     * @param BackendInterface        $backend          Search backend
     * @param ServiceLocatorInterface $serviceLocator   Service locator
     * @param string                  $searchConfig     Search config file id
     * @param string                  $dataSourceConfig Data source file id
     * @param bool                    $enabled          Whether deduplication is
     * enabled
     *
     * @return void
     */
    public function __construct(
        BackendInterface $backend,
        ServiceLocatorInterface $serviceLocator,
        $searchConfig, $facetConfig, $dataSourceConfig = 'datasources', $enabled = true
    ) {
        parent::__construct($backend, $serviceLocator, $searchConfig, $facetConfig,
            $dataSourceConfig, $enabled);
        $this->recordFactory = $this->serviceLocator->get('VuFind\RecordDriverPluginManager');
    }

    /**
     * Set up filter for excluding merge children.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        $backend = $event->getTarget();
        if ($backend === $this->backend) {
            $params = $event->getParam('params');
            $context = $event->getParam('context');
            if (($context == 'search' || $context == 'similar') && $params) {
                $disableDedup = $params->get('disableDedup');
                if (isset($disableDedup[0]) && $disableDedup[0] == TRUE) {
                    $this->enabled = false;
                }
                $params->remove('disableDedup');
                // If deduplication is enabled, filter out merged child records,
                // otherwise filter out dedup records.
                if ($this->enabled) {
                    $params->set('uniqueId', 'local_ids_str_mv');
                    $fq = '-merged_child_boolean:true';
                    $fl = '*,[child parentFilter=merged_boolean:true]';
                    $params->set('fl', $fl);
                } else {
                    $fq = '-merged_boolean:true';
                }
                $params->add('fq', $fq);
            }
        }
        return $event;
    }

    /**
     * Fetch local records for all the found dedup records
     *
     * @param EventInterface $event Event
     *
     * @return void
     */
    protected function retrieveLocalRecords($event, $idList)
    {
        $records = [];
        $result = $event->getTarget();
        foreach ($result->getRecords() as $record) {
            $fields = $record->getRawData();
            foreach ($fields['_childDocuments_'] as $rawLocalRecord) {
                $id = $rawLocalRecord['id'];
                if (in_array($id, $idList)) {
                    $records[] = $this->recordFactory->getSolrRecord($rawLocalRecord);
                }
            }
        }
    }

}
