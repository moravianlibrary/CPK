<?php

/**
 * EDS API Querybuilder
 *
 * PHP version 5
 *
 * Copyright (C) EBSCO Industries 2013
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
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch\Backend\EDS;

use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\QueryGroup;
use VuFindSearch\Query\Query;
use VuFindSearch\ParamBag;

/**
 * EDS API Querybuilder
 *
 * @category VuFind2
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class QueryBuilder
{
    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Construct EdsApi search parameters based on a user query and params.
     *
     * @param AbstractQuery $query User query
     *
     * @return ParamBag
     */
    public function build(AbstractQuery $query)
    {
        // Build base query
        $queries = $this->abstractQueryToArray($query);

        // Send back results
        $params = new ParamBag(['query' => $queries]);
        return $params;
    }

    /**
     * Convert a single Query object to an eds api query array
     *
     * @param Query  $query    Query to convert
     *
     * @return string
     */
    protected function queryToEdsQuery(Query $query)
    {
        $expression = $query->getString();
        $operator = $query->getOperator();
        $fieldCode = ($query->getHandler() == 'AllFields') ? '' : $query->getHandler();
        if (!empty($fieldCode)) {
            $expression = $fieldCode . ':' . $expression;
        }
        if (!empty($operator)) {
            $expression = $operator . ',' . $expression;
        }
        return $expression;
    }

    /**
     * Convert a single Query to EDS term
     *
     * @param Query $query to convert
     *
     * @return string with EDS term
     */
    protected function queryToEdsTerm(Query $query)
    {
        $expression = $query->getString();
        $fieldCode = ($query->getHandler() == 'AllFields') ? '' : $query->getHandler();
        if (!empty($fieldCode)) {
            $expression = $fieldCode . ':' . $expression;
        }
        return $expression;
    }

    /// Internal API

    /**
     * Convert an AbstractQuery object to a query string
     *
     * @param AbstractQuery $query Query to convert
     *
     * @return array
     */
    protected function abstractQueryToArray(AbstractQuery $query)
    {
        if ($query instanceof Query) {
            return ['1' => $this->queryToEdsQuery($query)];
        } else {
            return $this->queryGroupToArray($query);
        }
    }

    /**
     * Convert a QueryGroup object to single query
     *
     * @param AbstractQuery $queryRoot of expression
     *
     * @return array
     */
    protected function queryGroupToArray(AbstractQuery $queryRoot) {

        $groups[] = $this->queryToString($queryRoot);
        return $groups;
    }

    /**
     * Convert a query to an expression
     *
     * @param AbstractQuery $query is a tree node
     *
     * @param int $depth of recursion
     *
     * @return string that contains expression
     */
    protected function queryToString(AbstractQuery $query, $depth = 0)
    {
        // Recursively traverse the tree nodes
        if ($query instanceof QueryGroup) {
            // Herein the node is a query group
            $expression = '';
            $op = $query->getOperator();
            $queries = $query->getQueries();
            $first = true;
            foreach ($queries as $child) {
                // Go to child nodes of the current node
                $partial = $this->queryToString($child, $depth + 1);
                if ($first) {
                    $expression = $partial;
                    $first = false;
                } else {
                    $expression .= ' ' . $op . ' ' . $partial;
                }
            }
            // Apply negation and parentheses
            $multiple = count($queries) > 1;
            $negatedExpr = $multiple ? 'NOT (' . $expression . ')' : '(NOT ' . $expression.')';
            $expression = $query->isNegated() ? $negatedExpr : $expression;
            return $depth > 0 && $multiple ? '(' . $expression . ')' : $expression;
        } else {
            // Node is a simple query
            return $this->queryToEdsTerm($query);
        }
    }
}