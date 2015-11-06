<?php
/**
 * PiwikStatistics Model Class
 *
 * PHP version 5
 *
 * Copyright (C) MZK 2015.
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
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace Statistics\Piwik;

use Piwik\API\Request;
use Statistics\Piwik\PiwikStatisticsInterface;

/**
 * PiwikStatistics Model
 * Calls Piwik's API and returns it's data
 * 
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class PiwikStatistics implements PiwikStatisticsInterface
{
	/**
	 * Site Id, from which to take data
	 * @var	int
	 */
	protected $siteId;
	
	/**
	 * Url of catalog browser in VuFind
	 * @var	string
	 */
	protected $catalogBrowserUrl;
	
	/**
	 * Url of search results in VuFind
	 * @var	string
	 */
	protected $searchResultsUrl;
	
	/**
	 * Url of record in VuFind
	 * @var	string
	 */
	protected $recordUrl;
	
	/**
	 * Url of item reservation in VuFind
	 * @var	string
	 */
	protected $itemReservationUrl;
	
	/**
	 * Url of item prolongation in VuFind
	 * @var	string
	 */
	protected $itemProlongUrl;
	
	/**
	 * Url of user registration in VuFind
	 * @var	string
	 */
	protected $userRegistrationUrl;
	
	/**
	 * Url of user prolongation in VuFind
	 * @var	string
	 */
	protected $userProlongUrl;
	
	/**
	 * Url of statistics in VuFind
	 * @var	string
	 */
	protected $defaultStatisticsUrl;
	
	/**
	 * Url of piwik
	 * @var	string
	 */
	protected $piwikUrl;
	
	/**
	 * Piwik auth token
	 * @var	string
	 */
	protected $piwikTokenAuth;
	
	/**
	 * TrustSSLHost
	 * @var	boolean
	 */
	protected $trustSSLHost;
	
	/**
	 * Drivers
	 * @var array
	 */
	protected $drivers;
	
	/**
	 * Sets initial params
	 * 
	 * @param	\Zend\Config\Config $config
	 * @param	array $drivers Array of strings
	 *
	 * @todo set missing default urls
	 * @todo set variables in config [PiwikStatistics]
	 */
	public function __construct(\Zend\Config\Config $config, array $drivers)
	{
		$this->siteId 			    = isset($config->PiwikStatistics->site_id) 				  ? $config->PiwikStatistics->site_id 			  	  : 1;
		$this->catalogBrowserUrl    = isset($config->PiwikStatistics->catalog_browser_url) 	  ? $config->PiwikStatistics->catalog_browser_url     : "https://vufind.localhost/Browse/";
		$this->searchResultsUrl     = isset($config->PiwikStatistics->search_results_url) 	  ? $config->PiwikStatistics->search_results_url 	  : "https://vufind.localhost/Search/Results";
		$this->recordUrl 		    = isset($config->PiwikStatistics->record_url) 			  ? $config->PiwikStatistics->record_url 			  : "https://vufind.localhost/Record/";
		$this->itemReservationUrl   = isset($config->PiwikStatistics->item_reservation_url)   ? $config->PiwikStatistics->item_reservation_url    : "";
		$this->itemProlongUrl 	    = isset($config->PiwikStatistics->item_prolongation_url)  ? $config->PiwikStatistics->item_prolongation_url   : "";
		$this->userRegistrationUrl  = isset($config->PiwikStatistics->user_registration_url)  ? $config->PiwikStatistics->user_registration_url   : "";
		$this->userProlongUrl 	    = isset($config->PiwikStatistics->user_prolongation_url)  ? $config->PiwikStatistics->user_prolongation_url   : "";
		$this->defaultStatisticsUrl = isset($config->PiwikStatistics->default_statistics_url) ? $config->PiwikStatistics->default_statistics_url  : "http://cpk-front.mzk.cz/Statistics";
		$this->piwikUrl 			= isset($config->PiwikStatistics->piwik_url) 			  ? $config->PiwikStatistics->piwik_url  			  : "http://cpk-front.mzk.cz:9080";
		$this->piwikTokenAuth		= isset($config->PiwikStatistics->piwik_token_auth) 	  ? $config->PiwikStatistics->piwik_token_auth  	  : "no_token_in_config [PiwikStatistics] -> piwik_token_auth";
		$this->trustSSLHost			= isset($config->PiwikStatistics->trust_ssl_host)		  ? $config->PiwikStatistics->trust_ssl_host		  : true;
		$this->drivers 				= $drivers;
	}
	
	/**
	 * Returns array of strings representing driver names
	 * @return array
	 */
	public function getDrivers()
	{
		return $this->drivers;
	}
	
	/**
	 * Returns build url string
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	array	$params	GET params
	 * 
	 * @return	string
	 */
	public function buildQuery($period, $date, array $params)
	{
		$params['token_auth'] = $this->piwikTokenAuth;
		$params['module'] 	  = 'API';
		$params['idSite'] 	  = $this->siteId;
		$params['date'] 	  = $date;
		$params['period'] 	  = $period;
		
		$query = http_build_query($params);
		$url   = $this->piwikUrl.'?'.$query;
		
		//$url = str_replace('%3B', ';', $url);
		//$url = str_replace('%3D%3D', '==', $url);
		
		return $url;
	}
	
	/**
	 * Returns page content from Piwik API Request
	 * 
	 * CURLOPT_HEADER - Include header in result? (0 = yes, 1 = no)
	 * CURLOPT_RETURNTRANSFER - (true = return, false = print) data
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	array	$params	GET params
	 * @param	int|boolean	$rawData
	 * @throws	\Exception when cURL us not installed
	 * @throws	\Exception when Json cannot be decoded 
	 * 			or the encoded data is deeper than the recursion limit.
	 * @throws	\Exception when response body contains error element
	 * @throws	\Exception when reponse status code is not 200
	 * @return	mixed
	 */
	private function getRequestDataResponse($period, $date, array $params, $rawData = null)
	{
		$url = $this->buildQuery($period, $date, $params);
		
		if (! function_exists('curl_init'))
			throw new \Exception('cURL is not installed!');
		
		$curlAdapterConfig = array(
			'adapter'     => '\Zend\Http\Client\Adapter\Curl',
			'curloptions' => array(
				CURLOPT_FOLLOWLOCATION 	=> true,
				CURLOPT_REFERER			=> $this->defaultStatisticsUrl,
				CURLOPT_USERAGENT		=> "Mozilla/5.0",
				CURLOPT_HEADER			=> 0,
				CURLOPT_RETURNTRANSFER	=> true,
				CURLOPT_TIMEOUT			=> 10,	
				CURLOPT_SSL_VERIFYHOST	=> ($this->trustSSLHost) ? 0 : 2,
				CURLOPT_SSL_VERIFYPEER	=> ($this->trustSSLHost) ? 0 : 1,
			),
		);
		
		$client = new \Zend\Http\Client($url, $curlAdapterConfig);
		$response = $client->send();
		
		// Response head error handling
		$responseStatusCode = $response->getStatusCode();
		if($responseStatusCode !== 200)
			throw new \Exception("Response status code: ".$responseStatusCode);
		//
		
		$output	= $response->getBody();
		
		if ($rawData)
			return $output;
		
		// Response body error handling
		$dataArray = \Zend\Json\Json::decode($output, \Zend\Json\Json::TYPE_ARRAY);
		
		if ($dataArray === NULL)
			throw new \Exception('Json cannot be decoded or the encoded data is deeper than the recursion limit.');
		
		if ((isset($dataArray['result']) && ($dataArray['result'] == 'error')))
			throw new \Exception($dataArray['message']);
		//	
			
		return $output;
	}
	
	/**
	 * Returns count of data from Piwik\API\Request data response
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	array	$params
	 * @return	int
	 */
	private function getRowsCountFromRequest($period, $date, array $params)
	{
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		
		return count($dataArray);
	}
	
	/**
	 * Returns data from Piwik\API\Request as array
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	array	$params
	 * @throws	\Exception when cURL is not installed
	 * @return	array
	 */
	private function getResultDataAsArrayFromRequest($period = null, $date = null, array $params)
	{
		if ($params['format'] !== 'json')
			throw new \Exception("Format of requested data must be JSON to convert into php array correctly.");
		
		$jsonData  = $this->getRequestDataResponse($period, $date, $params);
		$dataArray = \Zend\Json\Json::decode($jsonData, \Zend\Json\Json::TYPE_ARRAY);
		
		return $dataArray;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getVisitsCount($period, $date, $type = "all", array $additionalParams = null)
	{
		$params = array(
			'method' => 'VisitsSummary.getVisits',
			'format' => 'json',
		);
		
		if ($type == "anonyme")
			$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1==';
		
		if ($type == "authenticated")
			$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1!=';
		
		// array merge without overwriting
		if($additionalParams) {
			foreach ($additionalParams as $key => $value) {
				if (! array_key_exists($key, $params)) {
					$params[$key] = $value;
				} else {
					if($key == 'segment')
						$param[$key] .= ';'.$value;
				}
			}
		}
		
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		
		return $dataArray;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getVisitsCountForLibrary($period, $date, $userLibCard, array $additionalParams = null)
	{
		$params = array(
			'method' => 'VisitsSummary.getVisits',
			'format' => 'json',
		);
		
		if ($userLibCard)
			$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard;
		
		// array merge without overwriting
		if($additionalParams) {
			foreach ($additionalParams as $key => $value) {
				if (! array_key_exists($key, $params)) {
					$params[$key] = $value;
				} else {
					if($key == 'segment')
						$params[$key] .= ';'.$value;
				}
			}
		}

		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		
		return $dataArray;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getVisitsInfo($period, $date, $type = "all", array $additionalParams = null)
	{
		$params = array(
				'method' => 'VisitsSummary.get',
				'format' => 'json',
		);
	
		if ($type == "anonyme")
			$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1==';
	
		if ($type == "authenticated")
			$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1!=';
	
		// array merge without overwriting
		if($additionalParams) {
			foreach ($additionalParams as $key => $value) {
				if (! array_key_exists($key, $params)) {
					$params[$key] = $value;
				} else {
					if($key == 'segment')
						$param[$key] .= ';'.$value;
				}
			}
		}
	
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
	
		return $dataArray;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getActionsInfo($period, $date, $type = "all", array $additionalParams = null)
	{
		$params = array(
				'method' => 'Actions.get',
				'format' => 'json',
		);
	
		if ($type == "anonyme")
			$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1==';
	
		if ($type == "authenticated")
			$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1!=';
	
		// array merge without overwriting
		if($additionalParams) {
			foreach ($additionalParams as $key => $value) {
				if (! array_key_exists($key, $params)) {
					$params[$key] = $value;
				} else {
					if($key == 'segment')
						$param[$key] .= ';'.$value;
				}
			}
		}
	
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
	
		return $dataArray;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getVisitsInfoForLibrary($period, $date, $userLibCard, array $additionalParams = null)
	{
		$params = array(
				'method' => 'VisitsSummary.get',
				'format' => 'json',
		);
	
		$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard;
	
		// array merge without overwriting
		if($additionalParams) {
			foreach ($additionalParams as $key => $value) {
				if (! array_key_exists($key, $params)) {
					$params[$key] = $value;
				} else {
					if($key == 'segment')
						$param[$key] .= ';'.$value;
				}
			}
		}
	
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
	
		return $dataArray;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getOnlineUsers($lastMinutes = 10, $userLibCard = null, array $additionalParams = null)
	{
		$params = array(
				'method' 	  => 'Live.getCounters',
				'format' 	  => 'json',
				'lastMinutes' => (int) $lastMinutes,
		);
		
		if($userLibCard)
			$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard;
		
		// array merge without overwriting
		if($additionalParams) {
			foreach ($additionalParams as $key => $value) {
				if (! array_key_exists($key, $params)) {
					$params[$key] = $value;
				} else {
					if($key == 'segment')
						$param[$key] .= ';'.$value;
				}
			}
		}
		
		$dataArray = $this->getResultDataAsArrayFromRequest(null, null, $params);
		
		return $dataArray[0]['visits'];
	}
	
	/**
	 * @inheritDoc
	 */
	public function getFoundSearchKeywordsCount($period, $date, $userLibCard = null)
	{
		 $params = array(
			'method'  => 'Actions.getSiteSearchKeywords',
			'format'  => 'json',
		 	'filter_limit' 	=> "-1",
			'showColumns' => 'label',
		);
		
		if ($userLibCard)
			$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard;
		
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		
		$count = count($dataArray);

		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getNoResultSearchKeywordsCount($period, $date, $userLibCard = null)
	{
		$params = array(
			'method' 		=> 'Actions.getSiteSearchNoResultKeywords',
			'format'  		=> 'json',
			'filter_limit' 	=> "-1",
			'showColumns' => 'label',
		);
	
		if ($userLibCard)
			$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard;
	
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
	
		$count = count($dataArray);
	
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getFoundSearchKeywords($period, $date, $filterLimit="-1", $userLibCard = null, $rawData = null)
	{
		 $params = array(
			'method'  => 'Actions.getSiteSearchKeywords',
			'format'  => 'json',
		 	'filter_limit' => $filterLimit,
			'showColumns' => 'nb_visits,label',
		);
		
		if ($rawData)
			$params['format'] = 'csv';
		
		if ($userLibCard)
			$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard;
		
		if ($rawData)
			return $this->buildQuery($period, $date, $params);
		
		if ($filterLimit == "-1") {
			
			$params['showColumns'] = 'nb_visits';
			$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
			
			$sum = 0;
			foreach ($dataArray as $value) {
				$sum += $value['nb_visits'];
			}
			
			return $sum;
			
		} else { 
			
			// because of performance
			$params['filter_sort_column'] = 'nb_visits';
			$params['filter_sort_order'] = 'desc';
			
		}
		
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		
		$searches = array();
		foreach ($dataArray as $value) {
			array_push($searches, array('keyword' => $value['label'], 'count' => $value['nb_visits']));
		}

		return $searches;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getNoResultSearchKeywords($period, $date, $filterLimit="-1" ,$userLibCard = null, $rawData = null)
	{
		$params = array(
			'method'  => 'Actions.getSiteSearchNoResultKeywords',
			'format'  => 'json',
			'filter_limit' => $filterLimit,
			'showColumns' => 'nb_visits,label',			
		);
	
		if ($rawData)
			$params['format'] = 'csv';
	
		if ($userLibCard)
			$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard;
	
		if ($rawData)
			return $this->buildQuery($period, $date, $params);
		
		if ($filterLimit == "-1") {
				
			$params['showColumns'] = 'nb_visits';
			$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
				
			$sum = 0;
			foreach ($dataArray as $value) {
				$sum += $value['nb_visits'];
			}
				
			return $sum;
				
		} else {
				
			// because of performance
			$params['filter_sort_column'] = 'nb_visits';
			$params['filter_sort_order'] = 'desc';
				
		}
	
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
	
		$searches = array();
		foreach ($dataArray as $key => $value) {
			array_push($searches, array('keyword' => $value['label'], 'count' => $value['nb_visits']));
		}
	
		return $searches;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getCatalogAccessCount($period, $date, $type = "all")
	{
		$params = array(
			'method'  => 'VisitsSummary.getVisits',
			'format'  => 'json',
			'segment' => 'pageUrl=@'.urlencode($this->catalogBrowserUrl),
		);
	
		if ($type == "anonyme")
			$params['segment'] .= ';customVariablePageName1==UserLibcard;customVariablePageValue1==';
	
		if ($type == "authenticated")
			$params['segment'] .= ';customVariablePageName1==UserLibcard;customVariablePageValue1!=';
	
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		$count = $dataArray['value'];
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getCatalogAccessCountForLibrary($period, $date, $userLibCard)
	{
		$params = array(
				'method'  => 'VisitsSummary.getVisits',
				'format'  => 'json',
				'segment' => 'pageUrl=@'.urlencode($this->catalogBrowserUrl)
				.';customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard,
		);
	
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		$count = $dataArray['value'];
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getNbRecordVisits($period, $date, $type = "all")
	{
		$params = array(
			'method'  => 'Actions.get',
			'format'  => 'json',
			'showColumns' => 'nb_pageviews',
			'segment' => 'pageUrl=@'.urlencode($this->recordUrl),
		);
	
		if ($type == "anonyme")
			$params['segment'] .= 'customVariablePageName1==UserLibcard;customVariablePageValue1==';
	
		if ($type == "authenticated")
			$params['segment'] .= 'customVariablePageName1==UserLibcard;customVariablePageValue1!=';
	
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		$count = $dataArray['value'];
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getNbRecordVisitsForLibrary($period, $date, $userLibCard, $type = "all")
	{
		$params = array(
				'method'  => 'Actions.get',
				'format'  => 'json',
				'showColumns' => 'nb_pageviews',
				'segment' => 'pageUrl=@'.urlencode($this->recordUrl)
							.';customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard,
		);
		
		if ($type == "anonyme")
			$params['segment'] .= 'customVariablePageName1==UserLibcard;customVariablePageValue1==';
		
		if ($type == "authenticated")
			$params['segment'] .= 'customVariablePageName1==UserLibcard;customVariablePageValue1!=';
		
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		$count = $dataArray['value'];
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getNbViewedRecords($period, $date, $type = "all", array $additionalParams = null)
	{
		$params = array(
			'method'  => 'Actions.get',
			'format'  => 'json',
			'showColumns' => 'nb_uniq_pageviews',
			'segment' => 'pageUrl=@'.urlencode($this->recordUrl),
		);
	
		if ($type == "anonyme")
			$params['segment'] .= 'customVariablePageName1==UserLibcard;customVariablePageValue1==';
	
		if ($type == "authenticated")
			$params['segment'] .= 'customVariablePageName1==UserLibcard;customVariablePageValue1!=';
	
		// array merge without overwriting
		if($additionalParams) {
			foreach ($additionalParams as $key => $value) {
				if (! array_key_exists($key, $params)) {
					$params[$key] = $value;
				} else {
					if($key == 'segment')
						$param[$key] .= ';'.$value;
				}
			}
		}
	
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		$count = $dataArray['value'];
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getNbViewedRecordsForLibrary($period, $date, $userLibCard)
	{
		$params = array(
			'method'  => 'Actions.get',
			'format'  => 'json',
			'showColumns' => 'nb_uniq_pageviews',
			'segment' => 'pageUrl=@'.urlencode($this->recordUrl).
						';customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard,
		);

		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		$count = $dataArray['value'];
	
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getViewedRecords($period, $date, $userLibCard = null, $rawData = null)
	{
		// @todo implement
	}
	
	/**
	 * @inheritDoc
	 */
	public function getNewVisitorsCount($period, $date, $type = "all")
	{		
		$params = array(
			'method'  => 'VisitsSummary.getUniqueVisitors',
			'format'  => 'json',
			'segment' => 'visitorType==new',
		);
		
		if ($type == "anonyme")
			$params['segment'] .= ';customVariablePageName1==UserLibcard;customVariablePageValue1=@';
			
		
		if ($type == "authenticated")
			$params['segment'] .= ';customVariablePageName1==UserLibcard;customVariablePageValue1!=';
		
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		
		$count = $dataArray['value'];
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getNewVisitorsCountForLibrary($period, $date, $userLibCard)
	{
		$params = array(
			'method'  => 'VisitsSummary.getUniqueVisitors',
			'format'  => 'json',
			'segment' => 'visitorType==new;customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard,
		);
		
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		
		$count = $dataArray['value'];
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getNewVisitors($period, $date, $userLibCard = null, $rawData = null)
	{
		$params = array(
			'method'  => 'VisitsSummary.getUniqueVisitors',
			'format'  => 'json',
			'segment' => 'visitorType==new',
		);
		
		if ($rawData)
			$params['format'] = 'csv';
		
		if ($userLibCard)
			$params['segment'] .= ';customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard;
		
		if ($rawData)
			return $this->getResultDataFromRequest($period, $date, $params);
		
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		
		return $dataArray;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getReturningVisitorsCount($period, $date, $type = "all")
	{
		$params = array(
			'method'  => 'VisitsSummary.getUniqueVisitors',
			'format'  => 'json',
			'segment' => 'visitorType==returning',
		);
		
		if ($type == "anonyme")
			$params['segment'] .= ';customVariablePageName1==UserLibcard;customVariablePageValue1=@';
		
		if ($type == "authenticated")
			$params['segment'] .= ';customVariablePageName1==UserLibcard;customVariablePageValue1!=';
		
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		
		$count = $dataArray['value'];
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getReturningVisitorsCountForLibrary($period, $date, $userLibCard)
	{
		$params = array(
			'method'  => 'VisitsSummary.getUniqueVisitors',
			'format'  => 'json',
			'segment' => 'visitorType==returning;customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard,
		);
		
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		
		$count = $dataArray['value'];
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getReturningVisitors($period, $date, $userLibCard = null, $rawData = null)
	{
		$params = array(
			'method'  => 'VisitsSummary.getUniqueVisitors',
			'format'  => 'json',
			'segment' => 'visitorType==returning',
		);
		
		if ($rawData)
			$params['format'] = 'csv';
		
		if ($userLibCard)
			$params['segment'] .= ';customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard;
		
		if ($rawData)
			return $this->getResultDataFromRequest($period, $date, $params);
		
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		
		return $dataArray;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getItemProlongsCount($period, $date, $userLibCard = null)
	{
		$params = array(
			'method'  => 'VisitsSummary.getVisits',
			'format'  => 'json',
			'segment' => 'pageUrl=@'.urlencode($this->itemProlongUrl),
		);
		
		if ($userLibCard)
			$params['segment'] .= ';customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard;
		
		$count = $this->getRowsCountFromRequest($period, $date, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getItemReservationsCount($period, $date, $userLibCard = null)
	{
		$params = array(
			'method'  => 'VisitsSummary.getVisits',
			'format'  => 'json',
			'segment' => 'pageUrl=@'.urlencode($this->itemReservationUrl),
		);
		
		if ($userLibCard)
			$params['segment'] .= ';customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard;
		
		$count = $this->getRowsCountFromRequest($period, $date, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getOrdersCount($period, $date, $type = "all")
	{
		
	}
	
	/**
	 * @inheritDoc
	 */
	public function getOrdersCountForLibrary($period, $date, $userLibCard)
	{
		
	}
	
	/**
	 * @inheritDoc
	 */
	public function getUserRegistrationsCount($period, $date, $userLibCard = null)
	{
		$params = array(
			'method'  => 'VisitsSummary.getVisits',
			'format'  => 'json',
			'segment' => 'pageUrl=@'.urlencode($this->userRegistrationUrl),
		);
		
		if ($userLibCard)
			$params['segment'] .= ';customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard;
		
		$count = $this->getRowsCountFromRequest($period, $date, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getUserProlongationsCount($period, $date, $userLibCard = null)
	{
		$params = array(
			'method'  => 'VisitsSummary.getVisits',
			'format'  => 'json',
			'segment' => 'pageUrl=@'.urlencode($this->userProlongUrl),
		);
		
		if ($userLibCard)
			$params['segment'] .= ';customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard;
		
		$count = $this->getRowsCountFromRequest($period, $date, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getTransactionCount($period, $date, $type = "all")
	{
		// @todo implement?
	}
	
	/**
	 * @inheritDoc
	 */
	public function getTransactionCountForLibrary($period, $date, $userLibCard)
	{
		// @todo implement?
	}
	
	/**
	 * @inheritDoc
	 */
	public function getTransactionSumCount($period, $date, $type = "all")
	{
		// @todo implement?
	}
	
	/**
	 * @inheritDoc
	 */
	public function getTransactionSumForLibrary($period, $date, $userLibCard)
	{
		// @todo implement?
	}
	
	/**
	 * @inheritDoc
	 */
	public function getReferrers($period, $date, $filterLimit='-1', $userLibCard = null, $rawData = null)
	{
		$params = array(
			'method'  => 'Referrers.getWebsites',
			'format'  => 'json',
		 	'filter_limit' => $filterLimit,
			'showColumns' => 'nb_visits,label',
		);
		
		if ($rawData)
			$params['format'] = 'csv';
		
		if ($userLibCard)
			$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard;
		
		if ($rawData)
			return $this->buildQuery($period, $date, $params);
		
		if ($filterLimit == "-1") {
			
			$params['showColumns'] = 'nb_visits';
			$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
			
			$sum = 0;
			foreach ($dataArray as $value) {
				$sum += $value['nb_visits'];
			}
			
			return $sum;
			
		} else { 
			
			// because of performance
			$params['filter_sort_column'] = 'nb_visits';
			$params['filter_sort_order'] = 'desc';
			
		}
		
		
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		
		$searches = array();
		foreach ($dataArray as $value) {
			array_push($searches, array('referrer' => $value['label'], 'count' => $value['nb_visits']));
		}

		return $searches;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getReferredVisits($period, $date, $type = "all")
	{
		$params = array(
				'method' => 'Referrers.getReferrerType',
				'format' => 'json',
				'showColumns' => 'nb_visits,label',
		);
	
		if ($type == "anonyme")
			$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1==';
	
		if ($type == "authenticated")
			$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1!=';
	
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
	
		$referrerTypes = array();
		foreach ($dataArray as $value) {
			array_push($referrerTypes, array('referrerType' => $value['label'], 'count' => $value['nb_visits']));
		}
		
		return $referrerTypes;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getReferredVisitsForLibrary($period, $date, $userLibCard)
	{
		$params = array(
				'method' => 'Referrers.getReferrerType',
				'format' => 'json',
				'showColumns' => 'nb_visits,label',
		);
	
		if ($userLibCard)
			$params['segment'] = 'customVariablePageName1==UserLibcard;customVariablePageValue1=='.$userLibCard;
	
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		
		$referrerTypes = array();
		foreach ($dataArray as $value) {
			array_push($referrerTypes, array('referrerType' => $value['label'], 'count' => $value['nb_visits']));
		}
	
		return $referrerTypes;
	}
}