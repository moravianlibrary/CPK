<?php
/**
 * AlphaBrowse helper for AlphaBrowse module
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/alphabetical_heading_browse Wiki
 */
namespace MZKCatalog\AlephBrowse;
use VuFindHttp\HttpServiceInterface;

class Connector implements \VuFindHttp\HttpServiceAwareInterface
{

    /**
     * CGI URL
     *
     * @var string
     *
     */
    protected $cgiUrl = "http://aleph.mzk.cz/cgi-bin/rejstriky.pl";
    
    /**
     * Configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;
    
    /**
     * HTTP service
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected $httpService = null;
    
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        $this->config = $configLoader->get('browse');
    }
    
    public function browse($source, $from)
    {
        $source = strtolower($source);
        $index = $this->config[$source]['id'];
        $bases = $this->config['Global']['bases'];
        $params = array('index' => $index, 'query' => $from, 'base' => $bases);
        $answer = $this->httpService->get($this->cgiUrl, $params);
        $xml = simplexml_load_string($answer->getBody());
        $indexes = array();
        $count = 0;
        $next = null;
        foreach ($xml->{'result'} as $result) {
            $ids = array();
            foreach ($result->id as $id) {
                $ids[] = (string) $id;
            }
            $heading = $this->getDisplayText($source, (string) $result->display);
            if ($count < 10) {
                $indexes[] = array('heading' => $heading, 'ids' => $ids, 'count' => count($ids));
            } else {
                $next = (string) $result->sort;
            }
            $count++;
        }
        $result = array('items' => $indexes);
        if (isset($next)) {
            $result['nextQuery'] = array('source' => $source, 'from' => $next);
        }
        return $result;
    }
    
    public function getTypes()
    {
        $types = array();
        foreach ($this->config as $type => $conf) {
            if ($type != 'Global') {
                $types[$type] = $conf['label'];
            }
        }
        return $types;
    }

    /**
     * Set the HTTP service to be used for HTTP requests.
     *
     * @param HttpServiceInterface $service HTTP service
     *
     * @return void
     */
    public function setHttpService(HttpServiceInterface $service)
    {
        $this->httpService = $service;
    }
    
    protected function getDisplayText($source, $text)
    {
        $display = $this->config[$source]['display'];
        $heading = "";
        foreach (str_split($display) as $field) {
            $matches = array();
            $regex = "/\\\$\\\$" . $field . "([^\\$]+)(?::\\\$\\\$)?/";
            if (preg_match($regex, $text, $matches)) {
                $heading .= $matches[1] . " ";
            }
        }
        return $heading;
    }

}