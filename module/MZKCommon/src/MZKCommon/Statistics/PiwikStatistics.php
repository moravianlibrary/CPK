<?php
namespace MZKCommon\Statistics;

use Piwik\API\Request;
use MZKCommon\Statistics\PiwikStatisticsInterface;

/**
 * PiwikStatistics Model
 * Calls Piwik's API and returns it's data
 * 
 * @author	Martin Kravec	<kravec@mzk.cz>
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
	 * @inheritDoc
	 * @todo set missing default urls
	 * @todo set variables in config [PiwikStatistics]
	 */
	public function __construct($config)
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
	 * @throws	\Exception when cURL us not installed
	 * @return	mixed
	 */
	private function getRequestDataResponse($period, $date, array $params)
	{
		$params['token_auth'] = $this->piwikTokenAuth;
		$params['module'] 	  = 'API';
		$params['idSite'] 	  = $this->siteId;
		$params['date'] 	  = $date;
		$params['period'] 	  = $period;
		
		$query = http_build_query($params);
		$url   = $this->piwikUrl.'?'.$query;
		
		if (! function_exists('curl_init'))
			throw new \Exception('cURL is not installed!');
		
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_REFERER, $this->defaultStatisticsUrl);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	
		$output = curl_exec($ch);
	
		curl_close($ch);
	
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
	private function getResultDataAsArrayFromRequest($period, $date, array $params)
	{
		if ($params['format'] !== 'json')
			throw new \Exception("Format of requested data must be JSON to convert into php array correctly.");
		
		$jsonData  = $this->getRequestDataResponse($period, $date, $params);
		$dataArray = json_decode($jsonData, true);
		
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
			$params['segment'] = 'customVariablePageUserLibCard==null';
		
		if ($type == "authenticated")
			$params['segment'] = 'customVariablePageUserLibCard!=null';
		
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
	public function getVisitsCountForLibrary($period, $date, $userLibCard)
	{
		$params = array(
			'method' => 'VisitsSummary.getVisits',
			'format' => 'json',
		);
		
		if ($userLibCard)
			$params['segment'] = 'customVariablePageUserLibCard=='.$userLibCard;
		
		$count = $this->getRowsCountFromRequest($period, $date, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getSearchCount($period, $date, $type = "all")
	{
		$params = array(
			'method'  => 'VisitsSummary.getVisits',
			'format'  => 'json',
			'segment' => 'pageUrl=@'.urlencode($this->searchResultsUrl),
		);
		
		if ($type == "anonyme")
			$params['segment'] .= ';customVariablePageUserLibCard==null';
		
		if ($type == "authenticated")
			$params['segment'] .= ';customVariablePageUserLibCard!=null';
		
		$count = $this->getRowsCountFromRequest($period, $date, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getSearchCountForLibrary($period, $date, $userLibCard)
	{
		$params = array(
			'method'  => 'VisitsSummary.getVisits',
			'format'  => 'json',
			'segment' => 'pageUrl=@'.urlencode($this->searchResultsUrl)
					   .';customVariablePageUserLibCard=='.$userLibCard,
		);
		
		$count = $this->getRowsCountFromRequest($period, $date, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getSearchKeywords($period, $date, $userLibCard = null, $rawData = null)
	{
		// @todo implement
	}
	
	/**
	 * @inheritDoc
	 */
	public function getViewedRecordsCount($period, $date, $type = "all")
	{
		$params = array(
			'method'  => 'VisitsSummary.getVisits',
			'format'  => 'json',
			'segment' => 'pageUrl=@'.urlencode($this->recordUrl),
		);
		
		if ($type == "anonyme")
			$params['segment'] .= ';customVariablePageUserLibCard==null';
		
		if ($type == "authenticated")
			$params['segment'] .= ';customVariablePageUserLibCard!=null';
		
		$count = $this->getRowsCountFromRequest($period, $date, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getViewedRecordsCountForLibrary($period, $date, $userLibCard)
	{
		$params = array(
			'method'  => 'VisitsSummary.getVisits',
			'format'  => 'json',
			'segment' => 'pageUrl=@'.urlencode($this->recordUrl)
					   .';customVariablePageUserLibCard=='.$userLibCard,
		);
		
		$count = $this->getRowsCountFromRequest($period, $date, $params);
		
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
			$params['segment'] .= ';customVariablePageUserLibCard==null';
		
		if ($type == "authenticated")
			$params['segment'] .= ';customVariablePageUserLibCard!=null';
		
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		
		return $dataArray;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getNewVisitorsCountForLibrary($period, $date, $userLibCard)
	{
		$params = array(
			'method'  => 'VisitsSummary.getVisits',
			'format'  => 'json',
			'segment' => 'visitorType==new;customVariablePageUserLibCard=='.$userLibCard,
		);
		
		$count = $this->getRowsCountFromRequest($period, $date, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getNewVisitors($period, $date, $userLibCard = null, $rawData = null)
	{
		$params = array(
			'method'  => 'VisitsSummary.getVisits',
			'format'  => 'json',
			'segment' => 'visitorType==new',
		);
		
		if ($rawData)
			$params['format'] = 'csv';
		
		if ($userLibCard)
			$params['segment'] .= ';customVariablePageUserLibCard=='.$userLibCard;
		
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
			$params['segment'] .= ';customVariablePageUserLibCard==null';
		
		if ($type == "authenticated")
			$params['segment'] .= ';customVariablePageUserLibCard!=null';
		
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		
		return $dataArray;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getReturningVisitorsCountForLibrary($period, $date, $userLibCard)
	{
		$params = array(
			'method'  => 'VisitsSummary.getVisits',
			'format'  => 'json',
			'segment' => 'visitorType==returning;customVariablePageUserLibCard=='.$userLibCard,
		);
		
		$count = $this->getRowsCountFromRequest($period, $date, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getReturningVisitors($period, $date, $userLibCard = null, $rawData = null)
	{
		$params = array(
			'method'  => 'VisitsSummary.getVisits',
			'format'  => 'json',
			'segment' => 'visitorType==returning',
		);
		
		if ($rawData)
			$params['format'] = 'csv';
		
		if ($userLibCard)
			$params['segment'] .= ';customVariablePageUserLibCard=='.$userLibCard;
		
		if ($rawData)
			return $this->getResultDataFromRequest($period, $date, $params);
		
		$dataArray = $this->getResultDataAsArrayFromRequest($period, $date, $params);
		
		return $dataArray;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getNotFoundSearchKeywordsCount($period, $date, $type = "all")
	{
		// @todo implement
	}
	
	/**
	 * @inheritDoc
	 */
	public function getNotFoundSearchKeywordsCountForLibrary($period, $date, $userLibCard)
	{
		// @todo implement
	}
	
	/**
	 * @inheritDoc
	 */
	public function getNotFoundSearchKeywords($period, $date, $userLibCard = null, $rawData = null)
	{
		// @todo implement
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
			$params['segment'] .= ';customVariablePageUserLibCard==null';
		
		if ($type == "authenticated")
			$params['segment'] .= ';customVariablePageUserLibCard!=null';
		
		$count = $this->getRowsCountFromRequest($period, $date, $params);
		
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
					   .';customVariablePageUserLibCard=='.$userLibCard,
		);
		
		$count = $this->getRowsCountFromRequest($period, $date, $params);
		
		return $count;
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
			$params['segment'] .= ';customVariablePageUserLibCard=='.$userLibCard;
		
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
			$params['segment'] .= ';customVariablePageUserLibCard=='.$userLibCard;
		
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
			$params['segment'] .= ';customVariablePageUserLibCard=='.$userLibCard;
		
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
			$params['segment'] .= ';customVariablePageUserLibCard=='.$userLibCard;
		
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
}