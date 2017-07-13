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
     * Constructor
     *
     * @param  string| $file File to write to
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Write a message to the log.
     *
     * @param array $data data
     *
     * @return void
     * @throws \Zend\Log\Exception\RuntimeException
     */
    public function write(array $data)
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
        file_put_contents($this->file, $json, FILE_APPEND);
    }

}