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
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace MZKCommon\ILS\Driver;
use VuFind\Exception\ILS as ILSException;
use Zend\Log\LoggerInterface;
use VuFindHttp\HttpServiceInterface;
use DateTime;
use VuFind\Exception\Date as DateException;
use VuFind\ILS\Driver\Aleph as AlephBase;

class Aleph extends AlephBase
{
    
    protected $recordStatus;
    
    protected $availabilitySource = null;
    
    public function __construct(\VuFind\Date\Converter $dateConverter,
        \VuFind\Cache\Manager $cacheManager = null, \VuFindSearch\Service $searchService = null,
        \MZKCommon\Db\Table\RecordStatus $recordStatus = null
    ) {
        parent::__construct($dateConverter, $cacheManager, $searchService);
        $this->recordStatus = $recordStatus;
    }
    
    public function init()
    {
        parent::init();
        if (isset($this->config['Availability']['source'])) {
            $this->availabilitySource = $this->config['Availability']['source'];
        }
    }
    
    public function getStatuses($idList)
    {
        $statuses = $this->recordStatus->getByIds($this->availabilitySource, $idList);
        $foundIds = array();
        $holdings = array();
        foreach ($statuses as &$status) {
            $foundIds[] = $status['record_id'];
            $holding = array();
            $holding['id'] = $status['record_id'];
            $holding['record_id'] = $status['record_id'];
            $holding['absent_total'] = $status['absent_total'];
            $holding['absent_avail'] = $status['absent_total'] - $status['absent_on_loan']; 
            $holding['present_total'] = $status['present_total'];
            $holding['present_avail'] = $status['present_total'] - $status['present_on_loan'];
            $holding['availability'] = ($holding['absent_avail'] > 0
                || $holding['present_avail'] > 0);
            $holdings[] = array($holding);
        }
        $missingIds = array_diff($idList, $foundIds);
        foreach ($missingIds as $missingId) {
            $holding = array(
                'id' => $missingId,
                'record_id' => $missingId,
                'absent_total'  => 0,
                'absent_avail'  => 0,
                'present_total' => 0,
                'present_avail' => 0,
                'availability'  => false,
            );
            $holdings[] = array($holding);
        }
        return $holdings;
    }

}