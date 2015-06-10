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
	 * Returns number of visits
	 * 
	 * @param	string	$period				day|week|month|year|range
	 * @param	string	$date				YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 										YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$type				all|anonyme|authenticated
	 * @param	array	$additionalParams	Additional parameters
	 * @return	int
	 */
	public function getVisitsCount($period, $date, $type = "all", array $additionalParams = null);
	
	/**
	 * Return number of visits with users logged in to the specific library
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @param	array	$additionalParams	Additional parameters
	 * @return	int
	 */
	public function getVisitsCountForLibrary($period, $date, $userLibCard, array $additionalParams = null);
	
	/**
	 * Returns array of visits info
	 *
	 * @param	string	$period				day|week|month|year|range
	 * @param	string	$date				YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 										YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$type				all|anonyme|authenticated
	 * @param	array	$additionalParams	Additional parameters
	 * @return	int
	 */
	public function getVisitsInfo($period, $date, $type = "all", array $additionalParams = null);
	
	/**
	 * Returns array of actions info
	 *
	 * @param	string	$period				day|week|month|year|range
	 * @param	string	$date				YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 										YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$type				all|anonyme|authenticated
	 * @param	array	$additionalParams	Additional parameters
	 * @return	int
	 */
	public function getActionsInfo($period, $date, $type = "all", array $additionalParams = null);
	
	/**
	 * Returns array of info with users logged in to the specific library
	 *
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	 */
	public function getVisitsInfoForLibrary($period, $date, $userLibCard, array $additionalParams = null);
	
	/**
	 * Returns number of users, who were online in last x minutes.
	 * 
	 * @param	int		$lastMinutes
	 * @param	string	$userLibCard
	 * @param	array	$additionalParams
	 * 
	 * @return	int
	 */
	public function getOnlineUsers($lastMinutes = 10, $userLibCard = null, array $additionalParams = null);
	
	/**
	 * Returns number of search keywords with found results
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	 */
	public function getFoundSearchKeywordsCount($period, $date, $userLibCard = null);
	
	/**
	 * Returns number of search keywords with no results
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	 */
	public function getNoResultSearchKeywordsCount($period, $date, $userLibCard = null);
	
	/**
	 * Returns array of search keywords with found results
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	int		$filterLimit	Max rows in result. -1 = all
	 * @param	string	$userLibCard
	 * @param	int|boolean	$rawData	If true, returns CSV
	 * @return	int
	 */
	public function getFoundSearchKeywords($period, $date, $filterLimit="-1", $userLibCard = null, $rawData = null);
	
	/**
	 * Returns array of search keywords with no results
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	int		$filterLimit	Max rows in result. -1 = all
	 * @param	string	$userLibCard
	 * @param	int|boolean	$rawData	If true, returns CSV
	 * @return	int
	 */
	public function getNoResultSearchKeywords($period, $date, $filterLimit="-1" ,$userLibCard = null, $rawData = null);
	
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
	 * Returns number of records visits
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$type	all|anonyme|authenticated
	 * @return	int
	 */
	public function getNbRecordVisits($period, $date, $type = "all");
	
	/**
	 * Returns number of records visits with users logged in to the specific library
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	int
	 */
	public function getNbRecordVisitsForLibrary($period, $date, $userLibCard);
	
	/**
	 * Returns number of visited records in VuFind.
	 * When rawData is set to 1, returns csv
	 * 
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$type	all|anonyme|authenticated
	 * @param	array	$additionalParams
	 * @return	array|csv
	 */
	public function getNbViewedRecords($period, $date, $type = "all", array $additionalParams = null);
	
	/**
	 * Returns number of visited records in VuFind.
	 * If userLibCard is provided, returns number of visited records in VuFind
	 * by users logged in to specific library.
	 * When rawData is set to 1, returns csv
	 *
	 * @param	string	$period	day|week|month|year|range
	 * @param	string	$date	YYYY-MM-DD|today|yesterday|lastX|previousX|
	 * 							YYYY-MM-DD,YYYY-MM-DD|YYYY-MM-DD,today|YYYY-MM-DD,yesterday
	 * @param	string	$userLibCard
	 * @return	array|csv
	 */
	public function getNbViewedRecordsForLibrary($period, $date, $userLibCard);
	
	/**
	 * Returns array of visited records in VuFind.
	 * If userLibCard is provided, returns array of visited records in VuFind
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