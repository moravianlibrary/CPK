<?php
namespace MZKCommon\Statistics;

/**
 * PiwikStatisticsInterface
 * For communication with Piwik's API
 * 
 * @author	Martin Kravec	<kravec@mzk.cz>
 */
interface PiwikStatisticsInterface
{
	/**
	 * Sets initial params
	 * @param	int $idSite
	 */
	public function __construct($config);
	
	/**
	 * Returns number of visits
	 * 
	 * @param	string	$period				day|week|month|year|range
	 * @param	string	$date				YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 										YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$type				all|anonyme|authenticated
	 * @param	array	$additionalParams	Additional parameters
	 * @return	int
	 */
	public function getVisitsCount($period, $date, $type = "all", array $additionalParams);
	
	/**
	 * Return number of visits with users logged in to the specific library
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	 */
	public function getVisitsCountForLibrary($period, $date, $userLibCard);
	
	/**
	 * Returns number of searches
	 * 
	 * @param	string	$type	all|anonyme|authenticated
	 * @return	int
	 */
	public function getSearchCount($period, $date, $type = "all");
	
	/**
	 * Return number of searches with users logged in to the specific library
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	 */
	public function getSearchCountForLibrary($period, $date, $userLibCard);
	
	/**
	 * Returns all searched keywords in VuFind.
	 * If userLibCard is provided, returns searched keywords in VuFind 
	 * by users logged in to specific library.
	 * When rawData is set to 1, returns csv
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	int		$userLibCard
	 * @param	int|boolean	$rawData
	 * @return	array|csv
	 */
	public function getSearchKeywords($period, $date, $userLibCard = null, $rawData = null);
	
	/**
	 * Returns number of viewed records
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$type	all|anonyme|authenticated
	 * @return	int
	 */
	public function getViewedRecordsCount($period, $date, $type = "all");
	
	/**
	 * Returns number of viewed records with users logged in to the specific library
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	 */
	public function getViewedRecordsCountForLibrary($period, $date, $userLibCard);
	
	/**
	 * Returns all viewed records in VuFind.
	 * If userLibCard is provided, returns viewed records in VuFind 
	 * by users logged in to specific library.
	 * When rawData is set to 1, returns csv
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	int		$userLibCard
	 * @param	int|boolean	$rawData
	 * @return	array|csv
	 */
	public function getViewedRecords($period, $date, $userLibCard = null, $rawData = null);
	
	/**
	 * Returns number of new visitors
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$type	all|anonyme|authenticated
	 * @return	int
	 */
	public function getNewVisitorsCount($period, $date, $type = "all");
	
	
	/**
	 * Returns number of new visitors with users logged in to the specific library
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	 */
	public function getNewVisitorsCountForLibrary($period, $date, $userLibCard);
	
	/**
	 * Returns all new visitors
	 * If userLibCard is provided, returns new visitors logged in to specific library.
	 * When rawData is set to 1, returns csv
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	int		$userLibCard
	 * @param	int|boolean	$rawData
	 * @return	array|csv
	 */
	public function getNewVisitors($period, $date, $userLibCard = null, $rawData = null);
	
	/**
	 * Returns number of returning visitors
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$type	all|anonyme|authenticated
	 * @return	int
	 */
	public function getReturningVisitorsCount($period, $date, $type = "all");
	
	
	/**
	 * Returns number of returning visitors with users logged in to the specific library
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	*/
	public function getReturningVisitorsCountForLibrary($period, $date, $userLibCard);
	
	/**
	 * Returns all returning visitors
	 * If userLibCard is provided, returns returning visitors logged in to specific library.
	 * When rawData is set to 1, returns csv
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	int		$userLibCard
	 * @param	int|boolean	$rawData
	 * @return	array|csv
	*/
	public function getReturningVisitors($period, $date, $userLibCard = null, $rawData = null);
	
	/**
	 * Returns number of not found search keywords
	 * 
	 * @param	string	$type	all|anonyme|authenticated
	 * @return	int
	 */
	public function getNotFoundSearchKeywordsCount($period, $date, $type = "all");
	
	/**
	 * Return number of not found search keywords with users logged in to the specific library
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	*/
	public function getNotFoundSearchKeywordsCountForLibrary($period, $date, $userLibCard);
	
	/**
	 * Returns all not found search keywords in VuFind.
	 * If userLibCard is provided, returns not found search keywords in VuFind
	 * by users logged in to specific library.
	 * When rawData is set to 1, returns csv
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	int		$userLibCard
	 * @param	int|boolean	$rawData
	 * @return	array|csv
	*/
	public function getNotFoundSearchKeywords($period, $date, $userLibCard = null, $rawData = null);
	
	/**
	 * Returns number of catalog accesses
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$type	all|anonyme|authenticated
	 * @return	int
	 */
	public function getCatalogAccessCount($period, $date, $type = "all");
	
	
	/**
	 * Returns number of catalog accesses with users logged in to the specific library
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	 */
	public function getCatalogAccessCountForLibrary($period, $date, $userLibCard);
	
	/**
	 * Returns number of item prolongs
	 * When userLibCard is provided, returns number of item prolongs with users logged in to the specific library
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	 */
	public function getItemProlongsCount($period, $date, $userLibCard = null);
	
	/**
	 * Returns number of item reservations
	 * When userLibCard is provided, returns number of item reservations with users logged in to the specific library
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	 */
	public function getItemReservationsCount($period, $date, $userLibCard);
	
	/**
	 * Returns number of orders
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$type	all|anonyme|authenticated
	 * @return	int
	 */
	public function getOrdersCount($period, $date, $type = "all");
	
	
	/**
	 * Returns number of orders with users logged in to the specific library
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	*/
	public function getOrdersCountForLibrary($period, $date, $userLibCard);
	
	/**
	 * Returns number of user registrations
	 * When userLibCard is provided, returns number of user registrations with users logged in to the specific library
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	 */
	public function getUserRegistrationsCount($period, $date, $userLibCard = null);
	
	/**
	 * Returns number of user prolonagtions
	 * When userLibCard is provided, returns number of user prolonagtions with users logged in to the specific library
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	 */
	public function getUserProlongationsCount($period, $date, $userLibCard = null);
	
	/**
	 * Returns number of transactions
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$type	all|anonyme|authenticated
	 * @return	int
	 */
	public function getTransactionCount($period, $date, $type = "all");
	
	
	/**
	 * Returns number of transactions with users logged in to the specific library
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	*/
	public function getTransactionCountForLibrary($period, $date, $userLibCard);
	
	/**
	 * Returns sum of transactions
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$type	all|anonyme|authenticated
	 * @return	double
	 */
	public function getTransactionSumCount($period, $date, $type = "all");
	
	
	/**
	 * Returns sum of transactions with users logged in to the specific library
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	double
	*/
	public function getTransactionSumForLibrary($period, $date, $userLibCard);
	
}