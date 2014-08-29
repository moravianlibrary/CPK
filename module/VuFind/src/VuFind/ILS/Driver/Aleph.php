<?php
/**
 * Aleph ILS driver
 *
 * PHP version 5
 *
 * Copyright (C) UB/FU Berlin
 *
 * last update: 7.11.2007
 * tested with X-Server Aleph 18.1.
 *
 * TODO: login, course information, getNewItems, duedate in holdings,
 * https connection to x-server, ...
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
 * @author   Christoph Krempe <vufind-tech@lists.sourceforge.net>
 * @author   Alan Rykhus <vufind-tech@lists.sourceforge.net>
 * @author   Jason L. Cooper <vufind-tech@lists.sourceforge.net>
 * @author   Kun Lin <vufind-tech@lists.sourceforge.net>
 * @author   Vaclav Rosecky <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;
use VuFind\Exception\ILS as ILSException;
use Zend\Log\LoggerInterface;
use VuFindHttp\HttpServiceInterface;
use DateTime;
use VuFind\Exception\Date as DateException;

/**
 * Aleph Translator class
 *
 *
 */
interface AlephTranslator
{

    public function tab15Translate($item);

    public function tab40Translate($item);

}

/**
 *
 *
 *
 */
class AlephFixedTranslator implements AlephTranslator
{
    
    public function tab15Translate($item) {
        $z30 = $item->z30;
        return array(
            'opac'         => 'Y',
            'request'      => 'C',
            'desc'         => (string) $z30->{'z30-item-status'},
            'sub_lib_desc' => (string) $z30->{'z30-sub-library'}
        );
    }
    
    public function tab40Translate($item) {
        $z30 = $item->z30;
        $collection = (string) $z30->{'z30-collection'};
        $collection_desc = array('desc' => $collection);
        return $collection_desc;
    }
    
}

/**
 * Aleph Translator Class that uses configuration from Aleph tab*.lng files.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Vaclav Rosecky <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class AlephFileTranslator implements AlephTranslator
{
    /**
     * Constructor
     *
     * @param array $configArray Aleph configuration
     */
    public function __construct($configArray)
    {
        $this->charset = $configArray['util']['charset'];
        $this->table15 = $this->parsetable(
            $configArray['util']['tab15'],
            get_class($this) . "::tab15Callback"
        );
        $this->table40 = $this->parsetable(
            $configArray['util']['tab40'],
            get_class($this) . "::tab40Callback"
        );
        $this->table_sub_library = $this->parsetable(
            $configArray['util']['tab_sub_library'],
            get_class($this) . "::tabSubLibraryCallback"
        );
    }
    
    /**
     * Get a tab15 item status
     *
     * @param string $slc  Sub-library
     * @param string $isc  Item status code
     * @param string $ipsc Item process status code
     *
     * @return string
     */
    public function tab15Translate($item)
    {
        $item_status_code    = (string) $item->{'z30-item-status-code'};
        $item_process_status = (string) $item->{'z30-item-process-status-code'};
        $sub_library_code    = (string) $item->{'z30-sub-library-code'};
        $tab15 = $this->tabSubLibraryTranslate($sub_library_code);
        if ($tab15 == null) {
            throw new ILSException("tab15 is not defined for sub_library_code=" + $sub_library_code);
        }
        $findme = $tab15["tab15"] . "|" . $item_status_code . "|" . $item_process_status;
        $result = $this->table15[$findme];
        if ($result == null) {
            $findme = $tab15["tab15"] . "||" . $item_process_status;
            $result = $this->table15[$findme];
        }
        $result["sub_lib_desc"] = $tab15["desc"];
        return $result;
    }
    
    /**
     * Get a tab40 collection description
     *
     * @param string $collection Collection
     * @param string $sublib     Sub-library
     *
     * @return string
     */
    public function tab40Translate($item)
    {
        $collection_code  = (string) $item->{'z30-collection-code'};
        $sub_library_code = (string) $item->{'z30-sub-library-code'};
        $findme = $collection_code . "|" . $sub_library_code;
        $result = $this->table40[$findme];
        if ($result != null) {
            $findme = $collection_code . "|";
            return $this->table40[$findme];
        }
        return $result;
    }
    
    /**
     * Support method for tab15Translate -- translate a sub-library name
     *
     * @param string $sl Text to translate
     *
     * @return string
     */
    protected function tabSubLibraryTranslate($sl)
    {
        return $this->table_sub_library[$sl];
    }

    /**
     * Parse a table
     *
     * @param string $file     Input file
     * @param string $callback Callback routine for parsing
     *
     * @return string
     */
    public function parsetable($file, $callback)
    {
        $result = array();
        $file_handle = fopen($file, "r, ccs=UTF-8");
        $rgxp = "";
        while (!feof($file_handle) ) {
            $line = fgets($file_handle);
            $line = chop($line);
            if (preg_match("/!!/", $line)) {
                $line = chop($line);
                $rgxp = AlephFileTranslator::regexp($line);
            } if (preg_match("/!.*/", $line) || $rgxp == "" || $line == "") {
            } else {
                $line = str_pad($line, 80);
                $matches = "";
                if (preg_match($rgxp, $line, $matches)) {
                    call_user_func_array(
                        $callback, array($matches, &$result, $this->charset)
                    );
                }
            }
        }
        fclose($file_handle);
        return $result;
    }

    /**
     * tab15 callback (modify $tab15 by reference)
     *
     * @param array  $matches preg_match() return array
     * @param array  &$tab15  result array to generate
     * @param string $charset character set
     *
     * @return void
     */
    public static function tab15Callback($matches, &$tab15, $charset)
    {
        $lib = $matches[1];
        $no1 = $matches[2];
        if ($no1 == "##") {
            $no1="";
        }
        $no2 = $matches[3];
        if ($no2 == "##") {
            $no2="";
        }
        $desc = iconv($charset, 'UTF-8', $matches[5]);
        $key = trim($lib) . "|" . trim($no1) . "|" . trim($no2);
        $tab15[trim($key)] = array(
            "desc" => trim($desc), "loan" => $matches[6], "request" => $matches[8],
            "opac" => $matches[10]
        );
    }

    /**
     * tab40 callback (modify $tab40 by reference)
     *
     * @param array  $matches preg_match() return array
     * @param array  &$tab40  result array to generate
     * @param string $charset character set
     *
     * @return void
     */
    public static function tab40Callback($matches, &$tab40, $charset)
    {
        $code = trim($matches[1]);
        $sub = trim($matches[2]);
        $sub = trim(preg_replace("/#/", "", $sub));
        $desc = trim(iconv($charset, 'UTF-8', $matches[4]));
        $key = $code . "|" . $sub;
        $tab40[trim($key)] = array( "desc" => $desc );
    }

    /**
     * sub-library callback (modify $tab_sub_library by reference)
     *
     * @param array  $matches          preg_match() return array
     * @param array  &$tab_sub_library result array to generate
     * @param string $charset          character set
     *
     * @return void
     */
    public static function tabSubLibraryCallback($matches, &$tab_sub_library,
        $charset
    ) {
        $sublib = trim($matches[1]);
        $desc = trim(iconv($charset, 'UTF-8', $matches[5]));
        $tab = trim($matches[6]);
        $tab_sub_library[$sublib] = array( "desc" => $desc, "tab15" => $tab );
    }

    /**
     * Apply standard regular expression cleanup to a string.
     *
     * @param string $string String to clean up.
     *
     * @return string
     */
    public static function regexp($string)
    {
        $string = preg_replace("/\\-/", ")\\s(", $string);
        $string = preg_replace("/!/", ".", $string);
        $string = preg_replace("/[<>]/", "", $string);
        $string = "/(" . $string . ")/";
        return $string;
    }
}

/**
 *
 * Aleph Restful API does not include bibliographic base in its response, so you
 * have to resolve it when you have two or more bibliographic bases.
 *
 */
interface IdResolver {
    
    /**
     * Resolve ids (add Solr id to items)
     *
     * @param array $items   items to resolve
     */
    public function resolveIds(&$items);
    
}

/**
 *
 * FixedIdResolver - used when you have only one bibliographic base, so ID
 * in solr is not prefixed with bibliographic base so you can keep the returned id
 * as is.
 *
 */
class FixedIdResolver implements IdResolver {
    
    public function resolveIds(&$items) {
        return $items;
    }
    
}

/**
 * SolrIdResolver - resolve bibliographic base against solr.
 *
 */
class SolrIdResolver implements IdResolver {
    
    protected $solrQueryField = 'availability_id_str';
    
    protected $itemIdentifier = 'adm_id';
    
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
        $docs = $this->searchService->search('Solr', $group, 0, sizeof($ids));
        foreach ($docs->getRecords() as $record) {
            $fields = $record->getRawData();
            if (isset($fields[$this->solrQueryField])) {
                if (is_array($fields[$this->solrQueryField])) {
                    foreach ($fields[$this->solrQueryField] as $value) {
                        if (in_array($value, $ids)) {
                            $results[$value] = $record->getUniqueID();
                        }
                    }
                } else {
                    $value = $fields[$this->solrQueryField];
                    if (in_array($value, $ids)) {
                        $results[$value] = $record->getUniqueID();
                    }
                }
            }
        }
        return $results;
    }
        
}

