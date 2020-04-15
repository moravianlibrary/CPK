<?php
/**
 * Ajax Controller Module
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
 * @package  Controller
 * @author   Martin Kravec <Martin.Kravec@mzk.cz>; Jiří Kozlovský <Jiri.Kozlovsky@mzk.cz>; Matúš Šabík <Matus.Sabik@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace CPK\Controller;

use Mzk\ZiskejApi\ResponseModel\Ticket;
use VuFind\Controller\AjaxController as AjaxControllerBase;

/**
 * This controller handles global AJAX functionality
 *
 * @category VuFind2
 * @package  Controller
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class AjaxController extends AjaxControllerBase
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    const STATUS_NOT_OK = 'NOT_OK';    // must login first

    protected $skcLinks = 'http://aleph.nkp.cz/web/cpk/skc_links';

    /**
     * Downloads SFX JIB content for current record.
     *
     * @param string $_GET ['institute']
     *
     * @return array
     */
    public function callLinkServerAjax()
    {
        $multiBackendConfig = $this->getConfig('MultiBackend');

        $recordID = $this->params()->fromQuery('recordID');
        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        $recordDriver = $recordLoader->load($recordID);

        $parentRecordID = $recordDriver->getParentRecordID();
        $parentRecordDriver = $recordLoader->load($parentRecordID);

        $childrenIds = $parentRecordDriver->getChildrenIds();

        $linkServers = [];
        foreach ($childrenIds as $childrenId) {
            $sourceInstitute = explode('.', $childrenId)[0];

            $lsID = 'ls_' . $sourceInstitute;
            $linkServer = $multiBackendConfig->LinkServers->$lsID;

            if ($linkServer === null) // if there is no configuration in Multibackend.ini, use default settings
            {
                $linkServer = $multiBackendConfig->LinkServers->ls_default;
            }

            $instituteLsShortcut = explode("|", $linkServer)[0];
            $instituteLsLink = explode("|", $linkServer)[1];

            if (!array_key_exists($instituteLsShortcut, $linkServers)) {
                $linkServers[$instituteLsShortcut] = $instituteLsLink;
            }
        }

        $isn = $parentRecordDriver->getIsn();
        if ($isn === false) {
            $isn = $recordDriver->getIsn();
        }

        $openUrl = $recordDriver->getOpenURL();
        $additionalParams = array();
        parse_str($openUrl, $additionalParams);

        foreach ($additionalParams as $key => $val) {
            $additionalParams[str_replace("rft_", "rft.", $key)] = $val;
        }

        if (substr($isn, 0, 1) === 'M') {
            $isnKey = "rft.ismn";
        } elseif ((strlen($isn) === 8) or (strlen($isn) === 9)) {
            $isnKey = "rft.issn";
        } else { // (strlen($isn) === 10) OR (strlen($isn) === 13)
            $isnKey = "rft.isbn";
        }

        $params = array(
            'ctx_ver' => 'Z39.88-2004',
            'ctx_enc' => 'info:ofi/enc:UTF-8',
            'sfx.response_type' => 'simplexml',
            $isnKey => str_replace("-", "", (string)$isn)
        );

        $allParams = array_merge($params, $additionalParams);

        $wantItFactory = $this->getServiceLocator()->get('WantIt\Factory');
        $electronicChoiceHandler = $wantItFactory->createElectronicChoiceHandlerObject(
            $recordDriver);

        $sfxResult = [];
        foreach ($linkServers as $shortcut => $link) {
            $allParams['sfx.institute'] = $shortcut;
            $sfxResult[] = $electronicChoiceHandler->getRequestDataResponseAsArray(
                $link, $allParams);
        }

        $vars[] = array(
            'sfxResult' => $sfxResult
        );

        // Done
        return $this->output($vars, self::STATUS_OK);
    }

    /**
     * Downloads SFX JIB content for current record.
     *
     * @param string $institute
     *
     * @return array
     */
    public function get866Ajax()
    {
        $parentRecordID = $this->params()->fromQuery('parentRecordID');
        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        $recordDriver = $recordLoader->load($parentRecordID);

        $field866 = $recordDriver->get866Data();

        foreach ($field866 as $key => $field) {
            $fieldArr = explode("|", $field);
            $source = $fieldArr[0];
            $translation = $this->translate('source_' . $source);
            $field866[$key] = str_replace($source, $translation, $field);
        }

        $vars[] = array(
            'field866' => $field866
        );

        // Done
        return $this->output($vars, self::STATUS_OK);
    }

    public function getHoldingsStatusesAjax()
    {
        $request = $this->getRequest();
        $ids = $this->params()->fromPost('ids');
        $bibId = $this->params()->fromPost('bibId');
        $filter = $this->params()->fromPost('activeFilter');
        $nit = $this->params()->fromPost('next_item_token');

        $viewRend = $this->getViewRenderer();

        if ($ids === null || !is_array($ids) || empty($ids)) {
            return $this->output(
                [
                    'status' => $this->getTranslatedUnknownStatus($viewRend)
                ], self::STATUS_ERROR);
        } elseif ($bibId === null) {
            return $this->output([
                'statuses' => $this->getTranslatedUnknownStatuses($ids, $viewRend),
                'msg' => 'No bibId provided !'
            ], self::STATUS_ERROR);
        }

        $ids = array_filter($ids);
        $ids = array_unique($ids);

        $ilsDriver = $this->getILS()->getDriver();

        if ($ilsDriver instanceof \CPK\ILS\Driver\MultiBackend) {

            try {
                $user = $this->getAuthManager()->isLoggedIn();
                $statuses = $ilsDriver->getStatuses($ids, $bibId, $filter, $nit, $user);
            } catch (\Exception $e) {
                return $this->output([
                    'statuses' => $this->getTranslatedUnknownStatuses($ids, $viewRend),
                    'msg' => $e->getMessage(),
                    'code' => $e->getCode()
                ], self::STATUS_ERROR);
            }

            if (null === $statuses || empty($statuses)) {
                return $this->output([
                    'statuses' => $this->getTranslatedUnknownStatuses($ids, $viewRend),
                    'msg' => '$ilsDriver->getStatuses returned nothing'
                ], self::STATUS_ERROR);
            }

            $itemsStatuses = [];

            if (!empty($statuses)) {
                if (array_key_exists('next_item_token', $statuses[0])) {
                    $nextItemToken = $statuses[0]['next_item_token'];
                } else {
                    $nextItemToken = null;
                }
                if (array_key_exists('usedGetStatus', $statuses[0])) {
                    $usedGetStatus = $statuses[0]['usedGetStatus'];
                } else {
                    $usedGetStatus = null;
                }
                if (array_key_exists('usedAleph', $statuses[0])) {
                    $usedAleph = $statuses[0]['usedAleph'];
                } else {
                    $usedAleph = null;
                }
            } else {
                $nextItemToken = $usedGetStatus = $usedAleph = null;
            }

            foreach ($statuses as $status) {
                $unescId = $status['item_id'];
                $id = str_replace(':', '\:', $status['item_id']);

                $itemsStatuses[$id] = [];

                if (!empty($status['status'])) {
                    $itemsStatuses[$id]['status'] = $viewRend->transEsc(
                        'status_' . $status['status'], null, $status['status']);
                } else {
                    // The status is empty - set label to 'label-danger'
                    $itemsStatuses[$id]['label'] = 'label-unknown';

                    // And set the status to unknown status
                    $itemsStatuses[$id]['status'] = $this->getTranslatedUnknownStatus(
                        $viewRend);
                }

                if (!empty($status['duedate'])) {
                    $itemsStatuses[$id]['duedate'] = $status['duedate'];
                }

                if (!empty($status['hold_type'])) {
                    $itemsStatuses[$id]['holdtype'] = $viewRend->transEsc(
                        $status['hold_type']);
                }

                if (!empty($status['label'])) {
                    $itemsStatuses[$id]['label'] = $status['label'];
                }

                if (!empty($status['addLink'])) {
                    $itemsStatuses[$id]['addLink'] = $status['addLink'];
                }

                if (!empty($status['availability'])) {
                    $itemsStatuses[$id]['availability'] = $viewRend->transEsc(
                        'availability_' . $status['availability'], null, $status['availability']);
                }

                if (!empty($status['collection'])) {
                    $itemsStatuses[$id]['collection'] = $status['collection'];
                }

                if (!empty($status['department'])) {
                    $itemsStatuses[$id]['department'] = $status['department'];
                }

                if (!empty($status['location'])) {
                    $itemsStatuses[$id]['location'] = $status['location'];
                }

                $key = array_search(trim($unescId), $ids);

                if ($key !== false) {
                    unset($ids[$key]);
                }
            }

            if ($nextItemToken || $usedGetStatus || $usedAleph) {
                $retVal['remaining'] = $ids;
                $retVal['next_item_token'] = $nextItemToken;
            } else {
                foreach ($ids as $id) {
                    $itemsStatuses[$id]['availability'] = $viewRend->transEsc('availability_Not For Loan');
                    $itemsStatuses[$id]['status'] = $viewRend->transEsc('status_Unknown Status');
                    $itemsStatuses[$id]['label'] = 'label-unknown';
                }
            }

            $retVal['statuses'] = $itemsStatuses;
            return $this->output($retVal, self::STATUS_OK);
        } else {
            return $this->output([
                'statuses' => $this->getTranslatedUnknownStatuses($ids, $viewRend),
                'msg' => "ILS Driver isn't instanceof MultiBackend - ending job now."
            ], self::STATUS_ERROR);
        }
    }

    public function getCaslinHoldingsStatusesAjax()
    {
        $request = $this->getRequest();
        $ids = $this->params()->fromPost('ids');
        $siglas = $this->params()->fromPost('siglas');
        $bibId = $this->params()->fromPost('bibId');
        $filter = $this->params()->fromPost('activeFilter');
        $nit = $this->params()->fromPost('next_item_token');

        $viewRend = $this->getViewRenderer();

        if ($siglas === null || !is_array($siglas) || empty($siglas)) {
            return $this->output(
                [
                    'status' => $this->getTranslatedUnknownStatus($viewRend)
                ], self::STATUS_ERROR);
        } elseif ($bibId === null) {
            return $this->output([
                'statuses' => $this->getTranslatedUnknownStatuses($ids, $viewRend),
                'msg' => 'No bibId provided !'
            ], self::STATUS_ERROR);
        }

        $retVal = [];
        $records = [];

        if (count($ids)) {
            foreach ($ids as $key => $id) {
                $records[$id] = $siglas[$key];
            }
        } else {
            return $this->output([
                'statuses' => $this->getTranslatedUnknownStatuses($ids, $viewRend),
                'msg' => 'No records available!'
            ], self::STATUS_ERROR);
        }

        if (count($records)) {

            try {
                $ch = curl_init($this->skcLinks);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
                $html = curl_exec($ch);
                curl_close($ch);

                $lines = explode("\n", $html);
                $table = [];
                foreach ($lines as $line) {
                    $cols = explode(";", $line);
                    if (isset($cols[3])) {
                        $table[] = [
                            'siglaCol' => $cols[0],
                            'param' => $cols[2],
                            'linkCol' => $cols[3],
                        ];
                    }
                }

            } catch (\Exception $e) {
                return $this->output('false', self::STATUS_ERROR);
            }

            foreach ($records as $id => $sigla) {
                foreach ($table as $cols) {
                    if (strpos($cols['siglaCol'], $sigla) !== false) {
                        $link = $cols['linkCol'] . $cols['param'];
                        if (strpos(strtolower($cols['linkCol']), 'carmen') !== false) { // if Carmen, use last 8 chars of ID
                            $link .= substr($id, -8);
                        } else {
                            $link .= $id;
                        }
                        $retVal['links'][$id] = $link;
                    }
                }

            }
        } else {
            return $this->output([
                'statuses' => $this->getTranslatedUnknownStatuses($ids, $viewRend),
                'msg' => 'No records available!'
            ], self::STATUS_ERROR);
        }

        return $this->output($retVal, self::STATUS_OK);
    }

    /**
     * Returns subfileds of MARC 996 field for specific recordID
     *
     * @param string $_POST ['record']
     * @param string $_POST ['field']
     * @param string $_POST ['subfields']
     *            subfileds
     *
     * @return array subfields values
     */
    public function getMarc996ArrayAjax()
    {
        $recordID = $this->params()->fromQuery('recordID');
        $field = $this->params()->fromQuery('field');
        $subfieldsArray = explode(",", $this->params()->fromQuery('subfields'));

        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');

        $recordDriver = $recordLoader->load($recordID);
        $arr = $recordDriver->get996($subfieldsArray);

        $vars[] = array(
            'arr' => $arr
        );

        // Done
        return $this->output($vars, self::STATUS_OK);
    }

    public function getMyProfileAjax()
    {
        // Get the cat_username being requested
        $cat_username = $this->params()->fromPost('cat_username');

        $hasPermissions = $this->hasPermissions($cat_username);

        if ($hasPermissions instanceof \Zend\Http\Response) {
            return $hasPermissions;
        }

        $ilsDriver = $this->getILS()->getDriver();

        if ($ilsDriver instanceof \CPK\ILS\Driver\MultiBackend) {

            $patron = [
                'cat_username' => $cat_username,
                'id' => $cat_username
            ];

            try {
                // Try to get the profile ..
                $profile = $ilsDriver->getMyProfile($patron);

                if (is_array($profile) && count($profile) === 0) {
                    return $this->output(
                        [
                            'cat_username' => $cat_username,
                            'message' => $this->translate(
                                'profile_fetch_problem'),
                            'consideration' => 'There is a chance you have missing configuration file called "' .
                                explode('.', $cat_username)[0] . '.ini"'
                        ], self::STATUS_ERROR);
                }
            } catch (\Exception $e) {
                return $this->outputException($e, $cat_username);
            }
            $expire = date_create_from_format('d. m. Y', $profile['expire']);
            $dateDiff = date_diff($expire, date_create());
            $message = null;
            $prolongRegistrationUrl = null;
            if ($dateDiff->days < 31 && $dateDiff->invert != 0) {
                $message = $this->translate('library_card_expiration_warning');
                $prolongRegistrationUrl = $ilsDriver->getProlongRegistrationUrl($patron);
            }
            if ($dateDiff->invert == 0 && $dateDiff->days > 0) {
                $message = $this->translate('library_card_expirated_warning');
                $prolongRegistrationUrl = $ilsDriver->getProlongRegistrationUrl($patron);
            }
            $profile['message'] = $message;
            $profile['prolongRegistrationUrl'] = $prolongRegistrationUrl;
            $profile['prolongText'] = $this->translate('prolong_registration_url');
            return $this->output($profile, self::STATUS_OK);
        } else {
            return $this->output(
                [
                    'cat_username' => str_replace('.', '\.', $cat_username),
                    'message' => 'ILS Driver isn\'t instanceof MultiBackend - ending job now.'
                ],
                self::STATUS_ERROR);
        }
    }

    public function getMyHoldsAjax()
    {
        // Get the cat_username being requested
        $cat_username = $this->params()->fromPost('cat_username');
        $type = $this->params()->fromPost('type');

        $hasPermissions = $this->hasPermissions($cat_username);
        if ($hasPermissions instanceof \Zend\Http\Response) {
            return $hasPermissions;
        }

        $renderer = $this->getViewRenderer();

        $catalog = $this->getILS();

        $ilsDriver = $catalog->getDriver();

        if ($ilsDriver instanceof \CPK\ILS\Driver\MultiBackend) {

            if ($cat_username == 'ziskejAjaxLoad') {
                $toRet = $this->getZiskejTickets($type);
                return $this->output($toRet, self::STATUS_OK);
            }
            $patron = [
                'cat_username' => $cat_username,
                'id' => $cat_username
            ];

            try {
                // Try to get the profile ..
                $holds = $catalog->getMyHolds($patron);
            } catch (\Exception $e) {
                return $this->outputException($e, $cat_username);
            }

            $recordList = $obalky = [];

            $libraryIdentity = $this->createViewModel();

            // Let's assume there is not avaiable any cancelling
            $libraryIdentity->cancelForm = false;

            $cancelStatus = $catalog->checkFunction('cancelHolds', compact('patron'));

            foreach ($holds as $hold) {
                // Add cancel details if appropriate:
                $hold = $this->holds()->addCancelDetails($catalog, $hold,
                    $cancelStatus);
                if ($cancelStatus && $cancelStatus['function'] != "getCancelHoldLink" &&
                    isset($hold['cancel_details'])) {
                    // Enable cancel form if necessary:
                    $libraryIdentity->cancelForm = true;
                }

                // Build record driver:
                $resource = $this->getDriverForILSRecord($hold);
                $recordList[] = $resource;

                $recordId = $resource->getUniqueId();
                $bibInfo = $renderer->record($resource)->getObalkyKnihJSONV3();
                if ($bibInfo) {
                    $recordId = "#cover_$recordId";

                    $bibInfo = json_decode($bibInfo);

                    $recordId = preg_replace("/[\.:]/", "", $recordId);

                    $obalky[$recordId] = [
                        'bibInfo' => $bibInfo,
                        'advert' => $renderer->record($resource)->getObalkyKnihAdvert(
                            'checkedout')
                    ];
                }
            }

            // Get List of PickUp Libraries based on patron's home library
            try {
                $libraryIdentity->pickup = $catalog->getPickUpLocations($patron);
            } catch (\Exception $e) {
                // Do nothing; if we're unable to load information about pickup
                // locations, they are not supported and we should ignore them.
            }

            $libraryIdentity->recordList = $recordList;

            $html = $renderer->render('myresearch/holds-from-identity.phtml',
                [
                    'libraryIdentity' => $libraryIdentity,
                    'AJAX' => true
                ]);

            $cat_username = str_replace(':', '\:', $cat_username);

            $toRet = [
                'html' => $html,
                'obalky' => $obalky,
                'canCancel' => $libraryIdentity->cancelForm,
                'cat_username' => str_replace('.', '\.', $cat_username)
            ];

            return $this->output($toRet, self::STATUS_OK);
        } else {
            return $this->output(
                [
                    'cat_username' => str_replace('.', '\.', $cat_username),
                    'message' => 'ILS Driver isn\'t instanceof MultiBackend - ending job now.'
                ],
                self::STATUS_ERROR);
        }
    }

    public function getMyFinesAjax()
    {
        // Get the cat_username being requested
        $cat_username = $this->params()->fromPost('cat_username');

        $hasPermissions = $this->hasPermissions($cat_username);

        if ($hasPermissions instanceof \Zend\Http\Response) {
            return $hasPermissions;
        }

        $ilsDriver = $this->getILS()->getDriver();

        if ($ilsDriver instanceof \CPK\ILS\Driver\MultiBackend) {

            $patron = [
                'cat_username' => $cat_username,
                'id' => $cat_username
            ];

            try {
                // Try to get the profile ..
                $fines = $ilsDriver->getMyFines($patron);

                $data['cat_username'] = $cat_username;
                $data['fines'] = $fines;
                $data['source'] = $fines['source'];

                $totalFine = 0;
                if (!empty($fines)) {
                    foreach ($fines as $fine) {
                        $totalFine += ($fine['amount']);
                    }
                }
                if ($totalFine < 0) {
                    $data['paymentUrl'] = $ilsDriver->getPaymentURL($patron,
                        -1 * $totalFine);
                }
                $data['payButtonText'] = $this->translate('Online payment of fines');

            } catch (\Exception $e) {
                return $this->outputException($e, $cat_username);
            }

            return $this->output($data, self::STATUS_OK);
        } else {
            return $this->output(
                [
                    'cat_username' => $cat_username,
                    'message' => 'ILS Driver isn\'t instanceof MultiBackend - ending job now.'
                ],
                self::STATUS_ERROR);
        }
    }

    /**
     * @param string $type
     * @return array
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    protected function getZiskejTickets(string $type): array
    {
        /** @var \Mzk\ZiskejApi\Api $ziskejApi */
        $ziskejApi = $this->serviceLocator->get('Mzk\ZiskejApi\Api');

        /** @var \Zend\View\Renderer\PhpRenderer $zendRenderer */
        $zendRenderer = $this->getViewRenderer();

        /** @var \CPK\ILS\Driver\MultiBackend $cpkMultibackend */
        $cpkMultibackend = $this->getILS()->getDriver();

        $ziskejLibs = $ziskejApi->getLibraries()->getAll();
        $libraryIds = [];
        foreach ($ziskejLibs as $ziskejLib) {
            $libraryId = $cpkMultibackend->siglaToSource($ziskejLib->getSigla());
            if(in_array($libraryId, $libraryIds)){
                continue;
            }
            $libraryIds[] = $libraryId;
        }

        /** @var \VuFind\Db\Row\User $user */
        $user = $this->getAuthManager()->isLoggedIn();

        if (!$user) {
            //@todo
        }

        $data = [];

        $userSources = $user->getNonDummyInstitutions();
        $ziskejConnectedLibs = array_filter($userSources, function ($userLib) use ($libraryIds) {
            return in_array($userLib, $libraryIds);
        });

        $i = 0;
        $obalky = [];
        /** @var \VuFind\Db\Row\UserCard $userCard */
        foreach ($user->getAllUserLibraryCards() as $userCard) {
            if (in_array($userCard->home_library, $ziskejConnectedLibs)) {
                $key = $userCard->home_library;

                $data[$userCard->home_library] = [
                    'userCard' => $userCard,
                    'eppn' => $userCard->eppn,
                    'items' => [],
                    'ziskejReader' => null,
                ];

                $ziskejReader = $ziskejApi->getReader($userCard->eppn);
                if ($ziskejReader) {
                    $data[$userCard->home_library]['ziskejReader'] = $ziskejReader;
                    if ($ziskejReader->isActive()) {
                        $ticketsCollection = $ziskejApi->getTickets($userCard->eppn);
                        /** @var \Mzk\ZiskejApi\ResponseModel\Ticket $ticket */
                        foreach ($ticketsCollection->getAll() as $ticket) {
                            $i++;
                            $resource = $this->getDriverForILSRecordZiskej($ticket);
                            // obalky
                            $recordId = $resource->getUniqueId() . $i; //adding order to id (as suffix) to be able to show more covers with same id
                            $bibInfo = $zendRenderer->record($resource)->getObalkyKnihJSONV3();
                            if ($bibInfo) {
                                $recordId = "#cover_$recordId";
                                $bibInfo = json_decode($bibInfo);
                                $recordId = preg_replace("/[\.:]/", "", $recordId);
                                $obalky[$recordId] = [
                                    'bibInfo' => $bibInfo,
                                    'advert' => $zendRenderer->record($resource)->getObalkyKnihAdvert('checkedout')
                                ];
                            }
                            $data[$key]['items'][] = $resource;
                        }
                    }
                }

            }
        }

        $html = [];

        if ($type == 'holds') {
            $html = $zendRenderer->render('myresearch/holds-from-ziskej.phtml',
                [
                    'data' => $data,
                    'AJAX' => true,
                    'config' => $this->getConfig(),
                ]);
        }

        return [
            'html' => $html,
            'obalky' => $obalky,
            'cat_username' => 'ziskejAjaxLoad',
            'source' => 'ziskej'
        ];

    }

    public function getMyTransactionsAjax()
    {
        // Get the cat_username being requested
        $cat_username = $this->params()->fromPost('cat_username');

        $hasPermissions = $this->hasPermissions($cat_username);

        if ($hasPermissions instanceof \Zend\Http\Response) {
            return $hasPermissions;
        }

        $renderer = $this->getViewRenderer();

        $catalog = $this->getILS();

        $ilsDriver = $catalog->getDriver();

        if ($ilsDriver instanceof \CPK\ILS\Driver\MultiBackend) {

            $patron = [
                'cat_username' => $cat_username,
                'id' => $cat_username
            ];

            try {
                // Try to get the profile ..
                $result = $ilsDriver->getMyTransactions($patron);
            } catch (\Exception $e) {
                return $this->outputException($e, $cat_username);
            }

            $renewStatus = $catalog->checkFunction('Renewals', compact('patron'));

            $obalky = $transactions = [];

            $canRenew = $showOverdueMessage = false;

            $i = 0;

            foreach ($result as $current) {

                $i++;

                $current = $this->renewals()->addRenewDetails($catalog, $current,
                    $renewStatus);

                if ($canRenew === false && isset($current['renewable']) &&
                    $current['renewable'] && isset($current['loan_id'])) {

                    $canRenew = true;
                }

                $resource = $this->getDriverForILSRecord($current);

                // We need to let JS know what to opt for ...
                $recordId = $resource->getUniqueId() . $i; //adding order to id (as suffix) to be able to show more covers with same id
                $bibInfo = $renderer->record($resource)->getObalkyKnihJSONV3();

                if ($bibInfo) {
                    $recordId = "#cover_$recordId";

                    $bibInfo = json_decode($bibInfo);

                    $recordId = preg_replace("/[\.:]/", "", $recordId);

                    $obalky[$recordId] = [
                        'bibInfo' => $bibInfo,
                        'advert' => $renderer->record($resource)->getObalkyKnihAdvert(
                            'checkedout')
                    ];
                }

                $ilsDetails = $resource->getExtraDetail('ils_details');
                if (isset($ilsDetails['dueStatus']) &&
                    $ilsDetails['dueStatus'] == "overdue") {
                    $showOverdueMessage = true;
                }

                $transactions[] = $resource;
            }

            $html = $renderer->render('myresearch/checkedout-from-identity.phtml',
                [
                    'libraryIdentity' => compact('transactions'),
                    'AJAX' => true
                ]);

            $cat_username = str_replace(':', '\:', $cat_username);
            $splitted_cat_username = explode('.', $cat_username);

            $toRet = [
                'html' => $html,
                'obalky' => $obalky,
                'canRenew' => $canRenew,
                'overdue' => $showOverdueMessage,
                'cat_username' => join('\.', $splitted_cat_username),
                'source' => $splitted_cat_username[0]
            ];

            return $this->output($toRet, self::STATUS_OK);
        } else {
            return $this->output(
                [
                    'cat_username' => $cat_username,
                    'message' => 'ILS Driver isn\'t instanceof MultiBackend - ending job now.'
                ],
                self::STATUS_ERROR);
        }
    }

    public function getMyHistoryPageAjax()
    {
        // Get the cat_username being requested
        $post = $this->params()->fromPost();
        $cat_username = $post['cat_username'];

        $hasPermissions = $this->hasPermissions($cat_username);

        if ($hasPermissions instanceof \Zend\Http\Response) {
            return $hasPermissions;
        }

        $renderer = $this->getViewRenderer();

        $catalog = $this->getILS();

        $ilsDriver = $catalog->getDriver();

        if ($ilsDriver instanceof \CPK\ILS\Driver\MultiBackend) {

            $patron = [
                'cat_username' => $cat_username,
                'id' => $cat_username
            ];

            $page = isset($post['page']) ? $post['page'] : 1;
            $perPage = isset($post['perPage']) ? (int)$post['perPage'] : 10;

            try {
                // Try to get the profile ..
                $result = $ilsDriver->getMyHistoryPage($patron, $page, $perPage);
            } catch (\Exception $e) {
                return $this->outputExceptionWithoutPrefix($e, $cat_username);
            }

            $obalky = [];

            $i = 0;
            foreach ($result['historyPage'] as &$historyItem) {
                $resource = $this->getDriverForILSRecord($historyItem);

                if ($resource instanceof \VuFind\RecordDriver\Missing) {
                    unset($historyItem['id']);
                    $historyItem['thumbnail'] = $this->url()->fromRoute('cover-unavailable');
                } else {
                    try {

                        $displayAuthor = $resource->getDisplayAuthor();

                        if ($displayAuthor) {
                            $historyItem['author'] = $displayAuthor;
                        }

                        $title = $resource->getTitle();
                        if ($title) {
                            $historyItem['title'] = $title;
                        }

                        // We need to let JS know what to opt for ...
                        $recordId = $resource->getUniqueId() . ++$i; // adding order to id (as suffix) to be able to show more covers with same id
                        $bibInfo = $renderer->record($resource)->getObalkyKnihJSONV3();

                        if ($bibInfo) {

                            $recordId = preg_replace("/[\.:]/", "", $recordId);

                            $historyItem['uniqueId'] = $recordId;

                            $recordId = "#cover_$recordId";

                            $bibInfo = json_decode($bibInfo);

                            $obalky[$recordId] = [
                                'bibInfo' => $bibInfo,
                                'advert' => $renderer->record($resource)->getObalkyKnihAdvert('checkedouthistory')
                            ];
                        } else {
                            $historyItem['thumbnail'] = $this->url()->fromRoute('cover-unavailable');
                        }

                        $formats = $resource->getFormats();
                        if (count($formats) > 0) {
                            $historyItem['formats'] = array_map(function ($item) {
                                return [
                                    'orig' => $item,
                                    'format' => preg_replace('/[^a-z]/', '', strtolower($item))
                                ];
                            }, $formats);
                        }
                    } catch (\Exception $e) {
                        $historyItem['thumbnail'] = $this->url()->fromRoute('cover-unavailable');
                    }
                }
            }

            $result['obalky'] = $obalky;

            if (empty($result['historyPage'])) {
                $result['html'] = $renderer->render('myresearch/no-history.phtml');
                $result['source'] = $hasPermissions['home_library'];
            }

            return $this->output($result, self::STATUS_OK);
        } else {
            return $this->output([
                'cat_username' => $cat_username,
                'message' => 'ILS Driver isn\'t instanceof MultiBackend - ending job now.'
            ], self::STATUS_ERROR);
        }
    }

    public function getAllNotificationsForUserAjax()
    {

        // Check user's logged in
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            return $this->output('You are not logged in.', self::STATUS_ERROR);
        }

        $notifHandler = $this->getNotificationsHandler();

        $notifications = [];
        foreach ($user->getLibraryCards() as $libCard) {
            $notifications[$libCard->home_library] = $notifHandler->getUserCardNotifications($libCard->cat_username);
        }
        $notifications['cpk'] = $notifHandler->getUserNotifications($user);
        return $this->output($notifications, self::STATUS_OK);
    }

    /**
     * Creates new list into which it saves sent favorites.
     *
     * @return \Zend\Http\Response
     */
    public function pushFavoritesAjax()
    {

        $favorites = $this->params()->fromPost('favs');

        // Check user is logged in ..
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            return $this->output('You are not logged in.', self::STATUS_ERROR);
        }

        // Set DbTableManager ..
        $this->setDbTableManager($this->getServiceLocator()->get('VuFind\DbTablePluginManager'));

        $table = $this->getDbTable('UserList');

        $list = $table->getNew($user);
        $list->title = $this->translate('transferred_favs');
        $list->save($user);

        $params = [
            'list' => $list->id
        ];

        $recLoader = $this->getRecordLoader();

        $results = [];

        foreach ($favorites as $favorite) {

            if (!isset($favorite['title']['link'])) {
                return $this->output('Favorite client sent to server has not title link.', self::STATUS_ERROR);
            }

            $titleLink = $favorite['title']['link'];

            preg_match('/\/([^\/]+$)/', $titleLink, $matches);

            if (count($matches) === 0) {
                return $this->output('Invalid title link provided.', self::STATUS_ERROR);
            }

            $recId = $matches[1];

            $record = $recLoader->load($recId, 'Solr', false);

            $result = $record->saveToFavorites($params, $user);

            array_push($results, $result);
        }

        return $this->output($results, self::STATUS_OK);
    }

    /**
     * Retrieves the portal page
     *
     * @return \Zend\Http\Response
     */
    public function getPortalPageAjax()
    {
        $prettyUrl = $this->params()->fromQuery('prettyUrl');

        if (empty($prettyUrl) && empty($prettyUrl = $this->params()->fromPost('prettyUrl'))) {
            return $this->output('No prettyUrl provided', self::STATUS_ERROR);
        }

        $lang = $this->getServiceLocator()->has('VuFind\Translator') ? $this->getServiceLocator()
            ->get('VuFind\Translator')
            ->getLocale() : 'en';

        try {

            $portalPagesTable = $this->getTable("portalpages");
            $page = $portalPagesTable->getPage($prettyUrl, $lang);

            return $this->output($page, self::STATUS_OK);
        } catch (\Exception $e) {

            return $this->output([
                'errors' => [
                    $e->getMessage()
                ]
            ], self::STATUS_ERROR);
        }
    }

    /**
     * Comment on a record.
     *
     * @return \Zend\Http\Response
     */
    protected function commentRecordObalkyKnihAjax()
    {
        // TODO: user should not be able to add more than one comment
        $user = $this->getUser();
        if ($user === false) {
            return $this->output($this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH);
        }

        $id = $this->params()->fromPost('id');
        $comment = $this->params()->fromPost('comment');
        if (empty($id) || empty($comment)) {
            return $this->output($this->translate('An error has occurred'),
                self::STATUS_ERROR);
        }

        $table = $this->getTable('Resource');
        $resource = $table->findResource($id,
            $this->params()
                ->fromPost('source', 'VuFind'));
        $id = $resource->addComment($comment, $user);

        // obalky
        $bookid = $this->params()->fromPost('obalkyknihbookid');
        // //////////////////////////////////////////
        $cacheUrl = !isset($this->getConfig()->ObalkyKnih->cacheUrl)
            ? 'https://cache.obalkyknih.cz' : $this->getConfig()->ObalkyKnih->cacheUrl;
        $addReviewUrl = $cacheUrl . "/?add_review=true";
        $client = new \Zend\Http\Client($addReviewUrl);
        $client->setMethod('POST');
        $client->setParameterGet(
            array(
                'book_id' => $bookid,
                'id' => $id  //required parameter, later can be used for editing and deleting
            ));
        $client->setParameterPost(
            array(
                'review_text' => $comment
            ));
        $response = $client->send();
        $responseBody = $response->getBody();
        if ($responseBody == "ok") {
            return $this->output($id, self::STATUS_OK);
        }

        return $this->output($responseBody, self::STATUS_ERROR);
    }

    /**
     * Gets Buy Links
     *
     * @return array
     * @author Martin Kravec <Martin.Kravec@mzk.cz>
     *
     */
    protected function getBuyLinksAjax()
    {
        // Antikvariaty
        $parentRecordID = $this->params()->fromQuery('parentRecordID');
        $recordID = $this->params()->fromQuery('recordID');

        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');

        $parentRecordDriver = $recordLoader->load($parentRecordID);
        $recordDriver = $recordLoader->load($recordID);

        $antikvariatyLink = $parentRecordDriver->getAntikvariatyLink();

        // GoogleBooks & Zbozi.cz
        $wantItFactory = $this->getServiceLocator()->get('WantIt\Factory');
        $buyChoiceHandler = $wantItFactory->createBuyChoiceHandlerObject(
            $recordDriver);

        $gBooksLink = $buyChoiceHandler->getGoogleBooksVolumeLink();
        $zboziLink = $buyChoiceHandler->getZboziLink();

        $buyChoiceLinksCount = 0;

        if ($gBooksLink) {
            ++$buyChoiceLinksCount;
        }

        if ($zboziLink) {
            ++$buyChoiceLinksCount;
        }

        if ($antikvariatyLink) {
            ++$buyChoiceLinksCount;
        }

        $vars[] = array(
            'gBooksLink' => $gBooksLink ?: '',
            'zboziLink' => $zboziLink ?: '',
            'antikvariatyLink' => $antikvariatyLink ?: '',
            'buyLinksCount' => $buyChoiceLinksCount
        );

        // Done
        return $this->output($vars, self::STATUS_OK);
    }

    /**
     * Get a record driver object corresponding to an array returned by an ILS
     * driver's getMyHolds / getMyTransactions method.
     *
     * @param array $current
     *            Record information
     *
     * @return \VuFind\RecordDriver\AbstractBase
     */
    protected function getDriverForILSRecord($current)
    {
        $id = isset($current['id']) ? $current['id'] : null;
        $source = isset($current['source']) ? $current['source'] : 'VuFind';
        $record = $this->getServiceLocator()
            ->get('VuFind\RecordLoader')
            ->load($id, $source, true);
        $record->setExtraDetail('ils_details', $current);
        return $record;
    }

    protected function getDriverForILSRecordZiskej(Ticket $ticket)
    {
        $id = $ticket->getDocumentId();
        $source = 'VuFind';
        $record = $this->getServiceLocator()
            ->get('VuFind\RecordLoader')
            ->load($id, $source, true);
        $record->setExtraDetail('ils_details', $ticket);
        return $record;
    }

    /**
     * Get list of comments for a record as HTML.
     *
     * @return \Zend\Http\Response
     */
    protected function getRecordCommentsObalkyKnihAsHTMLAjax()
    {
        $driver = $this->getRecordLoader()->load(
            $this->params()
                ->fromQuery('id'),
            $this->params()
                ->fromQuery('source', 'VuFind'));
        $html = $this->getViewRenderer()->render(
            'record/comments-list-obalkyknih.phtml',
            [
                'driver' => $driver
            ]);
        return $this->output($html, self::STATUS_OK);
    }

    protected function getTranslatedUnknownStatuses($ids, $viewRend)
    {
        $statuses = [];
        foreach ($ids as $id) {
            $statuses[$id] = [
                'status' => $this->getTranslatedUnknownStatus($viewRend),
                'label' => 'label-unknown'
            ];
        }
        return $statuses;
    }

    protected function getTranslatedUnknownStatus($viewRend)
    {
        $initialString = 'Unknown Status';
        return $viewRend->transEsc('status_' . $initialString, null, $initialString);
    }

    /**
     * Checks whether User identified by his cookies ownes the identity
     * he is asking informations about.
     *
     * Returns true, if the User has all the rights to do so.
     *
     * If the User is not authorized, an \Zend\Http\Response with JSON message
     * is returned.
     *
     * @param string $cat_username
     * @return \Zend\Http\Response|\CPK\Db\Row\User
     */
    protected function hasPermissions($cat_username)
    {

        // Check user is logged in ..
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            return $this->output('You are not logged in.', self::STATUS_ERROR);
        }

        if ($cat_username === null) {
            return $this->output('No cat_username provided.', self::STATUS_ERROR);
        }

        // Check user ownes the identity he is requesting for ..
        $identities = $user->getLibraryCards();

        $isOwner = false;
        foreach ($identities as $identity) {
            if ($identity->cat_username === $cat_username) {
                $isOwner = true;
                break;
            }
        }

        if (!$isOwner && $cat_username != 'ziskejAjaxLoad') {
            // TODO: Implement incident reporting.
            return $this->output(
                'You are not authorized to query data about this identity. This incident will be reported.',
                self::STATUS_ERROR);
        }

        return $user;
    }

    /**
     * Returns the Notifications Handler.
     *
     * If not found, returns directly the output of any Ajax method with instance
     * of the \Zend\Http\Response
     *
     * @return \CPK\Notifications\NotificationsHandler|\Zend\Http\Response
     */
    protected function getNotificationsHandler()
    {
        $notifHandler = $this->getServiceLocator()->get('CPK\NotificationsHandler');

        // Check we have correct notifications handler
        if (!$notifHandler instanceof \CPK\Notifications\NotificationsHandler) {

            return $this->output([
                'errors' => [
                    'Did not found expected Notifications handler'
                ],
                'notifications' => []
            ], self::STATUS_ERROR);
        }

        return $notifHandler;
    }

    /**
     * Send output data and exit.
     *
     * @param mixed $data The response data
     * @param string $status Status of the request
     * @param int $httpCode A custom HTTP Status Code
     *
     * @return \Zend\Http\Response
     * @throws \Exception
     */
    protected function output($data, $status, $httpCode = null)
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Cache-Control', 'no-cache, must-revalidate');
        $headers->addHeaderLine('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
        $headers->addHeaderLine('Access-Control-Allow-Origin', '*');
        if ($httpCode !== null) {
            $response->setStatusCode($httpCode);
        }
        if ($this->outputMode == 'json') {
            $headers->addHeaderLine('Content-type', 'application/javascript');
            $output = ['data' => $data, 'status' => $status];
            if ('development' == APPLICATION_ENV && count(self::$php_errors) > 0) {
                $output['php_errors'] = self::$php_errors;
            }
            $response->setContent(json_encode($output));
            return $response;
        } elseif($this->outputMode == 'plaintext') {
                $headers->addHeaderLine('Content-type', 'text/plain');
                $response->setContent($data ? $status . " $data" : $status);
                return $response;
        } else {
            throw new \Exception('Unsupported output mode: ' . $this->outputMode);
        }
    }

    /**
     * This function should be provided with cat_username as many of the AJAX implementations
     * counts on recieving it in order to properly append it to proprietary element.
     *
     * @param \Exception $e
     * @param string $cat_username
     * @return \Zend\Http\Response
     */
    protected function outputException(\Exception $e, $cat_username = null)
    {

        // Something went wrong - include cat_username to properly
        // attach the error message into the right table

        $message = $this->translate('An error has occurred') . ': ' . $this->translate($e->getMessage());

        if ($cat_username == null) {
            $cat_username = 'unknown';
            $source = $cat_username;
        } else {

            $cat_username = str_replace(':', '\:', $cat_username);

            $splittedCatUsername = explode('.', $cat_username);

            $source = $splittedCatUsername[0];

            $cat_username = join('\.', $splittedCatUsername);
        }

        $data = [
            'message' => $message,
            'cat_username' => $cat_username,
            'source' => $source
        ];

        if ($e instanceof VuFind\Exception\ILS) {
            $data['consideration'] = 'There is a chance you have missing configuration file called "' . §source . '.ini"';
        }

        \Vufind\Sentry\Sentry::handleErrorException($e);

        return $this->output($data, self::STATUS_ERROR);
    }

    /**
     * This function should be provided with cat_username as many of the AJAX implementations
     * counts on recieving it in order to properly append it to proprietary element.
     *
     * @param \Exception $e
     * @param string $cat_username
     * @return \Zend\Http\Response
     */
    protected function outputExceptionWithoutPrefix(\Exception $e, $cat_username = null)
    {

        // Something went wrong - include cat_username to properly
        // attach the error message into the right table

        $message = $this->translate($e->getMessage());

        if ($cat_username == null) {
            $cat_username = 'unknown';
            $source = $cat_username;
        } else {

            $cat_username = str_replace(':', '\:', $cat_username);

            $splittedCatUsername = explode('.', $cat_username);

            $source = $splittedCatUsername[0];

            $cat_username = join('\.', $splittedCatUsername);
        }

        $data = [
            'message' => $message,
            'cat_username' => $cat_username,
            'source' => $source
        ];

        if ($e instanceof VuFind\Exception\ILS) {
            $data['consideration'] = 'There is a chance you have missing configuration file called "' . §source . '.ini"';
        }

        return $this->output($data, self::STATUS_ERROR);
    }

    /**
     * Filter dates in future
     */
    protected function processFacetValues($fields, $results)
    {
        $facets = $results->getFullFieldFacets(array_keys($fields));
        $retVal = [];
        $currentYear = date("Y");
        foreach ($facets as $field => $values) {
            $newValues = [
                'data' => []
            ];
            foreach ($values['data']['list'] as $current) {
                // Only retain numeric values!
                if (preg_match("/^[0-9]+$/", $current['value'])) {
                    if ($current['value'] < $currentYear) {
                        $data[$current['value']] = $current['count'];
                    }
                }
            }
            ksort($data);
            $newValues = array(
                'data' => array()
            );
            foreach ($data as $key => $value) {
                $newValues['data'][] = array(
                    $key,
                    $value
                );
            }
            $retVal[$field] = $newValues;
        }

        return $retVal;
    }

    /**
     * Get Autocomplete suggestions.
     *
     * @return \Zend\Http\Response
     */
    protected function getACSuggestionsAjax()
    {
        $this->writeSession();  // avoid session write timing bug
        $query = $this->getRequest()->getQuery();
        $autocompleteManager = $this->getServiceLocator()
            ->get('CPK\AutocompletePluginManager');
        $facetFilters = $this->params()->fromQuery('filters');
        return $this->output(
            $autocompleteManager->getSuggestions(
                $query, 'type', 'q', $facetFilters
            ),
            self::STATUS_OK
        );
    }

    /**
     * Get citation
     *
     * @return string
     */
    public function getCitationAjax()
    {
        $recordId = $this->params()->fromPost('recordId');
        $changedCitationValue = $this->params()->fromPost('citationValue');

        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        $recordDriver = $recordLoader->load($recordId);

        $parentRecordId = $recordDriver->getParentRecordId();
        $parentRecordDriver = $recordLoader->load($parentRecordId);

        $format = $parentRecordDriver->getRecordType();
        if ($format === 'marc') {
            $format .= '21';
        }
        $recordXml = $parentRecordDriver->getXml($format);

        if (strpos($recordXml, "datafield") === false) {
            return $this->output($statusCode, self::STATUS_ERROR);
        }

        // Set preferred citation style
        if ($changedCitationValue == 'false') {
            $user = $this->getAuthManager()->isLoggedIn();
            if (!$user) {
                $preferredCitationStyle = $this->getConfig()
                    ->Record->default_citation_style;
            } else {
                $userSettingsTable = $this->getTable("usersettings");
                $citationStyleTable = $this->getTable("citationstyle");
                $preferredCitationStyleId = $userSettingsTable
                    ->getUserCitationStyle($user);
                $preferredCitationStyle = $citationStyleTable
                    ->getCitationValueById($preferredCitationStyleId);
            }
        } else {
            $preferredCitationStyle = $changedCitationValue;
        }

        if ($preferredCitationStyle == null) {
            $preferredCitationStyle = $this->getConfig()
                ->Record->default_citation_style;
        }

        $citationLocalDomain = $this->getConfig()->Site->url;
        $citationLocalDomain = parse_url($citationLocalDomain, PHP_URL_HOST);
        $citationLocalDomain = str_replace("www.", "", $citationLocalDomain);

        $citationServerUrl = "https://www.citacepro.com/api/cpk/citace/"
            . $recordId
            . "?server=" . $citationLocalDomain
            . "&citacniStyl=" . $preferredCitationStyle;

        try {
            $ch = curl_init($citationServerUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            $html = curl_exec($ch);
            curl_close($ch);

            $doc = new \DOMDocument();
            $doc->loadHTML($html);

            $xpath = new \DOMXPath($doc);

            $results = $xpath->query('//*[@id="citace"]');

            $citation = $results->item(0)->c14n();
            if (!empty($citation)) {
                return $this->output($citation, self::STATUS_OK);
            }

        } catch (\Exception $e) {
            return $this->output('false', self::STATUS_ERROR);
        }

        return $this->output('false', self::STATUS_ERROR);
    }

    /**
     * Set preferred citation style into user_settings table
     *
     * @return \Zend\Http\Response
     */
    public function setCitationStyleAjax()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        $citationStyleValue = $this->params()->fromPost('citationStyleValue');

        try {
            $userSettingsTable = $this->getTable("usersettings");
            $userSettingsTable->setCitationStyle($user, $citationStyleValue);
        } catch (\Exception $e) {
            return $this->outputException($e);
        }

        return $this->output([], self::STATUS_OK);
    }

    /**
     * Set preferred amount of records per page user_settings table
     *
     * @return \Zend\Http\Response
     */
    public function setRecordsPerPageAjax()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        $recordsPerPage = $this->params()->fromPost('recordsPerPage');

        try {
            $userSettingsTable = $this->getTable("usersettings");
            $userSettingsTable->setRecordsPerPage($user, $recordsPerPage);
            // @FIXME Make following line object oriented
            $_SESSION['VuFind\Search\Solr\Options']['lastLimit'] = $recordsPerPage;
        } catch (\Exception $e) {
            return $this->outputException($e);
        }

        return $this->output([], self::STATUS_OK);
    }

    /**
     * Set preferred sorting for user to user_settings table
     *
     * @return \Zend\Http\Response
     */
    public function setPreferredSortingAjax()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        $preferredSorting = $this->params()->fromPost('preferredSorting');

        try {
            $userSettingsTable = $this->getTable("usersettings");
            $userSettingsTable->setPreferredSorting($user, $preferredSorting);
            // @FIXME Make following line object oriented
            $_SESSION['VuFind\Search\Solr\Options']['lastSort'] = $preferredSorting;
        } catch (\Exception $e) {
            return $this->outputException($e);
        }

        return $this->output([], self::STATUS_OK);
    }

    /**
     * Return search results
     *
     * @return \Zend\Http\Response
     */
    public function updateSearchResultsAjax()
    {
        $postParams = $this->params()->fromPost();
        $searchController = $this->getServiceLocator()->get('searchController');
        $viewData = $searchController->ajaxResultsAction($postParams);

        return $this->output($viewData, self::STATUS_OK);
    }

    public function updateExtraSearchResultsAjax()
    {
        $postParams = $this->params()->fromPost();
        $searchController = $this->getServiceLocator()->get('searchController');
        $viewData = $searchController->ajaxExtraResultsAction($postParams);

        return $this->output($viewData, self::STATUS_OK);
    }

    /**
     * Save chosen institutions to DB
     *
     * @return \Zend\Http\Response
     */
    public function saveTheseInstitutionsAjax()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        $institutions = $this->params()->fromPost('institutions');

        if ($user) {
            try {
                $userSettingsTable = $this->getTable("usersettings");
                $userSettingsTable->saveTheseInstitutions($user, $institutions);
            } catch (\Exception $e) {
                return $this->outputException($e);
            }

        } else {
            return $this->output([message => "You can't save these institutions when you are not logged in."], self::STATUS_NEED_AUTH);
        }

        return $this->output([], self::STATUS_OK);
    }

    /**
     * Get saved institutions
     *
     * @return \Zend\Http\Response
     */
    public function getSavedInstitutionsAjax()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!$user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        try {
            $userSettingsTable = $this->getTable("usersettings");
            $savedInstitutions = $userSettingsTable->getSavedInstitutions($user);
        } catch (\Exception $e) {
            return $this->outputException($e);
        }

        return $this->output(['savedInstitutions' => $savedInstitutions], self::STATUS_OK);
    }

    /**
     *
     * Get advanced handlers
     *
     * @return \Zend\Http\Response
     * @deprecated
     *
     */
    public function getAllAdvancedHandlersAjax()
    {
        $renderer = $this->getViewRenderer();

        $solrOptions = $renderer->searchOptions('Solr');
        $edsOptions = $renderer->searchOptions('EDS');

        $solrAdvancedHandlers = $solrOptions->getAdvancedHandlers();
        $edsAdvancedHandlers = $edsOptions->getAdvancedHandlers();

        foreach ($solrAdvancedHandlers as $key => $value) {
            $solrAdvancedHandlers[$key] = $renderer->translate($value);
        }

        foreach ($edsAdvancedHandlers as $key => $value) {
            $edsAdvancedHandlers[$key] = $renderer->translate($value);
        }

        $advancedHandlers['Solr'] = $solrAdvancedHandlers;
        $advancedHandlers['EDS'] = $edsAdvancedHandlers;

        return $this->output($advancedHandlers, self::STATUS_OK);
    }

    /**
     * Harvest most wanted records and favorite authors from MySql to Solr.
     *
     * @return \Zend\Http\Response
     */
    public function harvestWidgetsContentsAjax()
    {
        $widgetContentTable = $this->getTable('widgetcontent');

        $widgetTable = $this->getTable('widget');
        $widgets = $widgetTable->getWidgets();

        $data = "";
        $i = 1;
        foreach ($widgets as $widget) {
            if ($i != 1) {
                $data .= "<br>\n";
            }
            $widgetName = $widget->getName();
            $data .= "[$widgetName]" . "<br>\n";

            $contents = $widgetContentTable->getContentsByName($widgetName);
            foreach ($contents as $content) {
                $data .= $content->getValue() . "<br>\n";
            }
            $i++;
        }

        echo $data;
        exit();
    }

    /**
     * Creates MySQL DB table libraries_geolocations
     *
     *
     * @return \Zend\Http\Response
     */
    public function createLibrariesGeolocationsTableAjax()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        $adresarKnihovenApiUrl = $config->AdresarKnihoven->apiUrl;

        try {
            $librariesGeolocationTable = $this->getTable("libraries_geolocations");

            $infoApiUrl = $this->adresarKnihovenApiUrl . '/libraries?limit=999999';

            $libraries = $this->remoteJsonToArray($infoApiUrl);

            $data = [];
            foreach ($libraries as $library) {
                $data[] = [
                    'sigla' => $library['sigla'],
                    'latitude' => $library['latitude'],
                    'longitude' => $library['longitude'],
                    'town' => $library['city'],
                    'district' => $library['district'],
                    'region' => $library['region'],
                    'zip' => $library['zip'],
                    'street' => $library['street'],
                ];
            }

            $librariesGeolocationTable->saveGeoData($data);

        } catch (\Exception $e) {
            return $this->output($e->getMessage(), self::STATUS_ERROR);
        }

        return $this->output('', self::STATUS_OK);
    }

    /**
     * Returns content from url coverted from JSON to array
     *
     * CURLOPT_HEADER - Include header in result? (0 = yes, 1 = no)
     * CURLOPT_RETURNTRANSFER - (true = return, false = print) data
     *
     * @param string $infoApiUrl
     *
     * @return    mixed
     * @throws    \Exception when Json cannot be decoded
     *            or the encoded data is deeper than the recursion limit.
     * @throws    \Exception when response body contains error element
     * @throws    \Exception when reponse status code is not 200
     * @throws    \Exception when cURL us not installed
     */
    private function remoteJsonToArray($infoApiUrl)
    {
        if (!function_exists('curl_init')) {
            throw new \Exception('cURL is not installed!');
        }

        $curlAdapterConfig = array(
            'adapter' => '\Zend\Http\Client\Adapter\Curl',
            'curloptions' => array(
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => "Mozilla/5.0",
                CURLOPT_HEADER => 0,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
            ),
        );

        $client = new \Zend\Http\Client($infoApiUrl, $curlAdapterConfig);
        $response = $client->send();

        // Response head error handling
        $responseStatusCode = $response->getStatusCode();
        if ($responseStatusCode !== 200) {
            throw new \Exception("Response status code: " . $responseStatusCode);
        }
        //

        $output = $response->getBody();

        // Response body error handling
        $dataArray = \Zend\Json\Json::decode($output, \Zend\Json\Json::TYPE_ARRAY);

        if ($dataArray === null) {
            throw new \Exception('Json cannot be decoded or the encoded data is deeper than the recursion limit.');
        }

        if ((isset($dataArray['result']) && ($dataArray['result'] == 'error'))) {
            throw new \Exception($dataArray['message']);
        }
        //

        return $dataArray;
    }

    /**
     * Get towns by region
     *
     * @return string
     */
    public function getTownsByRegionAjax()
    {
        try {
            $coords = $this->params()->fromPost();
            $latitude = $coords['latitude'];
            $longitude = $coords['longitude'];

            $apikey = $this->getConfig()->GoogleMaps->apikey;
            $googleMapsUri = "https://maps.googleapis.com/maps/api/geocode/json" .
                "?latlng=$latitude,$longitude" .
                "&sensor=false" .
                "&language=cs&key=$apikey";
            $geocode = file_get_contents($googleMapsUri);

            $geoData = json_decode($geocode, true);

            $region = null;

            foreach ($geoData['results'] as $index => $data) {
                foreach ($data['address_components'] as $key => $array) {
                    foreach ($array['types'] as $type) {
                        if ($type == 'administrative_area_level_1') {
                            $region = $data['address_components'][$key]['long_name'];
                        }
                    }
                }
            }

            if (is_null($region)) {
                throw new \Exception('Region not found.');
            }

            $librariesGeolocationsTable = $this->getTable("librariesgeolocations");
            $towns = $librariesGeolocationsTable->getTownsByRegion($region);

        } catch (\Exception $e) {
            return $this->output($e->getMessage(), self::STATUS_ERROR);
        }

        $output = ['region' => $region, 'towns' => $towns];

        return $this->output($output, self::STATUS_OK);
    }

    /**
     * Return conspectus subcategories
     *
     * @return \Zend\Http\Response
     */
    public function getConspectusSubCategoriesAjax()
    {
        $postParams = $this->params()->fromPost();
        $searchController = $this->getServiceLocator()->get('searchController');
        $viewData = $searchController->getConspectusSubCategoriesAction($postParams);

        return $this->output($viewData, self::STATUS_OK);
    }

    /**
     * Return siblings of record
     *
     * @param string $recordId
     *
     * @return array
     */
    public function getRecordSiblings($recordId)
    {
        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        $recordDriver = $recordLoader->load($recordId);

        $parentRecordID = $recordDriver->getParentRecordID();
        try {
            $parentRecordDriver = $recordLoader->load($parentRecordID);
        } catch (\Exception $e) {
            //ignore exception when there is no record and continue rendering
        }

        $childrenIds = $parentRecordDriver ? $parentRecordDriver->getChildrenIds() : [];

        return $childrenIds;
    }

    public function getObalkyKnihAuthorityIDAjax()
    {
        $id = $this->params()->fromQuery('id');
        $obalky = $this->getAuthorityFromObalkyKnih($id);
        $coverUrl = empty($obalky[0]['cover_medium_url']) ? '' : $obalky[0]['cover_medium_url'];
        $coverUrl = str_replace('http://', 'https://', $coverUrl);
        return $this->output($coverUrl, self::STATUS_OK);

    }

    private function getAuthorityFromObalkyKnih($id)
    {
        if (!isset($this->obalky)) {

            $auth_id = $id;

            if (!empty($auth_id)) {
                try {
                    $cacheUrl = !isset($this->getConfig()->ObalkyKnih->cacheUrl)
                        ? 'https://cache.obalkyknih.cz' : $this->getConfig()->ObalkyKnih->cacheUrl;
                    $metaUrl = $cacheUrl . "/api/auth/meta";
                    $client = new \Zend\Http\Client($metaUrl);
                    $client->setParameterGet(array(
                        'auth_id' => $auth_id
                    ));

                    $response = $client->send();
                    $responseBody = $response->getBody();
                    $phpResponse = json_decode($responseBody, true);
                    $this->obalky = empty($phpResponse) ? null : $phpResponse;
                } catch (TimeoutException $e) {
                    $this->obalky = null;
                }
            } else {
                $this->obalky = null;
            }
        }
        return $this->obalky;
    }


    /**
     * Get an array of summary strings for the record.
     *
     * @return string
     */
    private function getSummaryObalkyKnih($isbnArray)
    {
        $isbnJson = json_encode($isbnArray);

        $cacheUrl = !isset($this->getConfig()->ObalkyKnih->cacheUrl)
            ? 'https://cache.obalkyknih.cz' : $this->getConfig()->ObalkyKnih->cacheUrl;
        $apiBooksUrl = $cacheUrl . "/api/books";
        $client = new \Zend\Http\Client($apiBooksUrl);
        $client->setParameterGet(array(
            'multi' => '[' . $isbnJson . ']'
        ));

        try {
            $response = $client->send();
        } catch (\Exception $ex) {
            return null; // TODO what to do when server is not responding
        }


        $responseBody = $response->getBody();

        $phpResponse = json_decode($responseBody, true);

        if (isset($phpResponse[0]['annotation'])) {

            if ($phpResponse[0]['annotation']['html'] == null) {
                return null;
            }

            $anothtml = $phpResponse[0]['annotation']['html'];
            //obalky knih sends annotation html escaped, we have convert it to string, to be able to escape it
            $anot = htmlspecialchars_decode($anothtml);

            $source = $phpResponse[0]['annotation']['source'];

            return $anot . " - " . $source;
        }
        return null;
    }


    public function getSummaryObalkyKnihAjax()
    {
        $bibinfo = $this->params()->fromQuery('bibinfo');

        $annotation = $this->getSummaryObalkyKnih($bibinfo);

        $html = $this->getViewRenderer()->render(
            'RecordDriver/SolrDefault/summary-full.phtml',
            [
                'annotation' => $annotation
            ]);
        return $this->output($html, self::STATUS_OK);
    }

    public function getSummaryShortObalkyKnihAjax()
    {
        $bibinfo = $this->params()->fromQuery('bibinfo');

        $annotation = $this->getSummaryObalkyKnih($bibinfo);

        $html = $this->getViewRenderer()->render(
            'RecordDriver/SolrDefault/summary-short.phtml',
            [
                'annotation' => $annotation
            ]);
        return $this->output($html, self::STATUS_OK);
    }


    /**
     * Save search
     *
     * @return \Zend\Http\Response
     */
    public function saveSearchAjax()
    {
        $postParams = $this->params()->fromPost();
        $searchId = $postParams['searchId'];

        // Fail if saved searches are disabled.
        $check = $this->getServiceLocator()->get('VuFind\AccountCapabilities');
        if ($check->getSavedSearchSetting() === 'disabled') {
            return $this->output(['Saved searches disabled.'], self::STATUS_ERROR);
        }

        try {
            $user = $this->getUser();
            if ($user == false) {
                //return $this->forceLogin();
                return $this->forwardTo('MyResearch', 'Login');
            }

            $search = $this->getTable('Search');
            if (($id = $this->params()->fromPost('searchId', false)) !== false) {
                $search->setSavedFlag($id, true, $user->id);

                $searchController = $this->getServiceLocator()->get('searchController');
                $searchTerms = $searchController->getSearchTermsFromSearch($searchId);

            } else {
                return $this->output(['Missing searchId for save search action.'], self::STATUS_ERROR);
            }
        } catch (\Exception $e) {
            return $this->output([$e->getMessage()], self::STATUS_ERROR);
        }
        return $this->output(['searchTerms' => $searchTerms], self::STATUS_OK);
    }

    /**
     * Remove search
     *
     * @return \Zend\Http\Response
     */
    public function removeSearchAjax()
    {
        $postParams = $this->params()->fromPost();
        $searchId = $postParams['searchId'];

        // Fail if saved searches are disabled.
        $check = $this->getServiceLocator()->get('VuFind\AccountCapabilities');
        if ($check->getSavedSearchSetting() === 'disabled') {
            throw new \Exception('Saved searches disabled.');
        }

        $user = $this->getUser();
        if ($user == false) {
            //return $this->forceLogin();
            return $this->forwardTo('MyResearch', 'Login');
        }

        $search = $this->getTable('Search');
        if (($id = $this->params()->fromPost('searchId', false)) !== false) {
            $search->setSavedFlag($id, false);
        } else {
            throw new \Exception('Missing searchId for remove search action.');
        }

        return $this->output([], self::STATUS_OK);
    }

    /**
     * Get hierarchical facet data for jsTree
     *
     * Parameters:
     * facetName  The facet to retrieve
     * facetSort  By default all facets are sorted by count. Two values are available
     * for alternative sorting:
     *   top = sort the top level alphabetically, rest by count
     *   all = sort all levels alphabetically
     *
     * @return \Zend\Http\Response
     */
    protected function getFacetDataAjax()
    {
        $this->writeSession();  // avoid session write timing bug

        $facet = $this->params()->fromQuery('facetName');
        $sort = $this->params()->fromQuery('facetSort');
        $operator = $this->params()->fromQuery('facetOperator');

        $results = $this->getResultsManager()->get('Solr');
        $params = $results->getParams();
        $params->addFacet($facet, null, $operator === 'OR');
        $requestQuery = $this->getRequest()->getQuery();
        $requestQuery['filter'] = explode("|", \LZCompressor\LZString::decompressFromBase64(specialUrlDecode($requestQuery['filter'])));
        if (empty($requestQuery['filter'][0])) {
            unset($requestQuery['filter']);
        }
        $params->initFromRequest($requestQuery);

        $facets = $results->getFullFieldFacets([$facet], false, -1, 'count');
        if (empty($facets[$facet]['data']['list'])) {
            return $this->output([], self::STATUS_OK);
        }

        $facetList = $facets[$facet]['data']['list'];

        $facetHelper = $this->getServiceLocator()
            ->get('VuFind\HierarchicalFacetHelper');
        if (!empty($sort)) {
            $facetHelper->sortFacetList($facetList, $sort == 'top');
        }

        return $this->output(
            $facetHelper->buildFacetArray(
                $facet, $facetList, $results->getUrlQuery()
            ),
            self::STATUS_OK
        );
    }

    /**
     * Get Item Statuses
     *
     * This is responsible for printing the holdings information for a
     * collection of records in JSON format.
     *
     * @return \Zend\Http\Response
     * @author Chris Delis <cedelis@uillinois.edu>
     * @author Tuan Nguyen <tuan@yorku.ca>
     */
    protected function getItemStatusesAjax()
    {
        $this->writeSession();  // avoid session write timing bug
        $catalog = $this->getILS();
        $ids = $this->params()->fromQuery('id');
        $results = $catalog->getStatuses($ids);

        if (!is_array($results)) {
            $results = array();
        }

        // In order to detect IDs missing from the status response, create an
        // array with a key for every requested ID.  We will clear keys as we
        // encounter IDs in the response -- anything left will be problems that
        // need special handling.
        $missingIds = array_flip($ids);

        // Get access to PHP template renderer for partials:
        $renderer = $this->getViewRenderer();

        // Load messages for response:
        $messages = array(
            'available' => $renderer->render('ajax/status-available.phtml'),
            'unavailable' => $renderer->render('ajax/status-unavailable.phtml'),
            'no-items' => $renderer->render('ajax/status-no-items.phtml'),
        );

        // Loop through all the status information that came back
        $statuses = array();
        foreach ($results as $recordNumber => $record) {
            if (!count($record)) {
                continue;
            }
            $current = $record[0];
            $current['record_number'] = array_search($current['id'], $ids);
            $current['full_status'] = $renderer->render('ajax/status-full.phtml',
                array('status' => $current));
            $message = $messages['unavailable'];
            if (isset($current['absent_total']) && isset($current['present_total']) &&
                $current['absent_total'] + $current['present_total'] == 0) {
                $message = $messages['no-items'];
            } else {
                if ($current['availability']) {
                    $message = $messages['available'];
                }
            }
            $current['availability_message'] = $message;
            $statuses[] = $current;
            // The current ID is not missing -- remove it from the missing list.
            unset($missingIds[$current['id']]);
        }

        // If any IDs were missing, send back appropriate dummy data
        foreach ($missingIds as $missingId => $recordNumber) {
            $statuses[] = array(
                'id' => $missingId,
                'availability' => 'false',
                'availability_message' => $messages['unavailable'],
                'location' => $this->translate('Unknown'),
                'locationList' => false,
                'reserve' => 'false',
                'reserve_message' => $this->translate('Not On Reserve'),
                'callnumber' => '',
                'missing_data' => true,
                'record_number' => $recordNumber
            );
        }

        // Done
        return $this->output($statuses, self::STATUS_OK);
    }

    public function getEdsFulltextLinkAjax()
    {
        $solrUrl = $this->getConfig()->Index->url;
        $solrCore = $this->getConfig()->Index->default_core;

        $htmlLinks = [];
        $not_ok_messages = [];

        // get and prepare data
        $recordData = $this->params()->fromPost('recordData');

        $issns = isset($recordData['issns']) ? explode(", ", $recordData['issns']) : [];
        $electronicIssns = isset($recordData['electronicIssns']) ? explode(", ", $recordData['electronicIssns']) : [];
        $issns = array_merge($issns, $electronicIssns);
        $issns = (!empty($issns)) ? $issns : false;

        $isbns = isset($recordData['isbns']) ? explode(", ", $recordData['isbns']) : false;
        $publishDate = isset($recordData['publishDate']) ? $recordData['publishDate'] : false;
        $authors = isset($recordData['authors']) ? explode(", ", $recordData['authors']) : false;
        $sourceTitle = isset($recordData['sourceTitle']) ? $recordData['sourceTitle'] : false;

        // build query
        $url = "$solrUrl/$solrCore/select?";
        $url .= "q=recordtype:sfx";

        if ($issns || $isbns) {

            if ($issns) {
                $url .= "%0A";
                $url .= 'issn:("' . implode('"+OR+"', $issns) . '")';
            }

            if ($isbns) {
                $url .= "%0A";
                $url .= 'isbn:("' . implode('"+OR+"', $isbns) . '")';
            }

            if ($publishDate) {
                $url .= "%0A";
                $url .= 'publishDate_txt_mv:("' . $publishDate . '")';
            }

        } else {
            if ($sourceTitle && $authors) {
                $url .= "%0A";
                $url .= 'sfx_title_txt_mv:("' . $sourceTitle . '")';
                $url .= "%0A";
                $url .= 'author_txt_mv:("' . implode('"+OR+"', $authors) . '")';
            } else {
                return $this->output(['url' => $url, 'message' => 'NO USABLE METADATA FOUND', 'not_ok_messages' => $not_ok_messages], self::STATUS_NOT_OK);
            }
        }

        $url .= "&fl=sfx_source_txt,sfx_id_txt,sfx_url_txt,embargo_str";
        $url .= "&wt=json";
        $url .= "&indent=true";
        $url .= '&rows=20';

        // run Solr query
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            $html = curl_exec($ch);
            curl_close($ch);

        } catch (\Exception $e) {
            return $this->output(['url' => $url, 'not_ok_messages' => $not_ok_messages], self::STATUS_ERROR);
        }

        // get response
        $json = json_decode($html, true);

        if (empty($json['response']['docs'])) {
            return $this->output(['url' => $url, 'message' => 'NOT FOUND', 'not_ok_messages' => $not_ok_messages], self::STATUS_NOT_OK);
        }

        foreach ($json['response']['docs'] as $record) {
            if ((!empty($record['sfx_source_txt'])) && (!empty($record['sfx_id_txt']))) {

                $sfxSource = $record['sfx_source_txt'];
                $embargo = (!empty($record['embargo_str']) ? ' ' . htmlspecialchars($record['embargo_str']) : '');

                if (!empty($record['sfx_url_txt'])) {

                    if (filter_var($record['sfx_url_txt'], FILTER_VALIDATE_URL)) {
                        if (!isset($htmlLinks[$sfxSource])) {
                            $htmlLinks[$sfxSource] = [];
                        }

                        if ($sfxSource == 'free') {
                            $anchor = $this->translate('Free fulltext');
                        } else {
                            $anchor = $this->translate(strtoupper($sfxSource));
                        }

                        $link = "<a href='" . $record['sfx_url_txt'] . "' target='_blank' title='" . $this->translate('Fulltext') . "'>" . $anchor . "</a>";
                        if ($embargo) {
                            $link .= " (" . $this->translate(explode(' ', trim($embargo))[0]) . " " . explode(' ', trim($embargo))[1] . ")";
                        }

                        $htmlLinks[$sfxSource][] = $link;
                    } else {
                        $not_ok_messages[] = 'Record with sfx_id_txt: ' . $record['sfx_id_txt'] . ' contains link, but the link is INVALID.';
                    }

                }

            }
        }

        if (empty($htmlLinks)) {
            return $this->output(['url' => $url, 'message' => 'Eds fulltext links FOUND but links are INVALID', 'not_ok_messages' => $not_ok_messages], self::STATUS_NOT_OK);
        }

        $htmlLinks = $this->keepOnlyFreeLinkIfAvailable($htmlLinks);
        $htmlLinks = $this->sortLinksByMyLibraries($htmlLinks);

        $output = [
            'links' => $htmlLinks,
            'url' => $url,
            'not_ok_messages' => $not_ok_messages,
        ];

        return $this->output($output, self::STATUS_OK);
    }

    public function getEdsFulltextLinkInResultsAjax()
    {
        $solrUrl = $this->getConfig()->Index->url;
        $solrCore = $this->getConfig()->Index->default_core;

        $htmlLinks = [];
        $not_ok_messages = [];

        // get and prepare data
        $recordData = $this->params()->fromPost('recordData');
        $recordId = isset($recordData['recordId']) ? $recordData['recordId'] : false;

        $recordLoader = $this->getRecordLoader();
        $recordDriver = $recordLoader->load($recordId, 'EDS',
            true);

        if (!$recordDriver) {
            return $this->output(['recordId' => $recordId, 'message' => 'RECORD NOT LOADED', 'not_ok_messages' => $not_ok_messages,], self::STATUS_NOT_OK);
        }

        $containsFulltext = $recordDriver->containsFulltext();

        if ($containsFulltext) {
            $output = [
                'message' => 'Record contains free fulltext',
                'not_ok_messages' => $not_ok_messages,
            ];

            return $this->output($output, self::STATUS_OK);
        }

        $issns = $recordDriver->getIssns() != false ? $recordDriver->getIssns() : [];
        $electronicIssns = $recordDriver->getElectronicIssns() != false ? $recordDriver->getElectronicIssns() : [];
        $issns = array_merge($issns, $electronicIssns);
        $issns = (!empty($issns)) ? $issns : false;

        $isbns = $recordDriver->getIsbns();
        $publishDate = $recordDriver->getPublishDate();
        $authors = $recordDriver->getAuthors();
        $sourceTitle = $recordDriver->getSourceTitle();

        // build query
        $url = "$solrUrl/$solrCore/select?";
        $url .= "q=recordtype:sfx";

        if ($issns || $isbns) {

            if ($issns) {
                $url .= "%0A";
                $url .= 'issn:("' . implode('"+OR+"', $issns) . '")';

                if ($publishDate) {
                    $url .= "%0A";
                    $url .= 'publishDate_txt_mv:("' . $publishDate . '")';
                }
            }

            if ($isbns) {
                $url .= "%0A";
                $url .= 'isbn:("' . implode('"+OR+"', $isbns) . '")';
            }

        } else {
            if ($sourceTitle && $authors) {
                $url .= "%0A";
                $url .= 'sfx_title_txt_mv:("' . $sourceTitle . '")';
                $url .= "%0A";
                $url .= 'author_txt_mv:("' . implode('"+OR+"', $authors) . '")';
            } else {
                return $this->output([
                    'url' => $url,
                    'message' => 'NO USABLE METADATA FOUND',
                    'not_ok_messages' => $not_ok_messages,
                ], self::STATUS_NOT_OK);
            }
        }

        $url .= "&fl=sfx_source_txt,sfx_id_txt,sfx_url_txt,embargo_str";
        $url .= "&wt=json";
        $url .= "&indent=true";
        $url .= '&rows=20';

        // run Solr query
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            $html = curl_exec($ch);
            curl_close($ch);

        } catch (\Exception $e) {
            return $this->output(['url' => $url, 'not_ok_messages' => $not_ok_messages,], self::STATUS_ERROR);
        }

        // get response
        $json = json_decode($html, true);

        if (empty($json['response']['docs'])) {
            return $this->output(['url' => $url, 'message' => 'NOT FOUND', 'not_ok_messages' => $not_ok_messages,], self::STATUS_NOT_OK);
        }

        foreach ($json['response']['docs'] as $record) {

            if ((!empty($record['sfx_source_txt'])) && (!empty($record['sfx_id_txt']))) {
                $sfxSource = $record['sfx_source_txt'];
                $embargo = (!empty($record['embargo_str']) ? ' ' . htmlspecialchars($record['embargo_str']) : '');

                if (!empty($record['sfx_url_txt'])) {

                    if (filter_var($record['sfx_url_txt'], FILTER_VALIDATE_URL)) {
                        if (!isset($htmlLinks[$sfxSource])) {
                            $htmlLinks[$sfxSource] = [];
                        }

                        if ($sfxSource == 'free') {
                            $anchor = $this->translate('Free fulltext');
                        } else {
                            $anchor = $this->translate(strtoupper($sfxSource));
                        }


                        $link = sprintf(
                            '<a href="%s" target="_blank" title="%s">%s</a>',
                            $record['sfx_url_txt'],
                            $this->translate('Fulltext'),
                            $anchor
                        );

                        if ($embargo) {
                            $embargoText = $this->translate(explode(' ', trim($embargo))[0]) .
                                " " . explode(' ', trim($embargo))[1];
                            $link .= sprintf(
                                '<span class="eds-results-embargo" data-toggle="tooltip" title="%s">&nbsp;*</span>',
                                $embargoText
                            );
                        }

                        $htmlLinks[$sfxSource][] = $link;
                    } else {
                        $not_ok_messages[] = 'Record with sfx_id_txt: ' . $record['sfx_id_txt'] . ' contains link, but the link is INVALID.';
                    }

                }

            }

        }

        if (empty($htmlLinks)) {
            return $this->output(['url' => $url, 'message' => 'FOUND INVALID LINKS', 'not_ok_messages' => $not_ok_messages], self::STATUS_NOT_OK);
        }

        $htmlLinks = $this->keepOnlyFreeLinkIfAvailable($htmlLinks);
        $htmlLinks = $this->sortLinksByMyLibraries($htmlLinks);

        $output = [
            'links' => $htmlLinks,
            'url' => $url,
            'message' => '',
            'not_ok_messages' => $not_ok_messages,
        ];

        return $this->output($output, self::STATUS_OK);
    }

    /**
     * User's Library cards (home_library values)
     *
     * @return array
     */
    public function getUsersHomeLibraries()
    {
        $account = $this->getAuthManager();
        if ($account->isLoggedIn()) { // is loggedIn

            $user = $this->getUser();

            if ($user instanceof \CPK\Db\Row\User) {
                return $user->getNonDummyInstitutions();
            }
        } else {
            return [];
        }
    }

    /**
     * Sort links by my libraries
     *
     * @param array Associative array
     *
     * @return  array
     */
    protected function sortLinksByMyLibraries($htmlLinks)
    {
        $myLibs = $this->getUsersHomeLibraries();
        $available = isset($this->getConfig()->Preferred_Institutions->list);
        $preferred = ($available) ? $this->getConfig()->Preferred_Institutions->list->toArray() : [];
        $myLibs = array_merge($myLibs, $preferred);

        if (!empty($myLibs)) {
            $preferredLinks = [];
            $otherLinks = [];
            foreach ($htmlLinks as $source => $links) {
                if (in_array($source, $myLibs)) {
                    $preferredLinks[$source] = $links;
                } else {
                    $otherLinks[$source] = $links;
                }
            }

            $links = [];
            foreach (array_values(array_merge($preferredLinks, $otherLinks)) as $lib) {
                $links = array_merge($links, array_values($lib));
            }

        } else {
            $links = [];
            foreach (array_values($htmlLinks) as $array) {
                $links = array_merge($links, array_values($array));
            }
        }

        return $links;
    }

    /**
     * Return sfx institution shortcut by local library shortcut (source)
     *
     * @param string $libShortcut
     *
     * @return  string
     */
    protected function getSfxInstitutionShortcut($libShortcut)
    {
        $shortcutsMapping = $this->getConfig('MultiBackend')->SfxInstitutionsMapping->toArray();
        return isset($shortcutsMapping[$libShortcut]) ? $shortcutsMapping[$libShortcut] : $libShortcut;
    }

    /**
     * Return array with free link only, if free link is available, otherwise return full array
     *
     * @param array $htmlLinks Associative array
     *
     * @return  array               Associative array
     */
    protected function keepOnlyFreeLinkIfAvailable($htmlLinks)
    {
        $allowed = ['free'];

        $intersection = array_intersect_key($htmlLinks, array_flip($allowed));

        return (!empty($intersection)) ? $intersection : $htmlLinks;
    }

    public function createZiskejMessageAjax()
    {


        $postParams = $this->params()->fromPost();
        $message = $postParams['message'];
        $id = $postParams['ticketId'];

        $request = $this->getRequest();
        $eppn = $request->getServer()->eduPersonPrincipalName;

        $ilsDriver = $this->getILS()->getDriver();
        $patron = [
            'eppn' => $eppn,
            'id' => $id,
            'message' => $message
        ];

        $resp = $ilsDriver->createZiskejMessage($patron);
        return $resp;
    }

}
