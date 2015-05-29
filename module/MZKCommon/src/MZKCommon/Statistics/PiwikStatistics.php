<?php

namespace MZKCommon\Statistics;

use Piwik\API\Request;
use MZKCommon\Statistics\PiwikStatisticsInterface;

/**
 * PiwikStatistics Model
 * @author Martin Kravec <kravec@mzk.cz>
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
	 * @inheritDoc
	 * @todo set missing default urls
	 * @todo set variables in config [PiwikStatistics]
	 */
	public function __construct($config)
	{
		$this->siteId 			   = isset($config->PiwikStatistics->site_id) 				? $config->PiwikStatistics->site_id 			  : 1;
		$this->catalogBrowserUrl   = isset($config->PiwikStatistics->catalog_browser_url) 	? $config->PiwikStatistics->catalog_browser_url   : "https%3A%2F%2Fvufind.localhost%2FBrowse%2F";
		$this->searchResultsUrl    = isset($config->PiwikStatistics->search_results_url) 	? $config->PiwikStatistics->search_results_url 	  : "https%3A%2F%2Fvufind.localhost%2FSearch%2FResults";
		$this->recordUrl 		   = isset($config->PiwikStatistics->record_url) 			? $config->PiwikStatistics->record_url 			  : "https%3A%2F%2Fvufind.localhost%2FRecord%2F";
		$this->itemReservationUrl  = isset($config->PiwikStatistics->item_reservation_url) 	? $config->PiwikStatistics->item_reservation_url  : "";
		$this->itemProlongUrl 	   = isset($config->PiwikStatistics->item_prolongation_url) ? $config->PiwikStatistics->item_prolongation_url : "";
		$this->userRegistrationUrl = isset($config->PiwikStatistics->user_registration_url) ? $config->PiwikStatistics->user_registration_url : "";
		$this->userProlongUrl 	   = isset($config->PiwikStatistics->user_prolongation_url) ? $config->PiwikStatistics->user_prolongation_url : "";
	}
	
	/**
	 * Returns row count of Piwik\API\Request data response
	 * @param	string	$ApiMethod	Api method name
	 * @param	array	$params		Array of params
	 * @return	int
	 */
	private function getRowsCountFromRequest($ApiMethod, array $params)
	{
		$dataTable = Request::processRequest($ApiMethod, $params);
		$count = $dataTable->getRowsCount();
		return $count;
	}
	
	/**
	 * Returns data from Piwik\API\Request
	 * Param $params['format'] must be "json" to convert data into array
	 * successfully.
	 * @param	string	$ApiMethod	Api method name
	 * @param	array	$params		Array of params
	 * @return	array
	 */
	private function getResultDataAsArrayFromRequest($ApiMethod, array $params)
	{
		$dataTable = Request::processRequest($ApiMethod, $params);
		$array = json_decode($dataTable, true);
		return $array;
	}
	
	/**
	 * Returns data from Piwik\API\Request
	 * @param	string	$ApiMethod	Api method name
	 * @param	array	$params		Array of params
	 * @return	array
	 */
	private function getResultDataFromRequest($ApiMethod, array $params)
	{
		return Request::processRequest($ApiMethod, $params);
	}
	
	/**
	 * @inheritDoc
	 */
	public function getVisitsCount($period, $date, $type = "all")
	{
		$ApiMethod = "VisitsSummary.getVisits";
		$format		= 'json';
		
		$params = array(
			'idSite' 	=> $this->siteId,
			'date' 		=> $date,
			'period' 	=> $period,
			'format' 	=> $format,
		);
		
		if($type == "anonyme")
			array_push($params, array('segment'	=> 'customVariablePageUserLibCard==null'));
		
		if($type == "authenticated")
			array_push($params, array('segment'	=> 'customVariablePageUserLibCard!=null'));
		
		$count = $this->getRowsCountFromRequest($ApiMethod, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getVisitsCountForLibrary($period, $date, $userLibCard)
	{
		$ApiMethod = 'VisitsSummary.getVisits';
		$format		= 'json';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
		);
		
		if($userLibCard)
			array_push($params, array('segment'	=> 'customVariablePageUserLibCard=='.$userLibCard));
		
		$count = $this->getRowsCountFromRequest($ApiMethod, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getSearchCount($period, $date, $type = "all")
	{
		$ApiMethod	= 'VisitsSummary.getVisits';
		$format		= 'json';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
				'segment'	=> 'pageUrl=@'.$this->searchResultsUrl,
		);
		
		if($type == "anonyme")
			$params['segment'] .= ';customVariablePageUserLibCard==null';
		
		if($type == "authenticated")
			$params['segment'] .= ';customVariablePageUserLibCard!=null';
		
		$count = $this->getRowsCountFromRequest($ApiMethod, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getSearchCountForLibrary($period, $date, $userLibCard)
	{
		$ApiMethod	= 'VisitsSummary.getVisits';
		$format		= 'json';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
				'segment'	=> 'pageUrl=@'.$this->searchResultsUrl
							 .';customVariablePageUserLibCard=='.$userLibCard,
		);
		
		$count = $this->getRowsCountFromRequest($ApiMethod, $params);
		
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
		$ApiMethod	= 'VisitsSummary.getVisits';
		$format		= 'json';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
				'segment'	=> 'pageUrl=@'.$this->recordUrl,
		);
		
		if($type == "anonyme")
			$params['segment'] .= ';customVariablePageUserLibCard==null';
		
		if($type == "authenticated")
			$params['segment'] .= ';customVariablePageUserLibCard!=null';
		
		$count = $this->getRowsCountFromRequest($ApiMethod, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getViewedRecordsCountForLibrary($period, $date, $userLibCard)
	{
		$ApiMethod	= 'VisitsSummary.getVisits';
		$format		= 'json';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
				'segment'	=> 'pageUrl=@'.$this->recordUrl
							 .';customVariablePageUserLibCard=='.$userLibCard,
		);
		
		$count = $this->getRowsCountFromRequest($ApiMethod, $params);
		
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
		$ApiMethod	= 'VisitsSummary.getVisits';
		$format		= 'json';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
				'segment'	=> 'visitorType==new',
		);
		
		if($type == "anonyme")
			$params['segment'] .= ';customVariablePageUserLibCard==null';
		
		if($type == "authenticated")
			$params['segment'] .= ';customVariablePageUserLibCard!=null';
		
		$count = $this->getRowsCountFromRequest($ApiMethod, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getNewVisitorsCountForLibrary($period, $date, $userLibCard)
	{
		$ApiMethod	= 'VisitsSummary.getVisits';
		$format		= 'json';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
				'segment'	=> 'visitorType==new;customVariablePageUserLibCard=='.$userLibCard,
		);
		
		$count = $this->getRowsCountFromRequest($ApiMethod, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getNewVisitors($period, $date, $userLibCard = null, $rawData = null)
	{
		$ApiMethod	= 'VisitsSummary.getVisits';
		$format		= 'json';
		
		if($rawData)
			$format	= 'csv';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
				'segment'	=> 'visitorType==new',
		);
		
		if($userLibCard)
			$params['segment'] .= ';customVariablePageUserLibCard=='.$userLibCard;
		
		if($rawData)
			return $this->getResultDataFromRequest($ApiMethod, $params);
		
		$dataArray = $this->getResultDataAsArrayFromRequest($ApiMethod, $params);
		
		return $dataArray;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getReturningVisitorsCount($period, $date, $type = "all")
	{
		$ApiMethod	= 'VisitsSummary.getVisits';
		$format		= 'json';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
				'segment'	=> 'visitorType==returning',
		);
		
		if($type == "anonyme")
			$params['segment'] .= ';customVariablePageUserLibCard==null';
		
		if($type == "authenticated")
			$params['segment'] .= ';customVariablePageUserLibCard!=null';
		
		$count = $this->getRowsCountFromRequest($ApiMethod, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getReturningVisitorsCountForLibrary($period, $date, $userLibCard)
	{
		$ApiMethod	= 'VisitsSummary.getVisits';
		$format		= 'json';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
				'segment'	=> 'visitorType==returning;customVariablePageUserLibCard=='.$userLibCard,
		);
		
		$count = $this->getRowsCountFromRequest($ApiMethod, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getReturningVisitors($period, $date, $userLibCard = null, $rawData = null)
	{
		$ApiMethod	= 'VisitsSummary.getVisits';
		$format		= 'json';
		
		if($rawData)
			$format	= 'csv';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
				'segment'	=> 'visitorType==returning',
		);
		
		if($userLibCard)
			$params['segment'] .= ';customVariablePageUserLibCard=='.$userLibCard;
		
		if($rawData)
			return $this->getResultDataFromRequest($ApiMethod, $params);
		
		$dataArray = $this->getResultDataAsArrayFromRequest($ApiMethod, $params);
		
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
		$ApiMethod	= 'VisitsSummary.getVisits';
		$format		= 'json';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
				'segment'	=> 'pageUrl=@'.$this->catalogBrowserUrl,
		);
		
		if($type == "anonyme")
			$params['segment'] .= ';customVariablePageUserLibCard==null';
		
		if($type == "authenticated")
			$params['segment'] .= ';customVariablePageUserLibCard!=null';
		
		$count = $this->getRowsCountFromRequest($ApiMethod, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getCatalogAccessCountForLibrary($period, $date, $userLibCard)
	{
		$ApiMethod	= 'VisitsSummary.getVisits';
		$format		= 'json';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
				'segment'	=> 'pageUrl=@'.$this->catalogBrowserUrl
							 .';customVariablePageUserLibCard=='.$userLibCard,
		);
		
		$count = $this->getRowsCountFromRequest($ApiMethod, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getItemProlongsCount($period, $date, $userLibCard = null)
	{
		$ApiMethod	= 'VisitsSummary.getVisits';
		$format		= 'json';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
				'segment'	=> 'pageUrl=@'.$this->itemProlongUrl,
		);
		
		if($userLibCard)
			$params['segment'] .= ';customVariablePageUserLibCard=='.$userLibCard;
		
		$count = $this->getRowsCountFromRequest($ApiMethod, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getItemReservationsCount($period, $date, $userLibCard = null)
	{
		$ApiMethod	= 'VisitsSummary.getVisits';
		$format		= 'json';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
				'segment'	=> 'pageUrl=@'.$this->itemReservationUrl,
		);
		
		if($userLibCard)
			$params['segment'] .= ';customVariablePageUserLibCard=='.$userLibCard;
		
		$count = $this->getRowsCountFromRequest($ApiMethod, $params);
		
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
		$ApiMethod	= 'VisitsSummary.getVisits';
		$format		= 'json';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
				'segment'	=> 'pageUrl=@'.$this->userRegistrationUrl,
		);
		
		if($userLibCard)
			$params['segment'] .= ';customVariablePageUserLibCard=='.$userLibCard;
		
		$count = $this->getRowsCountFromRequest($ApiMethod, $params);
		
		return $count;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getUserProlongationsCount($period, $date, $userLibCard = null)
	{
		$ApiMethod	= 'VisitsSummary.getVisits';
		$format		= 'json';
		
		$params = array(
				'idSite' 	=> $this->siteId,
				'date' 		=> $date,
				'period' 	=> $period,
				'format' 	=> $format,
				'segment'	=> 'pageUrl=@'.$this->userProlongUrl,
		);
		
		if($userLibCard)
			$params['segment'] .= ';customVariablePageUserLibCard=='.$userLibCard;
		
		$count = $this->getRowsCountFromRequest($ApiMethod, $params);
		
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