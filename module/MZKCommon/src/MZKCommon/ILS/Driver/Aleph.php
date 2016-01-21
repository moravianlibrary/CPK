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
use MZKCommon\ILS\Driver\AlephMZK as AlephBase;

class Aleph extends AlephBase
{

    protected $recordStatus = null;

    protected $availabilitySource = null;

    protected $favoritesUrl = null;

    protected $userCgiUrl = null;

    protected $wwwuser = null;

    protected $wwwpasswd = null;

    protected $hmacKey = null;

    protected $paymentUrl = null;

    protected $prolongRegistrationUrl = null;

    protected $prolongRegistrationStatus = null;

    /*
    // FIXME: move to configuration file
    const PAYMENT_URL = 'https://aleph.mzk.cz/cgi-bin/c-gpe1-vufind.pl';

    // FIXME: move to configuration file
    const PROLONG_REGISTRATION_URL = 'http://aleph.mzk.cz/cgi-bin/prodl_reg.pl';
    */

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
        if (isset($this->config['Catalog']['fav_cgi_url'])) {
            $this->favoritesUrl = $this->config['Catalog']['fav_cgi_url'];
        }
        if (isset($this->config['Catalog']['user_cgi_url'])) {
            $this->userCgiUrl = $this->config['Catalog']['user_cgi_url'];
        }
        if (isset($this->config['Catalog']['hmac_key'])) {
            $this->hmacKey = $this->config['Catalog']['hmac_key'];
        }
        if (isset($this->config['Catalog']['wwwuser']) && isset($this->config['Catalog']['wwwpasswd'])) {
            $this->wwwuser = $this->config['Catalog']['wwwuser'];
            $this->wwwpasswd = $this->config['Catalog']['wwwpasswd'];
        }
        if (isset($this->config['Catalog']['payment_url'])) {
            $this->paymentUrl = $this->config['Catalog']['payment_url'];
        }
        if (isset($this->config['Catalog']['prolong_registration_url'])) {
            $this->prolongRegistrationUrl = $this->config['Catalog']['prolong_registration_url'];
        }
        if (isset($this->config['Catalog']['prolong_registration_status'])) {
            $this->prolongRegistrationStatus = $this->config['Catalog']['prolong_registration_status'];
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

    public function getMyTransactions($user, $history=false, $limit = 0)
    {
        $transactions = parent::getMyTransactions($user, $history, $limit);
        if ($history) {
            return $transactions;
        }
        $patronId = $user['id'];
        foreach ($transactions as &$transaction) {
            if (!$transaction['renewable']) {
                $bibId  = $transaction['id'];
                $itemId = $transaction['z36_item_id'];
                try {
                    $holdingInfo = $this->getHoldingInfoForItem($patronId, $bibId, $itemId);
                    $transaction['reserved'] = ($holdingInfo['order'] > 1);
                } catch (ILSException $ex) {
                    // nothing to do
                }
            }
        }
        return $transactions;
    }

    /**
     *
     * Get Favorite Items from ILS
     *
     * @param mixed  $patron  Patron data
     * @return mixed          An array with favorite items (each item contains id, folder and note)
     * @access public
     */
    public function getMyFavorites($patron)
    {
        if ($this->favoritesUrl == null) {
            return array(); // graceful degradation
        }
        $params = array('id' => $patron['id']);
        $response = $this->httpService->get($this->favoritesUrl, $params);
        if (!$response->isSuccess()) {
            throw new ILSException('HTTP error');
        }
        $answer = $response->getBody();
        $xml = simplexml_load_string($answer);
        $result = array();
        foreach ($xml->{'favourite'} as $fav) {
            $result[] = array(
                'id'     => (string) $fav->{'id'},
                'folder' => (string) $fav->{'folder'},
                'note'   => (string) $fav->{'note'}
            );
        }
        return $result;
    }

    public function getUserNickname($patron)
    {
        $params = array(
            'op'           => 'get_nickname',
        );
        $xml = $this->changeUserRequest($patron, $params, true);
        if ($xml->error) {
            if ($xml->error == 'no nick') {
                return null;
            } else {
                throw new ILSException($xml->error);
            }
        } else {
            return $xml->nick;
        }
    }

    public function changeUserNickname($patron, $newAlias)
    {
        $params = array(
            'op'           => 'change_nickname',
            'new_nickname' => $newAlias,
        );
        return $this->changeUserRequest($patron, $params);
    }

    public function changeUserPassword($patron, $oldPassword, $newPassword)
    {
        $params = array(
            'op'      => 'change_password',
            'old_pwd' => $oldPassword,
            'new_pwd' => $newPassword,
        );
        return $this->changeUserRequest($patron, $params);
    }

    public function changeUserEmailAddress($patron, $newEmailAddress)
    {
        $params = array(
            'op'      => 'change_email',
            'email' => $newEmailAddress,
        );
        return $this->changeUserRequest($patron, $params);
    }

    public function changeUserRequest($patron, $params, $returnResult = false)
    {
        if ($this->userCgiUrl == null) {
            throw new \Exception('Not supported, missing [Catalog][user_cgi_url] section in config');
        }
        $params['id']            = $patron['id'];
        $params['user_name']     = $this->wwwuser;
        $params['user_password'] = $this->wwwpasswd;
        $response = $this->httpService->get($this->userCgiUrl, $params);
        $answer = $response->getBody();
        $xml = simplexml_load_string($answer);
        if ($returnResult) {
            return $xml;
        }
        if ($xml->error) {
            throw new ILSException($xml->error);
        } else {
            return true;
        }
    }

    public function getPaymentURL($patron, $fine)
    {
        if ($this->paymentUrl == null) {
            return null;
        }
        $params = array (
            'id'     => $patron['id'],
            'adm'    => $this->useradm,
            'amount' => $fine,
            'time'   => time(),
        );
        $query = http_build_query($params);
        $url = $this->paymentUrl . '?' . $query;
        return $url;
    }

    public function getProlongRegistrationUrl($patron)
    {
        if ($this->prolongRegistrationUrl == null) {
            return null;
        }
        $status = '03';
        $expire = date_create_from_format('d. m. Y', $patron['expire']);
        $dateDiff = date_diff(date_create(), $expire);
        $daysDiff =  (($dateDiff->invert == 0) ? 1: -1) *  $dateDiff->days;
        if ($daysDiff > 31) {
            return null;
        }
        $hash = hash_hmac('sha256', $patron['id'], $this->hmacKey, true);
        $hash = base64_encode($hash);
        $params = array (
            'id'           => $patron['id'],
            'status_cten'  => $this->prolongRegistrationStatus,
            'from'         => 'vufind',
            'hmac'         => $hash,
        );
        $query = http_build_query($params);
        $url = $this->prolongRegistrationUrl . '?' . $query;
        return $url;

    }

}