/**
 * Resolve identifiers against XServer using find function.
 *
 */
class XServerIdResolver implements IdResolver {
    
    /**
     *
     * @var AlephWebServices
     */
    protected $alephWebService;
    
    /**
     *
     * @var array
     */
    protected $bib;
    
    /**
     *
     * @param AlephWebServices $service
     * @param array $config
     */
    public function __construct(AlephWebServices $service, $config)
    {
        $this->alephWebService = $service;
        $this->bib = explode(',', $config['bib']);
    }
    
    public function resolveIds(&$recordsToResolve)
    {
        foreach ($recordsToResolve as &$record) {
            $id = $this->barcodeToID($record['barcode']);
            if ($id != null) {
                $record['id'] = $id;
            }
        }
    }
    
    protected function barcodeToID($bar)
    {
        if (!$this->alephWebService->isXServerEnabled()) {
            return null;
        }
        foreach ($this->bib as $base) {
            try {
                $xml = $this->alephWebService->doXRequest(
                    "find", array("base" => $base, "request" => "BAR=$bar"), false
                );
                $docs = (int) $xml->{"no_records"};
                if ($docs == 1) {
                    $set = (string) $xml->{"set_number"};
                    $result = $this->alephWebService->doXRequest(
                        "present", array("set_number" => $set, "set_entry" => "1"),
                        false
                    );
                    $id = $result->xpath('//doc_number/text()');
                    if (count($this->bib)==1) {
                        return (string) $id[0];
                    } else {
                        return $base . "-" . $id[0];
                    }
                }
            } catch (\Exception $ex) { // not found
            }
        }
        return null;
    }
}

/**
 * ILS Exception
 *
 * @category VuFind
 * @package  Exceptions
 * @author   Vaclav Rosecky <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class AlephRestfulException extends ILSException
{
    /**
     * XML response (false for none)
     *
     * @var string|bool
     */
    protected $xmlResponse = false;

    /**
     * Attach an XML response to the exception
     *
     * @param string $body XML
     *
     * @return void
     */
    public function setXmlResponse($body)
    {
        $this->xmlResponse = $body;
    }

    /**
     * Return XML response (false if none)
     *
     * @return string|bool
     */
    public function getXmlResponse()
    {
        return $this->xmlResponse;
    }
}

/**
 * Auxiliary class for calling Aleph web services (both XServer and REST DLF API).
 *
 */
class AlephWebServices {
    
    /**
     * Aleph server host name
     *
     * @var string
     */
    protected $host;
    
    /**
     * Username for Xserver calls
     *
     * @var string
     */
    protected $wwwuser;
    
    /**
     * Username for Xserver calls
     *
     * @var string
     */
    protected $wwwpasswd;
    
    /**
     * Port number on which REST DLF API is running
     *
     */
    protected $dlfport;
    
    /**
     * Is XServer API enabled?
     *
     */
    protected $xserver_enabled;
    
    /**
     * HTTP service
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected $httpService = null;
    
    /**
     * Logger object for debug info (or false for no debugging).
     *
     * @var LoggerInterface|bool
     */
    protected $logger = false;
    
    /**
     *
     * @param array $config
     */
    public function __construct()
    {
    }
    
