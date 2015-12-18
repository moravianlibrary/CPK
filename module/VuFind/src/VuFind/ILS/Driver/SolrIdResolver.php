<?php
/**
 * Solr Id Resolver
 *
 * PHP version 5
 *
 * Copyright (C) UB/FU Berlin
 *
 * last update: 7.11.2007
 * tested with X-Server Aleph 22
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
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;

/**
 * SolrIdResolver - resolve bibliographic base against solr.
 *
 */
class SolrIdResolver {

    protected $solrQueryField = 'availability_id_str';

    protected $itemIdentifier = 'adm_id';

    protected $prefix = null;

    /**
     * Search service (used for lookups by barcode number)
     *
     * @var \VuFindSearch\Service
     */
    protected $searchService = null;

    public function __construct(\VuFindSearch\Service $searchService, $config)
    {
        $this->searchService = $searchService;
        if (isset($config['IdResolver']['solrQueryField'])) {
            $this->solrQueryField = $config['IdResolver']['solrQueryField'];
        }
        if (isset($config['IdResolver']['itemIdentifier'])) {
            $this->itemIdentifier = $config['IdResolver']['itemIdentifier'];
        }
        if (isset($config['IdResolver']['prefix'])) {
            $this->prefix = $config['IdResolver']['prefix'];
        }
    }

    public function resolveIds(&$recordsToResolve)
    {
        $idsToResolve = array();
        foreach ($recordsToResolve as $record) {
            $identifier = $record[$this->itemIdentifier];
            if (isset($identifier) && !empty($identifier)) {
                $idsToResolve[] = $record[$this->itemIdentifier];
            }
        }
        $resolved = $this->convertToIDUsingSolr($idsToResolve);
        foreach ($recordsToResolve as &$record) {
            if (isset($record[$this->itemIdentifier])) {
                $id = $record[$this->itemIdentifier];
                if (isset($resolved[$id])) {
                    $record['id'] = $resolved[$id];
                }
            }
        }
    }

    protected function convertToIDUsingSolr(&$ids)
    {
        if (empty($ids)) {
            return array();
        }
        $results = array();
        $group = new \VuFindSearch\Query\QueryGroup('OR');
        foreach ($ids as $id) {
            $query = new \VuFindSearch\Query\Query($this->solrQueryField. ':' . $id);
            $group->addQuery($query);
        }
        if (isset($this->prefix)) {
            $idPrefixQuery = new \VuFindSearch\Query\Query('id:' . $this->prefix . '*');
            $group = new \VuFindSearch\Query\QueryGroup('AND', [$idPrefixQuery, $group]);
        }
        $params = new \VuFindSearch\ParamBag(['disableDedup' => TRUE]);
        $docs = $this->searchService->search('Solr', $group, 0, sizeof($ids), $params);
        foreach ($docs->getRecords() as $record) {
            $fields = $record->getRawData();
            if (isset($fields[$this->solrQueryField])) {
                if (is_array($fields[$this->solrQueryField])) {
                    foreach ($fields[$this->solrQueryField] as $value) {
                        if (in_array($value, $ids)) {
                            $results[$value] = $this->getId($record);
                        }
                    }
                } else {
                    $value = $fields[$this->solrQueryField];
                    if (in_array($value, $ids)) {
                        $results[$value] = $this->getId($record);
                    }
                }
            }
        }
        return $results;
    }

    protected function getId($record) {
        $id = $record->getUniqueID();
        if (substr($id, 0, strlen($this->prefix)) === $this->prefix) {
            $id = substr($id, strlen($this->prefix));
        }
        return $id;
    }

}
