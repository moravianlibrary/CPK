<?php
/**
 * Performance logger
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
 * @package  Search
 * @author   Vaclav Rosecky <xrosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch\Backend\Solr;

/**
 * This class extends the Zend Logging towards streams
 *
 * @category VuFind2
 * @package  Search
 * @author   Vaclav Rosecky <xrosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class PerformanceLogger {

    /**
     * Holds the verbosity level
     *
     * @var string
     */
    protected $file;

    /**
     * Holds the verbosity level
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Constructor
     *
     * @param  string $file File to write to
     * @param  string $baseUrl VuFind base url
     */
    public function __construct($file, $baseUrl)
    {
        $this->file = $file;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Write a message to the log.
     *
     * @param \Zend\Http\Response $response response
     * @param strint $url Solr url
     *
     * @return void
     * @throws \Zend\Log\Exception\RuntimeException
     */
    public function write(\Zend\Http\Response $response, $solrUrl, $time)
    {
        $cache = null;
        $solrTime = null;
        if ($response->getHeaders()->has("X-Cache")) {
            $cache = $response->getHeaders()->get("X-Cache")->getFieldValue();
        }
        if ($response->getHeaders()->has("X-Generated-In")) {
            $solrTime = $response->getHeaders()->get("X-Generated-In")->getFieldValue();
        }
        $url = rtrim($this->baseUrl, '/') . $_SERVER['REQUEST_URI'];
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
        $requestId = isset($_SERVER['HTTP_X_REQUEST_ID']) ? $_SERVER['HTTP_X_REQUEST_ID'] : null;
        $perfEntry = [
            'time'         => date('c'),
            'ip'           => $_SERVER['REMOTE_ADDR'],
            'session'      => session_id(),
            'x_request_id' => $requestId,
            'vufind_url'   => $url,
            'referer'      => $referer,
            'solr_url'     => (string) $solrUrl,
            'query_time'   => $time,
            'solr_time'    => $solrTime,
            'cache'        => $cache,
        ];
        $json = json_encode($perfEntry, JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($this->file, $json, FILE_APPEND);
    }

}