    public function init($config)
    {
        $this->host = $config['host'];
        $this->xserver_enabled = false;
        if (isset($config['wwwuser']) && isset($config['wwwpasswd'])) {
            $this->wwwuser = $config['wwwuser'];
            $this->wwwpasswd = $config['wwwpasswd'];
            $this->xserver_enabled = true;
        }
        $this->debug_enabled = false;
        if (isset($config['debug']) && $config['debug']) {
            $this->debug_enabled = true;
        }
        $this->dlfport = $config['dlfport'];
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
    
    /**
     * Set the logger
     *
     * @param LoggerInterface $logger Logger to use.
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    public function isXServerEnabled()
    {
        return $this->xserver_enabled;
    }
    
    /**
     * Perform an XServer request.
     *
     * @param string $op     Operation
     * @param array  $params Parameters
     * @param bool   $auth   Include authentication?
     *
     * @return SimpleXMLElement
     */
    public function doXRequest($op, $params, $auth=false)
    {
        if (!$this->xserver_enabled) {
            throw new \Exception(
                'Call to doXRequest without X-Server configuration in Aleph.ini'
            );
        }
        $url = "http://$this->host/X?op=$op";
        $url = $this->appendQueryString($url, $params);
        if ($auth) {
            $url = $this->appendQueryString(
                $url, array(
                    'user_name' => $this->wwwuser,
                    'user_password' => $this->wwwpasswd
                )
            );
        }
        $result = $this->doHTTPRequest($url);
        if ($result->error) {
            if ($this->debug_enabled) {
                $this->debug(
                    "XServer error, URL is $url, error message: $result->error."
                );
            }
            throw new ILSException("XServer error: $result->error.");
        }
        return $result;
    }

    public function doXRequestUsingPost($op, $params, $auth=true)
    {
        $url = "http://$this->host/X?";
        $body = '';
        $sep = '';
        $params['op'] = $op;
        if ($auth) {
            $params['user_name'] = $this->wwwuser;
            $params['user_password'] = $this->wwwpasswd;
        }
        foreach ($params as $key => $value) {
            $body .= $sep . $key . '=' . urlencode($value);
            $sep = '&';
        }
        $result = $this->doHTTPRequest($url, null, 'POST', $body);
        if ($result->error) {
            if ($op == 'update-doc' && preg_match('/Document: [0-9]+ was updated successfully\\./', trim($result->error)) === 1) {
                return $result;
            }
            if ($this->debug_enabled) {
                $this->debug("XServer error, URL is $url, error message: $result->error.");
            }
            throw new Exception("XServer error: $result->error.");
        }
        return $result;
    }

    /**
     * Perform a RESTful DLF request.
     *
     * @param array  $path_elements URL path elements
     * @param array  $params        GET parameters (null for none)
     * @param string $method        HTTP method
     * @param string $body          HTTP body (for PUT or POST HTTP method)
     *
     * @return SimpleXMLElement
     */
    public function doRestDLFRequest($path_elements, $params = null,
        $method='GET', $body = null
    ) {
        $path = '';
        foreach ($path_elements as $path_element) {
            $path .= $path_element . "/";
        }
        $url = "http://$this->host:$this->dlfport/rest-dlf/" . $path;
        //$url = $this->appendQueryString($url, $params);
        $result = $this->doHTTPRequest($url, $params, $method, $body);
        $replyCode = (string) $result->{'reply-code'};
        if ($replyCode != "0000") {
            $replyText = (string) $result->{'reply-text'};
            $this->logger->err(
                "DLF request failed", array(
                    'url' => $url, 'reply-code' => $replyCode,
                    'reply-message' => $replyText
                )
            );
            $ex = new AlephRestfulException($replyText, $replyCode);
            $ex->setXmlResponse($result);
            throw $ex;
        }
        return $result;
    }
    
    /**
     * Add values to an HTTP query string.
     *
     * @param string $url    URL so far
     * @param array  $params Parameters to add
     *
     * @return string
     */
    public function appendQueryString($url, $params)
    {
        if ($params == null) {
            return $url; // nothing to append
        }
        $sep = (strpos($url, "?") === false)?'?':'&';
        foreach ($params as $key => $value) {
            $url.= $sep . $key . "=" . urlencode($value);
            $sep = "&";
        }
        return $url;
    }
    
    /**
     * Perform an HTTP request.
     *
     * @param string $url    URL of request
     * @param array  $param  query parameters to add to URL
     * @param string $method HTTP method
     * @param string $body   HTTP body (null for none)
     *
     * @return SimpleXMLElement
     */
    public function doHTTPRequest($url, $params=null, $method='GET', $body = null)
    {
        if ($this->debug_enabled) {
            $fullUrl = $this->appendQueryString($url, $params);
            $this->debug("URL: '$fullUrl'");
        }
        
        if ($params == null) {
            $params = array();
        }

        $result = null;
        try {
            if ($method == 'GET') {
                $result = $this->httpService->get($url, $params);
            } else if ($method == 'POST') {
                $url = $this->appendQueryString($url, $params);
                $result = $this->httpService->post($url, $body);
            } else {
                $client = $this->httpService->createClient($url);
                $client->setMethod($method);
                if ($body != null) {
                    $client->setRawBody($body);
                }
                $result = $client->send();
            }
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        if (!$result->isSuccess()) {
            throw new ILSException('HTTP error');
        }
        $answer = $result->getBody();
        if ($this->debug_enabled) {
            $this->debug("url: $url response: $answer");
        }
        $answer = str_replace('xmlns=', 'ns=', $answer);
        $result = simplexml_load_string($answer);
        if (!$result) {
            if ($this->debug_enabled) {
                $this->debug("XML is not valid, URL: $url");
            }
            throw new ILSException(
                "XML is not valid, URL: $url method: $method answer: $answer."
            );
        }
        return $result;
    }
    
    /**
     * Show a debug message.
     *
     * @param string $msg Debug message.
     *
     * @return void
     */
    protected function debug($msg)
    {
        if ($this->logger) {
            $this->logger->debug($msg);
        }
    }
    
}

/**
 * Aleph ILS driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Christoph Krempe <vufind-tech@lists.sourceforge.net>
 * @author   Alan Rykhus <vufind-tech@lists.sourceforge.net>
 * @author   Jason L. Cooper <vufind-tech@lists.sourceforge.net>
 * @author   Kun Lin <vufind-tech@lists.sourceforge.net>
 * @author   Vaclav Rosecky <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class Aleph extends AbstractBase implements \Zend\Log\LoggerAwareInterface,
    \VuFindHttp\HttpServiceAwareInterface
{
    /**
     * Duedate configuration
     *
     * @var array
     */
    protected $duedates = false;

    /**
     * Cache manager
     *
     * @var \VuFind\Cache\Manager
     */
    protected $cacheManager;

    /**
     * Logger object for debug info (or false for no debugging).
     *
     * @var LoggerInterface|bool
     */
    protected $logger = false;

    /**
     * HTTP service
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected $httpService = null;

    /**
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter = null;
    
    /**
     * Search service (used for lookups by barcode number)
     *
     */
    protected $searchService = null;
    
    /**
     * Resolver for translation of bibliographic ids, used in a case
     * of more bibliographic bases
     *
     * @var \VuFind\ILS\Driver\IdResolver
     */
    protected $idResolver = null;
    
    /**
     * Aleph web services
     *
     * @var \VuFind\ILS\Driver\AlephWebServices
     */
    protected $alephWebService = null;
    
    /**
     * Translation of statuses (used for hiding items and translation of statuses)
     *
     * @var \VuFind\ILS\Driver\AlephTranslator
     */
    protected $translator = false;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter
     * @param \VuFind\Cache\Manager  $cacheManager  Cache manager (optional)
     */
    public function __construct(\VuFind\Date\Converter $dateConverter,
        \VuFind\Cache\Manager $cacheManager = null, \VuFindSearch\Service $searchService = null
    ) {
        $this->dateConverter = $dateConverter;
        $this->cacheManager = $cacheManager;
        $this->searchService = $searchService;
        $this->alephWebService = new AlephWebServices();
    }

    /**
     * Set the logger
     *
     * @param LoggerInterface $logger Logger to use.
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->alephWebService->setLogger($logger);
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
        $this->alephWebService->setHttpService($service);
    }

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        // Validate config
        $required = array(
            'host', 'bib', 'useradm', 'admlib', 'dlfport', 'available_statuses'
        );
        foreach ($required as $current) {
            if (!isset($this->config['Catalog'][$current])) {
                throw new ILSException("Missing Catalog/{$current} config setting.");
            }
        }
        if (!isset($this->config['sublibadm'])) {
            throw new ILSException('Missing sublibadm config setting.');
        }
        $this->alephWebService->init($this->config['Catalog']);
        $this->bib = explode(',', $this->config['Catalog']['bib']);
        $this->useradm = $this->config['Catalog']['useradm'];
        $this->admlib = $this->config['Catalog']['admlib'];
        $this->sublibadm = $this->config['sublibadm'];
        if (isset($this->config['duedates'])) {
            $this->duedates = $this->config['duedates'];
        }
        $this->available_statuses
            = explode(',', $this->config['Catalog']['available_statuses']);
        $this->quick_availability
            = isset($this->config['Catalog']['quick_availability'])
            ? $this->config['Catalog']['quick_availability'] : false;
        if (isset($this->config['util']['tab40'])
            && isset($this->config['util']['tab15'])
            && isset($this->config['util']['tab_sub_library'])
        ) {
            if (isset($this->config['Cache']['type'])
                && null !== $this->cacheManager
            ) {
                $cache = $this->cacheManager
                    ->getCache($this->config['Cache']['type']);
                $this->translator = $cache->getItem('alephTranslator');
            }
            if ($this->translator == false) {
                $this->translator = new AlephFileTranslator($this->config);
                if (isset($cache)) {
                    $cache->setItem('alephTranslator', $this->translator);
                }
            }
        } else {
            $this->translator = new AlephFixedTranslator();
        }
        if (isset($this->config['Catalog']['preferred_pick_up_locations'])) {
            $this->preferredPickUpLocations = explode(
                ',', $this->config['Catalog']['preferred_pick_up_locations']
            );
        }
        if (isset($this->config['Catalog']['default_patron'])) {
            $this->defaultPatronId = $this->config['Catalog']['default_patron'];
        }
        $idResolverType = 'fixed';
        if (isset($this->config['IdResolver']['type'])) {
            $idResolverType = $this->config['IdResolver']['type'];
        }
        if ($idResolverType == 'fixed') {
            $this->idResolver = new FixedIdResolver();
        } else if ($idResolverType == 'solr') {
            $this->idResolver = new SolrIdResolver($this->searchService, $this->config);
        } else if ($idResolverType == 'xserver') {
            $this->idResolver = new XServerIdResolver($this->alephWebService, $this->config);
        } else {
            throw new ILSException('Unsupported Catalog[IdResolver][type]:' .
                 $idResolverType .', valid values are fixed, solr and xserver.');
        }
        
        if (isset($this->config['ILL']['hidden_statuses'])) {
            $this->IllHiddenStatuses = explode(',', $this->config['ILL']['hidden_statuses']);
        }
        if (isset($this->config['ILL']['default_ill_unit'])) {
            $this->defaultIllUnit = $this->config['ILL']['default_ill_unit'];
        }
        if (isset($this->config['ILL']['default_pickup_location'])) {
            $this->defaultIllPickupPlocation = $this->config['ILL']['default_pickup_location'];
        }
    }

    /**
     * Convert an ID string into an array of library and ID within the library.
     *
     * @param string $id ID to parse.
     *
     * @return array
     */
    protected function parseId($id)
    {
        if (count($this->bib) == 1) {
            return array($this->bib[0], $id);
        } else {
            return explode('-', $id);
        }
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @throws ILSException
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        $statuses = $this->getHolding($id);
        foreach ($statuses as &$status) {
            $status['status']
                = ($status['availability'] == 1) ? 'available' : 'unavailable';
        }
        return $statuses;
    }

    /**
     * Support method for getStatuses -- load ID information from a particular
     * bibliographic library.
     *
     * @param string $bib Library to search
     * @param array  $ids IDs to search within library
     *
     * @return array
     *
     * Description of AVA tag:
     * http://igelu.org/wp-content/uploads/2011/09/Staff-vs-Public-Data-views.pdf
     * (page 28)
     *
     * a  ADM code - Institution Code
     * b  Sublibrary code - Library Code
     * c  Collection (first found) - Collection Code
     * d  Call number (first found)
     * e  Availability status  - If it is on loan (it has a Z36), if it is on hold
     *    shelf (it has  Z37=S) or if it has a processing status.
     * f  Number of items (for entire sublibrary)
     * g  Number of unavailable loans
     * h  Multi-volume flag (Y/N) If first Z30-ENUMERATION-A is not blank or 0, then
     *    the flag=Y, otherwise the flag=N.
     * i  Number of loans (for ranking/sorting)
     * j  Collection code
     */
    public function getStatusesX($bib, $ids)
    {
        $doc_nums = "";
        $sep = "";
        foreach ($ids as $id) {
            $doc_nums .= $sep . $id;
            $sep = ",";
        }
        $xml = $this->alephWebService->doXRequest(
            "publish_avail", array('library' => $bib, 'doc_num' => $doc_nums), false
        );
        $holding = array();
        foreach ($xml->xpath('/publish-avail/OAI-PMH') as $rec) {
            $identifier = $rec->xpath(".//identifier/text()");
            $id = "$bib" . "-"
                . substr($identifier[0], strrpos($identifier[0], ':') + 1);
            $temp = array();
            foreach ($rec->xpath(".//datafield[@tag='AVA']") as $datafield) {
                $status = $datafield->xpath('./subfield[@code="e"]/text()');
                $location = $datafield->xpath('./subfield[@code="a"]/text()');
                $signature = $datafield->xpath('./subfield[@code="d"]/text()');
                $availability
                    = ($status[0] == 'available' || $status[0] == 'check_holdings');
                $reserve = true;
                $temp[] = array(
                    'id' => $id,
                    'availability' => $availability,
                    'status' => (string) $status[0],
                    'location' => (string) $location[0],
                    'signature' => (string) $signature[0],
                    'reserve' => $reserve,
                    'callnumber' => (string) $signature[0]
                );
            }
            $holding[] = $temp;
        }
        return $holding;
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $idList The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array        An array of getStatus() return values on success.
     */
    public function getStatuses($idList)
    {
        if (!$this->alephWebService->isXServerEnabled()) {
            if (!$this->quick_availability) {
                return array();
            }
            $result = array();
            foreach ($idList as $id) {
                $items = $this->getStatus($id);
                $result[] = $items;
            }
            return $result;
        }
        $ids = array();
        $holdings = array();
        foreach ($idList as $id) {
            list($bib, $sys_no) = $this->parseId($id);
            $ids[$bib][] = $sys_no;
        }
        foreach ($ids as $key => $values) {
            $holds = $this->getStatusesX($key, $values);
            foreach ($holds as $hold) {
                $holdings[] = $hold;
            }
        }
        return $holdings;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null, $filters = array())
    {
        $holding = array();
        list($bib, $sys_no) = $this->parseId($id);
        $resource = $bib . $sys_no;
        $params = array();
        if (!empty($filters)) {
            foreach ($filters as $id => $value) {
                if ($id == 'hide_loans' && $value='true') {
                    $params['loaned'] = 'NO';
                } else {
                    $params[$id] = $value;
                }
            }
        }
        $params['view'] = 'full';
        if (!empty($patron['id'])) {
            $params['patron'] = $patron['id'];
        } else if (isset($this->defaultPatronId)) {
            $params['patron'] = $this->defaultPatronId;
        }
        $xml = $this->alephWebService->doRestDLFRequest(array('record', $resource, 'items'), $params);
        foreach ($xml->{'items'}->{'item'} as $item) {
            $item_status = $this->translator->tab15Translate($item);
            if ($item_status['opac'] != 'Y') {
                continue;
            }
            $availability = false;
            $reserve = ($item_status['request'] == 'C')?'N':'Y';
            $z30 = $item->z30;
            $collection = (string) $z30->{'z30-collection'};
            $collection_desc = array('desc' => $collection);
            $collection_desc = $this->translator->tab40Translate($item);
            $sub_library_code = (string) $item->{'z30-sub-library-code'};
            $requested = false;
            $duedate = null;
            $addLink = false;
            $status = (string) $item->{'status'};
            if (in_array($status, $this->available_statuses)) {
                $availability = true;
            }
            if ($item_status['request'] == 'Y' && $availability == false) {
                $addLink = true;
            }
            $holdType = 'hold';
            if (!empty($patron) || isset($this->defaultPatronId)) {
                $hold_request = $item->xpath('info[@type="HoldRequest"]/@allowed');
                if ($hold_request[0] == 'N') {
                    $hold_request = $item->xpath('info[@type="ShortLoan"]/@allowed');
                    if ($hold_request[0] == 'Y') {
                        $holdType = 'shortloan';
                    }
                }
                $addLink = ($hold_request[0] == 'Y');
            }
            $matches = array();
            if (preg_match(
                "/([0-9]*\\/[a-zA-Z]*\\/[0-9]*);([a-zA-Z ]*)/", $status, $matches
            )) {
                $duedate = $this->parseDate($matches[1]);
                $requested = (trim($matches[2]) == "Requested");
            } else if (preg_match(
                "/([0-9]*\\/[a-zA-Z]*\\/[0-9]*)/", $status, $matches
            )) {
                $duedate = $this->parseDate($matches[1]);
            }
            // process duedate_status
            $duedate_status = $item_status['desc'];
            if ($availability && $this->duedates) {
                foreach ($this->duedates as $key => $value) {
                    if (preg_match($value, $item_status['desc'])) {
                        $duedate_status = $key;
                        break;
                    }
                }
            } else if (!$availability && ($status == "On Hold" || $status == "Requested")) {
                $duedate_status = "requested";
            }
            $item_id = $item->attributes()->href;
            $item_id = substr($item_id, strrpos($item_id, '/') + 1);
            $note    = (string) $z30->{'z30-note-opac'};
            $holding[] = array(
                'id'                => $id,
                'item_id'           => $item_id,
                'availability'      => $availability,
                'status'            => (string) $item_status['desc'],
                'location'          => $sub_library_code,
                'reserve'           => 'N',
                'callnumber'        => (string) $z30->{'z30-call-no'},
                'duedate'           => (string) $duedate,
                'number'            => (string) $z30->{'z30-inventory-number'},
                'barcode'           => (string) $z30->{'z30-barcode'},
                'description'       => (string) $z30->{'z30-description'},
                'notes'             => ($note == null) ? null : array($note),
                'is_holdable'       => true,
                'addLink'           => $addLink,
                'holdtype'          => $holdType,
                /* below are optional attributes*/
                'duedate_status'    => $status,
                'collection'        => (string) $collection,
                'collection_desc'   => (string) $collection_desc['desc'],
                'callnumber_second' => (string) $z30->{'z30-call-no-2'},
                'sub_lib_desc'      => (string) $item_status['sub_lib_desc'],
                'no_of_loans'       => (string) $z30->{'$no_of_loans'},
                'requested'         => (string) $requested
            );
        }
        return $holding;
    }
    
    public function getHoldingFilters($bibId) {
        list($bib, $sys_no) = $this->parseId($bibId);
        $resource = $bib . $sys_no;
        $years = array();
        $volumes = array();
        try {
            $xml = $this->alephWebService->doRestDLFRequest(array('record', $resource, 'filters'));
        } catch (Exception $ex) {
            return array();
        }
        if (isset($xml->{'record-filters'})) {
            if (isset($xml->{'record-filters'}->{'years'})) {
                foreach ($xml->{'record-filters'}->{'years'}->{'year'} as $year) {
                    $years[] = (string) $year;
                }
            }
            if (isset($xml->{'record-filters'}->{'volumes'})) {
                foreach ($xml->{'record-filters'}->{'volumes'}->{'volume'} as $volume) {
                    $volumes[] = (string) $volume;
                }
            }
        }
        return array('year' => $years, 'volume' => $volumes, 'hide_loans' => array(true, false));
    }

    /**
     * Get Patron Transaction History
     *
     * @param array $user The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array      Array of the patron's transactions on success.
     */
    public function getMyHistory($user)
    {
        return $this->getMyTransactions($user, true);
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $user    The patron array from patronLogin
     * @param bool  $history Include history of transactions (true) or just get
     * current ones (false).
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($user, $history=false)
    {
        $userId = $user['id'];
        $transList = array();
        $params = array("view" => "full");
        if ($history) {
            $params["type"] = "history";
        }
        $xml = $this->alephWebService->doRestDLFRequest(
            array('patron', $userId, 'circulationActions', 'loans'), $params
        );
        foreach ($xml->xpath('//loan') as $item) {
            $z36 = $item->z36;
            $z13 = $item->z13;
            $z30 = $item->z30;
            $group = $item->xpath('@href');
            $group = substr(strrchr($group[0], "/"), 1);
            if (strpos($group, '?') !== false) {
                $group = substr($group, 0, strpos($group, '?'));
            }
            $renew = $item->xpath('@renew');
            $renewable = ($renew[0] == 'Y');
            $docno = (string) $z36->{'z36-doc-number'};
            $itemseq = (string) $z36->{'z36-item-sequence'};
            $seq = (string) $z36->{'z36-sequence'};
            $location = (string) $z36->{'z36_pickup_location'};
            $reqnum = (string) $z36->{'z36-doc-number'}
                . (string) $z36->{'z36-item-sequence'}
                . (string) $z36->{'z36-sequence'};
            $due = $returned = null;
            if ($history) {
                $due = $item->z36h->{'z36h-due-date'};
                $returned = $item->z36h->{'z36h-returned-date'};
            } else {
                $due = (string) $z36->{'z36-due-date'};
            }
            $loaned = (string) $z36->{'z36-loan-date'};
            $title = (string) $z13->{'z13-title'};
            $author = (string) $z13->{'z13-author'};
            $isbn = (string) $z13->{'z13-isbn-issn'};
            $barcode = (string) $z30->{'z30-barcode'};
            $adm_id = (string) $z30->{'z30-doc-number'};
            $id = (string) $z13->{'z13-doc-number'};
            /* Check if item is loaned after due date */
            $currentDate = strtotime(date('d.m.Y'));
            $dueDate = strtotime(
                $this->dateConverter->convertFromDisplayDate(
                "d.m.Y", $this->parseDate($due)));
            $returnInDays = ($dueDate - $currentDate) / (60*60*24);
            $fine = (string) $item->{'fine'};
            $item = array(
                'id'        => $id,
                'adm_id'    => $adm_id,
                'item_id'   => $group,
                'location'  => $location,
                'title'     => $title,
                'author'    => $author,
                'isbn'      => array($isbn),
                'reqnum'    => $reqnum,
                'barcode'   => $barcode,
                'duedate'   => $this->parseDate($due),
                'returned'  => $this->parseDate($returned),
                //'holddate'  => $holddate,
                //'delete'    => $delete,
                'renewable' => $renewable,
                'fine'      => $fine,
                //'create'    => $this->parseDate($create)
            );
            if ($returnInDays < 0 && !$history) {
                $item['dueStatus'] = 'overdue';
            }
            $transList[] = $item;
        }
        $this->idResolver->resolveIds($transList);
        return $transList;
    }

    /**
     * Get Renew Details
     *
     * In order to renew an item, Voyager requires the patron details and an item
     * id. This function returns the item id as a string which is then used
     * as submitted form data in checkedOut.php. This value is then extracted by
     * the RenewMyItems function.
     *
     * @param array $details An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getRenewDetails($details)
    {
        return $details['item_id'];
    }

    /**
     * Renew My Items
     *
     * Function for attempting to renew a patron's items.  The data in
     * $renewDetails['details'] is determined by getRenewDetails().
     *
     * @param array $details An array of data required for renewing items
     * including the Patron ID and an array of renewal IDS
     *
     * @return array              An array of renewal information keyed by item ID
     */
    public function renewMyItems($details)
    {
        $patron = $details['patron'];
        $result = array();
        foreach ($details['details'] as $id) {
            try {
                $this->alephWebService->doRestDLFRequest(
                    array(
                        'patron', $patron['id'], 'circulationActions', 'loans', $id
                    ),
                    null, 'POST', null
                );
                $result[$id] = array('success' => true);
            } catch (AlephRestfulException $ex) {
                $result[$id] = array(
                    'success' => false, 'sysMessage' => $ex->getMessage()
                );
            }
        }
        return array('blocks' => false, 'details' => $result);
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array      Array of the patron's holds on success.
     */
    public function getMyHolds($user)
    {
        $userId = $user['id'];
        $holdList = array();
        $xml = $this->alephWebService->doRestDLFRequest(
            array('patron', $userId, 'circulationActions', 'requests', 'holds'),
            array('view' => 'full')
        );
        foreach ($xml->xpath('//hold-request') as $item) {
            $z37 = $item->z37;
            $z13 = $item->z13;
            $z30 = $item->z30;
            $delete = $item->xpath('@delete');
            $href = $item->xpath('@href');
            $item_id = substr($href[0], strrpos($href[0], '/') + 1);
            if ((string) $z37->{'z37-request-type'} == "Hold Request" || true) {
                $type = "hold";
                $seq = null;
                $item_status = preg_replace("/\s[\s]+/", " ", (string) $item->{'status'});
                if (preg_match("/Waiting in position ([0-9]+) in queue; current due date ([0-9]+\/[a-z|A-Z]+\/[0-9])+/", $item_status, $matches)) {
                    $seq = $matches[1];
                }
                $location = (string) $z37->{'z37-pickup-location'};
                $reqnum = (string) $z37->{'z37-doc-number'}
                    . (string) $z37->{'z37-item-sequence'}
                    . (string) $z37->{'z37-sequence'};
                $expire = (string) $z37->{'z37-end-request-date'};
                $create = (string) $z37->{'z37-open-date'};
                $holddate = (string) $z37->{'z37-hold-date'};
                $title = (string) $z13->{'z13-title'};
                $author = (string) $z13->{'z13-author'};
                $isbn = (string) $z13->{'z13-isbn-issn'};
                $barcode = (string) $z30->{'z30-barcode'};
                $status = (string) $z37->{'z37-status'};
                if ($holddate == "00000000") {
                    $holddate = null;
                } else {
                    $holddate = $this->parseDate($holddate);
                }
                $delete = ($delete[0] == "Y");
                $id = (string) $z13->{'z13-doc-number'};
                $adm_id = (string) $z30->{'z30-doc-number'};
                $holdList[] = array(
                    'id'       => $id,
                    'adm_id'   => $adm_id,
                    'type'     => $type,
                    'item_id'  => $item_id,
                    'location' => $location,
                    'title'    => $title,
                    'author'   => $author,
                    'isbn'     => array($isbn),
                    'reqnum'   => $reqnum,
                    'barcode'  => $barcode,
                    'expire'   => $this->parseDate($expire),
                    'holddate' => $holddate,
                    'delete'   => $delete,
                    'create'   => $this->parseDate($create),
                    'status'   => $status,
                    'position' => $seq,
                );
            }
        }
        $this->idResolver->resolveIds($holdList);
        return $holdList;
    }

    public function getMyShortLoanRequests($patron)
    {
        $xml = $this->alephWebService->doRestDLFRequest(array('patron', $patron['id'], 'circulationActions',
            'requests', 'bookings'), array("view" => "full"));
        $results = array();
        foreach ($xml->xpath('//booking-request') as $item) {
            $delete = $item->xpath('@delete');
            $href = $item->xpath('@href');
            $item_id = substr($href[0], strrpos($href[0], '/') + 1);
            $z13 = $item->z13;
            $z37 = $item->z37;
            $z30 = $item->z30;
            $barcode = (string) $z30->{'z30-barcode'};
            $startDate = $z37->{'z37-booking-start-date'};
            $startTime = $z37->{'z37-booking-start-hour'};
            $endDate = $z37->{'z37-booking-end-date'};
            $endTime = $z37->{'z37-booking-end-hour'};
            $callnumber = $z30->{'z30-call-no'};
            $start = substr($startDate[0], 6, 2) . '. ' . substr($startDate[0], 4, 2) . '. ' . substr($startDate[0], 0, 4)
            . ' ' . substr($startTime[0], 0, 2) . ':' .  substr($startTime[0], 2, 2);
            $end = substr($endDate[0], 6, 2) . '. ' . substr($endDate[0], 4, 2) . '. ' . substr($endDate[0], 0, 4)
            . ' ' . substr($endTime[0], 0, 2) . ':' .  substr($endTime[0], 2, 2);
            $delete = ($delete[0] == "Y");
            $id = (string) $z13->{'z13-doc-number'};
            $adm_id = (string) $z30->{'z30-doc-number'};
            $sortKey = (string) $startDate[0] . $item_id;
            $results[$sortKey] = array(
                'id'         => $id, //$this->barcodeToID($barcode),
                'adm_id'     => $adm_id,
                'start'      => $start,
                'end'        => $end,
                'delete'     => $delete,
                'item_id'    => $item_id,
                'barcode'    => $barcode,
                'callnumber' => $callnumber
            );
        }
        ksort($results);
        $results = array_values($results);
        $this->idResolver->resolveIds($results);
        return $results;
    }

    public function getCancelShortLoanRequestDetails($details)
    {
        if ($details['delete']) {
            return $details['item_id'];
        } else {
            return null;
        }
    }

    /**
     * Get Cancel Hold Details
     *
     * @param array $holdDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getCancelHoldDetails($holdDetails)
    {
        if ($holdDetails['delete']) {
            return $holdDetails['item_id'];
        } else {
            return null;
        }
    }

    public function getCancelBookingDetails($bookingsDetails)
    {
        return $this->getCancelHoldDetails($bookingsDetails);
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold or recall on a particular item. The
     * data in $cancelDetails['details'] is determined by getCancelHoldDetails().
     *
     * @param array $details An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     */
    public function cancelHolds($details)
    {
        $patron = $details['patron'];
        $patronId = $patron['id'];
        $count = 0;
        $statuses = array();
        foreach ($details['details'] as $id) {
            $result = $this->alephWebService->doRestDLFRequest(
                array(
                    'patron', $patronId, 'circulationActions', 'requests', 'holds',
                    $id
                ), null, "DELETE"
            );
            $reply_code = $result->{'reply-code'};
            if ($reply_code != "0000") {
                $message = $result->{'del-pat-hold'}->{'note'};
                if ($message == null) {
                    $message = $result->{'reply-text'};
                }
                $statuses[$id] = array(
                    'success' => false, 'status' => 'cancel_hold_failed',
                    'sysMessage' => (string) $message
                );
            } else {
                $count++;
                $statuses[$id]
                    = array('success' => true, 'status' => 'cancel_hold_ok');
            }
        }
        $statuses['count'] = $count;
        return $statuses;
    }

    public function cancelShortLoanRequest($details)
    {
        $patron = $details['patron'];
        $patronId = $patron['id'];
        $count = 0;
        $statuses = array();
        foreach ($details['details'] as $id) {
            try {
                $result = $this->alephWebService->doRestDLFRequest(array('patron', $patronId, 'circulationActions',
                    'requests', 'bookings', $id), null, "DELETE");
            } catch (Exception $ex) {
                $statuses[$id] = array('success' => false, 'status' => 'cancel_hold_failed', 'sysMessage' => (string) $ex->getMessage());
            }
            $count++;
            $statuses[$id] = array('success' => true, 'status' => 'cancel_hold_ok');
        }
        $statuses['count'] = $count;
        return $statuses;
    }

    public function getAccruedOverdue($user)
    {
        $sum = 0;
        $xml = $this->alephWebService->doRestDLFRequest(
            array('patron', $user['id'], 'circulationActions'), null
        );
        foreach ($xml->circulationActions->institution as $institution) {
            $cashNote = (string) $institution->note;
            $matches = array();
            if (preg_match("/Please note that there is an additional accrued overdue items fine of: (\d+\.?\d*)\./", $cashNote, $matches) === 1) {
                $sum = $matches[1];
            }
        }
        return $sum;
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return mixed      Array of the patron's fines on success.
     */
    public function getMyFines($user)
    {
        $finesList = array();
        $finesListSort = array();

        $xml = $this->alephWebService->doRestDLFRequest(
            array('patron', $user['id'], 'circulationActions', 'cash'),
            array("view" => "full")
        );

        foreach ($xml->xpath('//cash') as $item) {
            $z31 = $item->z31;
            $z13 = $item->z13;
            $z30 = $item->z30;
            $delete = $item->xpath('@delete');
            $title = (string) $z13->{'z13-title'};
            $description = (string) $z31->{'z31-description'};
            $transactiondate = date('d-m-Y', strtotime((string) $z31->{'z31-date'}));
            $transactiontype = (string) $z31->{'z31-credit-debit'};
            $id = (string) $z13->{'z13-doc-number'};
            $barcode = (string) $z30->{'z30-barcode'};
            $checkout = (string) $z31->{'z31-date'};
            $adm_id = (string) $z30->{'z30-doc-number'};
            $id = (string) $z13->{'z13-doc-number'};
            if ($transactiontype=="Debit") {
                $mult=-100;
            } elseif ($transactiontype=="Credit") {
                $mult=100;
            }
            $amount
                = (float)(preg_replace("/[\(\)]/", "", (string) $z31->{'z31-sum'}))
                * $mult;
            $cashref = (string) $z31->{'z31-sequence'};
            $cashdate = date('d-m-Y', strtotime((string) $z31->{'z31-date'}));
            $balance = 0;

            $finesListSort[$cashref]  = array(
                    "title"    => $title,
                    "barcode"  => $barcode,
                    "amount"   => $amount,
                    "fine"     => $description,
                    "transactiondate" => $transactiondate,
                    "transactiontype" => $transactiontype,
                    "checkout" => $this->parseDate($checkout),
                    "balance"  => $balance,
                    "id"       => $id,
                    "adm_id"   => $adm_id
            );
        }
        ksort($finesListSort);
        foreach ($finesListSort as $key => $value) {
            $title = $finesListSort[$key]["title"];
            $barcode = $finesListSort[$key]["barcode"];
            $amount = $finesListSort[$key]["amount"];
            $checkout = $finesListSort[$key]["checkout"];
            $transactiondate = $finesListSort[$key]["transactiondate"];
            $transactiontype = $finesListSort[$key]["transactiontype"];
            $balance += $finesListSort[$key]["amount"];
            $adm_id = $finesListSort[$key]["adm_id"];
            $id = $finesListSort[$key]["id"];
            $fine = $finesListSort[$key]["fine"];
            $finesList[] = array(
                "title"     => $title,
                "barcode"   => $barcode,
                "amount"    => $amount,
                "fine"      => $fine,
                "transactiondate" => $transactiondate,
                "transactiontype" => $transactiontype,
                "balance"   => $balance,
                "checkout"  => $checkout,
                "id"        => $id,
                "adm_id"    => $adm_id,
            );
        }
        $this->idResolver->resolveIds($finesList);
        return $finesList;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $user The patron array
     *
     * @throws ILSException
     * @return array      Array of the patron's profile data on success.
     */
    public function getMyProfile($user)
    {
        if ($this->alephWebService->isXServerEnabled()) {
            return $this->getMyProfileX($user);
        } else {
            return $this->getMyProfileDLF($user);
        }
    }

    /**
     * Get profile information using X-server.
     *
     * @param array $user The patron array
     *
     * @throws ILSException
     * @return array      Array of the patron's profile data on success.
     */
    public function getMyProfileX($user)
    {
        $recordList=array();
        if (!isset($user['college'])) {
            $user['college'] = $this->useradm;
        }
        $xml = $this->alephWebService->doXRequest(
            "bor-info",
            array(
                'loans' => 'N', 'cash' => 'N', 'hold' => 'N',
                'library' => $user['college'], 'bor_id' => $user['id']
            ), true
        );
        $id = (string) $xml->z303->{'z303-id'};
        $address1 = (string) $xml->z304->{'z304-address-2'};
        $address2 = (string) $xml->z304->{'z304-address-3'};
        $zip = (string) $xml->z304->{'z304-zip'};
        $phone = (string) $xml->z304->{'z304-telephone'};
        $barcode = (string) $xml->z304->{'z304-address-0'};
        $group = (string) $xml->z305->{'z305-bor-status'};
        $expiry = (string) $xml->z305->{'z305-expiry-date'};
        $credit_sum = (string) $xml->z305->{'z305-sum'};
        $credit_sign = (string) $xml->z305->{'z305-credit-debit'};
        $name = (string) $xml->z303->{'z303-name'};
        if (strstr($name, ",")) {
            list($lastname, $firstname) = explode(",", $name);
        } else {
            $lastname = $name;
            $firstname = "";
        }
        if ($credit_sign == null) {
            $credit_sign = "C";
        }
        $recordList['firstname'] = $firstname;
        $recordList['lastname'] = $lastname;
        if (isset($user['email'])) {
            $recordList['email'] = $user['email'];
        }
        $recordList['address1'] = $address1;
        $recordList['address2'] = $address2;
        $recordList['zip'] = $zip;
        $recordList['phone'] = $phone;
        $recordList['group'] = $group;
        $recordList['barcode'] = $barcode;
        $recordList['expire'] = $this->parseDate($expiry);
        $recordList['credit'] = $expiry;
        $recordList['credit_sum'] = $credit_sum;
        $recordList['credit_sign'] = $credit_sign;
        $recordList['id'] = $id;
        // deliquencies
        $blocks = array();
        foreach (array('z303-delinq-1', 'z303-delinq-2', 'z303-delinq-3') as $elementName) {
            $block = (string) $xml->z303->{$elementName};
            if (!empty($block) && $block != '00') {
                $blocks[] = $block;
            }
        }
        foreach (array('z305-delinq-1', 'z305-delinq-2', 'z305-delinq-3') as $elementName) {
            $block = (string) $xml->z305->{$elementName};
            if (!empty($block) && $block != '00') {
                $blocks[] = $block;
            }
        }
        $recordList['blocks'] = array_unique($blocks);
        return $recordList;
    }

    /**
     * Get profile information using DLF service.
     *
     * @param array $user The patron array
     *
     * @throws ILSException
     * @return array      Array of the patron's profile data on success.
     */
    public function getMyProfileDLF($user)
    {
        $xml = $this->alephWebService->doRestDLFRequest(
            array('patron', $user['id'], 'patronInformation', 'address')
        );
        $address = $xml->{'address-information'};
        $address1 = (string) $address->{'z304-address-1'};
        $address2 = (string) $address->{'z304-address-2'};
        $address3 = (string) $address->{'z304-address-3'};
        $address4 = (string) $address->{'z304-address-4'};
        $zip = (string) $address->{'z304-zip'};
        $phone = (string) $address->{'z304-telephone-1'};
        $email = (string) $address->{'z304-email-address'};
        $dateFrom = (string) $address->{'z304-date-from'};
        $dateTo = (string) $address->{'z304-date-to'};
        if (strpos($address2, ",") === false) {
            $recordList['lastname'] = $address2;
            $recordList['firstname'] = "";
        } else {
            list($recordList['lastname'], $recordList['firstname'])
                = explode(",", $address2);
        }
        $recordList['address1'] = $address3;
        $recordList['address2'] = $address4;
        $recordList['barcode'] = $address1;
        $recordList['zip'] = $zip;
        $recordList['phone'] = $phone;
        $recordList['email'] = $email;
        $recordList['addressValidFrom'] = $this->parseDate($dateFrom);
        $recordList['addressValidTo'] = $this->parseDate($dateTo);
        $recordList['id'] = $user['id'];
        $xml = $this->alephWebService->doRestDLFRequest(
            array('patron', $user['id'], 'patronStatus', 'registration')
        );
        $institution = $xml->{'registration'}->{'institution'};
        $status = (string) $institution->{'z305-bor-status'};
        $expiry = (string) $institution->{'z305-expiry-date'};
        $recordList['expire'] = $this->parseDate($expiry);
        $recordList['group'] = $status;
        return $recordList;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $user     The patron username
     * @param string $password The patron's password
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($user, $password)
    {
        if ($password == null) {
            $temp = array("id" => $user);
            $temp['college'] = $this->useradm;
            return $this->getMyProfile($temp);
        }
        try {
            $xml = $this->alephWebService->doXRequest(
                'bor-auth',
                array(
                    'library' => $this->useradm, 'bor_id' => $user,
                    'verification' => $password
                ), true
            );
        } catch (\Exception $ex) {
            throw new ILSException($ex->getMessage());
        }
        $patron=array();
        $name = $xml->z303->{'z303-name'};
        if (strstr($name, ",")) {
            list($lastName, $firstName) = explode(",", $name);
        } else {
            $lastName = $name;
            $firstName = "";
        }
        $email_addr = $xml->z304->{'z304-email-address'};
        $id = $xml->z303->{'z303-id'};
        $home_lib = $xml->z303->z303_home_library;
        // Default the college to the useradm library and overwrite it if the
        // home_lib exists
        $patron['college'] = $this->useradm;
        if (($home_lib != '') && (array_key_exists("$home_lib", $this->sublibadm))) {
            if ($this->sublibadm["$home_lib"] != '') {
                $patron['college'] = $this->sublibadm["$home_lib"];
            }
        }
        $patron['id'] = (string) $id;
        $patron['barcode'] = (string) $user;
        $patron['firstname'] = (string) $firstName;
        $patron['lastname'] = (string) $lastName;
        $patron['cat_username'] = (string) $user;
        $patron['cat_password'] = $password;
        $patron['email'] = (string) $email_addr;
        $patron['major'] = null;
        return $patron;
    }

    /**
     * Support method for placeHold -- get holding info for an item.
     *
     * @param string $patronId Patron ID
     * @param string $id       Bib ID
     * @param string $group    Item ID
     *
     * @return array
     */
    public function getHoldingInfoForItem($patronId, $id, $group)
    {
        list($bib, $sys_no) = $this->parseId($id);
        $resource = $bib . $sys_no;
        $xml = $this->alephWebService->doRestDLFRequest(
            array('patron', $patronId, 'record', $resource, 'items', $group)
        );
        $holdRequestAllowed = $xml->xpath("//item/info[@type='HoldRequest']/@allowed");
        $holdRequestAllowed = $holdRequestAllowed[0] == 'Y';
        if ($holdRequestAllowed) {
            return $this->extractHoldingInfoForItem($xml);
        }
        $shortLoanAllowed = $xml->xpath("//item/info[@type='ShortLoan']/@allowed");
        $shortLoanAllowed = $shortLoanAllowed[0] == 'Y';
        if ($shortLoanAllowed) {
            return $this->extractShortLoanInfoForItem($xml);
        }
    }

    protected function extractHoldingInfoForItem($xml)
    {
        $locations = array();
        $part = $xml->xpath('//pickup-locations');
        if ($part) {
            foreach ($part[0]->children() as $node) {
                $arr = $node->attributes();
                $code = (string) $arr['code'];
                $loc_name = (string) $node;
                $locations[$code] = $loc_name;
            }
        } else {
            throw new ILSException('No pickup locations');
        }
        
        $dueDate = null;
        $status = (string) $xml->xpath('//status/text()')[0];
        if (!in_array($status, $this->available_statuses)) {
            $availability = false;
            $matches = array();
            if (preg_match("/([0-9]*\\/[a-zA-Z]*\\/[0-9]*);([a-zA-Z ]*)/", $status, $matches)) {
                $dueDate = $this->parseDate($matches[1]);
            } else if (preg_match("/([0-9]*\\/[a-zA-Z]*\\/[0-9]*)/", $status, $matches)) {
                $dueDate = $this->parseDate($matches[1]);
            } else {
                $dueDate = null;
            }
        }
        
        $requests = 0;
        $str = $xml->xpath('//item/queue/text()');
        $matches = array();
        $pattern = "/(\d) request\(s\) of (\d) items/";
        if ($str != null && preg_match($pattern, $str[0], $matches)) {
            $requests = $matches[1];
        }
        $date = $xml->xpath('//last-interest-date/text()');
        $date = $date[0];
        $date = "" . substr($date, 6, 2) . "." . substr($date, 4, 2) . "."
            . substr($date, 0, 4);
        return array(
            'pickup-locations' => $locations, 'last-interest-date' => $date,
            'order' => $requests + 1, 'due-date' => $dueDate
        );
    }

    protected function extractShortLoanInfoForItem($xml)
    {
        $shortLoanInfo = $xml->xpath("//item/info[@type='ShortLoan']");
        $callNo = (string) $xml->{'item'}->{'z30'}->{'z30-call-no'};
        $slots = array();
        foreach ($shortLoanInfo[0]->{'short-loan'}->{'slot'} as $slot) {
            $numOfItems = (int) $slot->{'num-of-items'};
            $numOfOccupied = (int) $slot->{'num-of-occupied'};
            $available = $numOfItems - $numOfOccupied;
            if ($available <= 0) {
                continue;
            }
            $start_date = $slot->{'start'}->{'date'};
            $start_time = $slot->{'start'}->{'hour'};
            $end_date = $slot->{'end'}->{'date'};
            $end_time = $slot->{'end'}->{'hour'};
            $time = substr($start_date, 6, 2) . "." . substr($start_date, 4, 2) . "."
                . substr($start_date, 0, 4) . " " . substr($start_time, 0, 2) . ":"
                . substr($start_time, 2, 2) . " - " . substr($end_time, 0, 2) . ":"
                . substr($start_time, 2, 2);
            $id = $slot->attributes()->id;
            $id = (string) $id[0];
            $slots[$id] = array(
                'start_date' => (string) $start_date[0],
                'start_time' => (string) $start_time[0],
                'end_date'   => (string) $end_date[0],
                'end_time'   => (string) $end_time[0],
            );
        }
        $result = array(
            'type'       => 'short',
            'callnumber' => $callNo,
            'slots'      => $slots,
        );
        return $result;
    }

    /**
     * Get Default "Hold Required By" Date (as Unix timestamp) or null if unsupported
     *
     * @param array $patron   Patron information returned by the patronLogin method.
     * @param array $holdInfo Contains most of the same values passed to
     * placeHold, minus the patron data.
     *
     * @return int
     */
    public function getHoldDefaultRequiredDate($patron, $holdInfo)
    {
        if ($holdInfo != null) {
            $details = $this->getHoldingInfoForItem(
                $patron['id'], $holdInfo['id'], $holdInfo['item_id']
            );
        }
        if (isset($details['last-interest-date'])) {
            try {
                return $this->dateConverter
                    ->convert('d.m.Y', 'U', $details['last-interest-date']);
            } catch (DateException $e) {
                // If we couldn't convert the date, fail gracefully.
                $this->debug(
                    'Could not convert date: ' . $details['last-interest-date']
                );
            }
        }
        return null;
    }

    /**
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details or throws an exception on failure of support
     * classes
     *
     * @param array $details An array of item and patron data
     *
     * @throws ILSException
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($details)
    {
        list($bib, $sys_no) = $this->parseId($details['id']);
        $recordId = $bib . $sys_no;
        $itemId = $details['item_id'];
        $patron = $details['patron'];
        $pickupLocation = $details['pickUpLocation'];
        if (!$pickupLocation) {
            $pickupLocation = $this->getDefaultPickUpLocation($patron, $details);
        }
        $comment = $details['comment'];
        if (strlen($comment) <= 50) {
            $comment1 = $comment;
        } else {
            $comment1 = substr($comment, 0, 50);
            $comment2 = substr($comment, 50, 50);
        }
        try {
            $requiredBy = $this->dateConverter
                ->convertFromDisplayDate('Ymd', $details['requiredBy']);
        } catch (DateException $de) {
            return array(
                'success'    => false,
                'sysMessage' => 'hold_date_invalid'
            );
        }
        $patronId = $patron['id'];
        $body = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<hold-request-parameters></hold-request-parameters>'
        );
        $body->addChild('pickup-location', $pickupLocation);
        $body->addChild('last-interest-date', $requiredBy);
        $body->addChild('note-1', $comment1);
        if (isset($comment2)) {
            $body->addChild('note-2', $comment2);
        }
        $body = 'post_xml=' . $body->asXML();
        try {
            $result = $this->alephWebService->doRestDLFRequest(
                array(
                    'patron', $patronId, 'record', $recordId, 'items', $itemId,
                    'hold'
                ), null, "PUT", $body
            );
        } catch (AlephRestfulException $exception) {
            $message = $exception->getMessage();
            $note = $exception->getXmlResponse()
                ->xpath('/put-item-hold/create-hold/note[@type="error"]');
            $note = $note[0];
            return array(
                'success' => false,
                'sysMessage' => "$message ($note)"
            );
        }
        return array('success' => true);
    }

    public function placeShortLoanRequest($details)
    {
        list($bib, $sys_no) = $this->parseId($details['id']);
        $recordId = $bib . $sys_no;
        $slot = $details['slot'];
        $itemId = $details['item_id'];
        $patron = $details['patron'];
        $patronId = $patron['id'];
        $body = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<short-loan-parameters></short-loan-parameters>'
        );
        $body->addChild('request-slot', $slot);
        $data = 'post_xml=' . $body->asXML();
        try {
            $result = $this->alephWebService->doRestDLFRequest(array('patron', $patronId, 'record', $recordId,
                'items', $itemId, 'shortLoan'), null, "PUT", $data);
            } catch (Exception $ex) {
                return array('success' => false, 'sysMessage' => $ex->getMessage());
            }
        return array('success' => true);
    }

    /**
     * Parse a date.
     *
     * @param string $date Date to parse
     *
     * @return string
     */
    public function parseDate($date)
    {
        if ($date == null || $date == "") {
            return "";
        } else if (preg_match("/^[0-9]{8}$/", $date) === 1) { // 20120725
            return $this->dateConverter->convertToDisplayDate('Ynd', $date);
        } else if (preg_match("/^[0-9]+\/[A-Za-z]{3}\/[0-9]{4}$/", $date) === 1) {
            // 13/jan/2012
            return $this->dateConverter->convertToDisplayDate('d/M/Y', $date);
        } else if (preg_match("/^[0-9]+\/[0-9]+\/[0-9]{4}$/", $date) === 1) {
            // 13/7/2012
            return $this->dateConverter->convertToDisplayDate('d/M/Y', $date);
        } else {
            throw new \Exception("Invalid date: $date");
        }
    }

    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $func The name of the feature to be checked
     *
     * @return array An array with key-value pairs.
     */
    public function getConfig($func)
    {
        if ($func == "Holds") {
            if (isset($this->config['Holds'])) {
                return $this->config['Holds'];
            }
            return array(
                "HMACKeys" => "id:item_id",
                "extraHoldFields" => "comments:requiredByDate:pickUpLocation",
                "defaultRequiredDate" => "0:1:0"
            );
        } if ($func == "ILLRequests") {
            return array(
                "HMACKeys" => "id:item_id",
            );
        } else {
            return array();
        }
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible for gettting a list of valid library locations for
     * holds / recall retrieval
     *
     * @param array $patron   Patron information returned by the patronLogin method.
     * @param array $holdInfo Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.  The driver must not add new options to the return array
     * based on this data or other areas of VuFind may behave incorrectly.
     *
     * @throws ILSException
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     */
    public function getPickUpLocations($patron, $holdInfo=null)
    {
        if ($holdInfo != null) {
            $details = $this->getHoldingInfoForItem(
                $patron['id'], $holdInfo['id'], $holdInfo['item_id']
            );
            $pickupLocations = array();
            foreach ($details['pickup-locations'] as $key => $value) {
                $pickupLocations[] = array(
                    "locationID" => $key, "locationDisplay" => $value
                );
            }
            return $pickupLocations;
        } else {
            $default = $this->getDefaultPickUpLocation($patron);
            return empty($default) ? array() : array($default);
        }
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location set in VoyagerRestful.ini
     *
     * @param array $patron   Patron information returned by the patronLogin method.
     * @param array $holdInfo Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string       The default pickup location for the patron.
     */
    public function getDefaultPickUpLocation($patron, $holdInfo=null)
    {
        if ($holdInfo != null) {
            $details = $this->getHoldingInfoForItem(
                $patron['id'], $holdInfo['id'], $holdInfo['item_id']
            );
            $pickupLocations = $details['pickup-locations'];
            if (isset($this->preferredPickUpLocations)) {
                foreach (
                    array_values($details['pickup-locations']) as $locationID
                ) {
                    if (in_array($locationID, $this->preferredPickUpLocations)) {
                        return $locationID;
                    }
                }
            }
            // nothing found or preferredPickUpLocations is empty? Return the first
            // locationId in pickupLocations array
            reset($pickupLocations);
            return key($pickupLocations);
        } else if (isset($this->preferredPickUpLocations)) {
            return $this->preferredPickUpLocations[0];
        } else {
            throw new ILSException(
                'Missing Catalog/preferredPickUpLocations config setting.'
            );
        }
    }
    
    public function getMyILLRequests($user) {
        $userId = $user['id'];
        $loans = array();
        $params = array("view" => "full");
        $count = 0;
        $xml = $this->alephWebService->doRestDLFRequest(array('patron', $userId, 'circulationActions', 'requests', 'ill'), $params);
        foreach ($xml->xpath('//ill-request') as $item) {
            $loan = array();
            $z13 = $item->z13;
            $status = (string) $item->z410->{'z410-status'};
            if (!in_array($status, $this->IllHiddenStatuses)) {
                $loan['docno'] = (string) $z13->{'z13-doc-number'};
                $loan['author'] = (string) $z13->{'z13-author'};
                $loan['title'] = (string) $z13->{'z13-title'};
                $loan['imprint'] = (string) $z13->{'z13-imprint'};
                $loan['article_title'] = (string) $item->{'title-of-article'};
                $loan['article_author'] = (string) $item->{'author-of-article'};
                $loan['price'] = (string) $item->{'z13u-additional-bib-info-1'};
                $loan['pickup_location'] = (string) $item->z410->{'z410-pickup-location'};
                $loan['media'] = (string) $item->z410->{'z410-media'};
                $loan['create'] = $this->parseDate((string) $item->z410->{'z410-open-date'});
                $loan['expire'] = $this->parseDate((string) $item->z410->{'z410-last-interest-date'});
                $loans[] = $loan;
            }
        }
        return $loans;
    }
    
    public function placeILLRequest($user, $attrs)
    {
        $payment = $attrs['payment'];
        unset($attrs['payment']);
        $new = $attrs['new'];
        unset($attrs['new']);
        $additional_authors = $attrs['additional_authors'];
        unset($attrs['additional_authors']);
        if (!isset($attrs['ill-unit'])) {
            $attrs['ill-unit'] = $this->defaultIllUnit;
        }
        if (!isset($attrs['pickup-location'])) {
            $attrs['pickup-location'] = $this->defaultIllPickupPlocation;
        }
        try {
            $attrs['last-interest-date'] = $this->dateConverter->convertFromDisplayDate('Ymd', $attrs['last-interest-date']);
        } catch (DateException $de) {
            return array(
                'success'    => false,
                'sysMessage' => 'hold_date_invalid'
            );
        }
        $attrs['allowed-media'] = $attrs['media'];
        $attrs['send-directly'] = 'N';
        $attrs['delivery-method'] = 'S';
        if ($new == 'serial') {
            $new = 'SE';
        } else if ($new == 'monography') {
            $new = 'MN';
        }
        $patronId = $user['id'];
        $illDom = new \DOMDocument('1.0', 'UTF-8');
        $illRoot = $illDom->createElement('ill-parameters');
        $illRootNode = $illDom->appendChild($illRoot);
        foreach ($attrs as $key => $value) {
            $element = $illDom->createElement($key);
            $element->appendChild($illDom->createTextNode($value));
            $illRootNode->appendChild($element);
        }
        $xml = $illDom->saveXML();
        try {
            $path = array('patron', $patronId, 'record', $new, 'ill');
            $result = $this->alephWebService->doRestDLFRequest($path, null,
                'PUT', 'post_xml=' . $xml);
        } catch (\Exception $ex) {
            return array('success' => false, 'sysMessage' => $ex->getMessage());
        }
        $baseAndDocNumber = $result->{'create-ill'}->{'request-number'};
        $base = substr($baseAndDocNumber, 0, 5);
        $docNum = substr($baseAndDocNumber, 5);
        $findDocParams = array('base' => $base, 'doc_num' => $docNum);
        $document = $this->alephWebService->doXRequest('find-doc', $findDocParams, true);
        // create varfield for ILL request type
        $varfield = $document->{'record'}->{'metadata'}->{'oai_marc'}->addChild('varfield');
        $varfield->addAttribute('id', 'PNZ');
        $varfield->addAttribute('i1', ' ');
        $varfield->addAttribute('i2', ' ');
        $subfield = $varfield->addChild('subfield', $payment);
        $subfield->addAttribute('label', 'a');
        if (!empty($additional_authors)) {
            $varfield = $document->{'record'}->{'metadata'}->{'oai_marc'}->addChild('varfield');
            $varfield->addAttribute('id', '700');
            $varfield->addAttribute('i1', '1');
            $varfield->addAttribute('i2', ' ');
            $subfield = $varfield->addChild('subfield', $additional_authors);
            $subfield->addAttribute('label', 'a');
        }
        $updateDocParams = array('library' => $base, 'doc_num' => $docNum);
        $updateDocParams['xml_full_req'] = $document->asXml();
        $updateDocParams['doc_action'] = 'UPDATE';
        try {
            $update = $this->alephWebService->doXRequestUsingPost('update-doc', $updateDocParams, true);
        } catch (\Exception $ex) {
            return array('success' => false, 'sysMessage' => $ex->getMessage());
        }
        return array('success' => true, 'id' => $docNum);
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @throws ILSException
     * @return array     An array with the acquisitions data on success.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPurchaseHistory($id)
    {
        // TODO
        return array();
    }

    /**
     * Get New Items
     *
     * Retrieve the IDs of items recently added to the catalog.
     *
     * @param int $page    Page number of results to retrieve (counting starts at 1)
     * @param int $limit   The size of each page of results to retrieve
     * @param int $daysOld The maximum age of records to retrieve in days (max. 30)
     * @param int $fundId  optional fund ID to use for limiting results (use a value
     * returned by getFunds, or exclude for no limit); note that "fund" may be a
     * misnomer - if funds are not an appropriate way to limit your new item
     * results, you can return a different set of values from getFunds. The
     * important thing is that this parameter supports an ID returned by getFunds,
     * whatever that may mean.
     *
     * @throws ILSException
     * @return array       Associative array with 'count' and 'results' keys
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        // TODO
        $items = array();
        return $items;
    }

    /**
     * Get Departments
     *
     * Obtain a list of departments for use in limiting the reserves list.
     *
     * @throws ILSException
     * @return array An associative array with key = dept. ID, value = dept. name.
     */
    public function getDepartments()
    {
        // TODO
        return array();
    }

    /**
     * Get Instructors
     *
     * Obtain a list of instructors for use in limiting the reserves list.
     *
     * @throws ILSException
     * @return array An associative array with key = ID, value = name.
     */
    public function getInstructors()
    {
        // TODO
        return array();
    }

    /**
     * Get Courses
     *
     * Obtain a list of courses for use in limiting the reserves list.
     *
     * @throws ILSException
     * @return array An associative array with key = ID, value = name.
     */
    public function getCourses()
    {
        // TODO
        return array();
    }

    /**
     * Find Reserves
     *
     * Obtain information on course reserves.
     *
     * @param string $course ID from getCourses (empty string to match all)
     * @param string $inst   ID from getInstructors (empty string to match all)
     * @param string $dept   ID from getDepartments (empty string to match all)
     *
     * @throws ILSException
     * @return array An array of associative arrays representing reserve items.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function findReserves($course, $inst, $dept)
    {
        // TODO
        return array();
    }
}
