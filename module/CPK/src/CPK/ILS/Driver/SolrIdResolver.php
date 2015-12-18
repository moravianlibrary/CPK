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
namespace CPK\ILS\Driver;

use VuFind\ILS\Driver\SolrIdResolver as SolrIdResolverBase;


/**
 * SolrIdResolver - resolve bibliographic base against solr.
 */
class SolrIdResolver extends SolrIdResolverBase
{
    protected $source;

    public function resolveIds(&$recordsToResolve, $source = null, $config = null)
    {
        $this->source = $source;
        
        if ($config !== null && is_array($config)) {
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
        
        $idsToResolve = array();
        foreach ($recordsToResolve as $record) {
            $identifier = $record[$this->itemIdentifier];
            if (isset($identifier) && ! empty($identifier)) {
                $idsToResolve[] = $record[$this->itemIdentifier];
            }
        }
        $resolved = $this->convertToIDUsingSolr($idsToResolve);
        foreach ($recordsToResolve as &$record) {
            if (isset($record[$this->itemIdentifier])) {
                $id = $record[$this->itemIdentifier];
                if (isset($resolved[$id])) {
                    $record['id'] = explode(".", $resolved[$id])[1];
                }
            }
        }
    }

    protected function getId($record) {
        
        $source = ($this->source) ?: $this->prefix;
        
        $id = $record->getUniqueID();
        if (substr($id, 0, strlen($source)) === $source) {
            return $id;
        }
        return null;
    }
}