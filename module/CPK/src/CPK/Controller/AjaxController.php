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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace CPK\Controller;

use MZKCommon\Controller\AjaxController as AjaxControllerBase, VuFind\Exception\ILS as ILSException;

/**
 * This controller handles global AJAX functionality
 *
 * @category VuFind2
 * @package Controller
 * @author Martin Kravec <Martin.Kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class AjaxController extends AjaxControllerBase
{

    /**
     * Gets Buy Links
     *
     * @author Martin Kravec <Martin.Kravec@mzk.cz>
     *
     * @return array
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
            ++ $buyChoiceLinksCount;
        }

        if ($zboziLink) {
            ++ $buyChoiceLinksCount;
        }

        if ($antikvariatyLink) {
            ++ $buyChoiceLinksCount;
        }

        $vars[] = array(
            'gBooksLink' => $gBooksLink ?  : '',
            'zboziLink' => $zboziLink ?  : '',
            'antikvariatyLink' => $antikvariatyLink ?  : '',
            'buyLinksCount' => $buyChoiceLinksCount
        );

        // Done
        return $this->output($vars, self::STATUS_OK);
    }

    /**
     * Returns subfileds of MARC 996 field for specific recordID
     *
     * @param string $_POST['record']
     * @param string $_POST['field']
     * @param string $_POST['subfields']
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

    /**
     * Downloads SFX JIB content for current record.
     *
     * @param string $_GET['institute']
     *
     * @return array
     */
    public function callLinkServerAjax()
    {
        $sourceInstitute = $this->params()->fromQuery('sourceInstitute');
        if (! $sourceInstitute)
            $sourceInstitute = 'default';
        
        $multiBackendConfig = $this->getConfig('MultiBackend');
        $lsID = 'ls_'.$sourceInstitute;
        $linkServer = $multiBackendConfig->LinkServers->$lsID;
        $instituteLsShortcut = explode("|", $linkServer)[0];
        $instituteLsLink = explode("|", $linkServer)[1];

        $recordID = $this->params()->fromQuery('recordID');
        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        $recordDriver = $recordLoader->load($recordID);

        $parentRecordID = $recordDriver->getParentRecordID();
        $parentRecordDriver = $recordLoader->load($parentRecordID);
        $isn = $parentRecordDriver->getIsn();
        if ($isn === false)
            $isn = $recordDriver->getIsn();

        $openUrl = $recordDriver->getOpenURL();
        $additionalParams = array();
        parse_str($openUrl, $additionalParams);

        foreach ($additionalParams as $key => $val) {
            $additionalParams[str_replace("rft_", "rft.", $key)] = $val;
        }

        if (substr($isn, 0, 1) === 'M') {
            $isnKey = "rft.ismn";
        } else
            if ((strlen($isn) === 8) or (strlen($isn) === 9)) {
                $isnKey = "rft.issn";
            } else { // (strlen($isn) === 10) OR (strlen($isn) === 13)
                $isnKey = "rft.isbn";
            }

        $params = array(
            'sfx.institute' => $instituteLsShortcut,
            'ctx_ver' => 'Z39.88-2004',
            'ctx_enc' => 'info:ofi/enc:UTF-8',
            'sfx.response_type' => 'simplexml',
            $isnKey => str_replace("-", "", (string) $isn)
        );

        $allParams = array_merge($params, $additionalParams);

        $wantItFactory = $this->getServiceLocator()->get('WantIt\Factory');
        $electronicChoiceHandler = $wantItFactory->createElectronicChoiceHandlerObject(
            $recordDriver);

        $sfxResult = $electronicChoiceHandler->getRequestDataResponseAsArray($instituteLsLink,
            $allParams);

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

        $viewRend = $this->getViewRenderer();

        if (null === $ids)
            return $this->output(
                [
                    'status' => $this->getTranslatedUnknownStatus($viewRend)
                ], self::STATUS_ERROR);

        $ilsDriver = $this->getILS()->getDriver();

        if ($ilsDriver instanceof \CPK\ILS\Driver\MultiBackend) {

            try {
                $statuses = $ilsDriver->getStatuses($ids);
            } catch (\Exception $e) {
                return $this->output(
                    [
                        'status' => $this->getTranslatedUnknownStatus($viewRend),
                        'message' => $e->getMessage(),
                        'code' => $e->getCode()
                    ], self::STATUS_ERROR);
            }

            if (null === $statuses || empty($statuses))
                return $this->output('$ilsDriver->getStatuses returned nothing',
                    self::STATUS_ERROR);

            $itemsStatuses = [];

            foreach ($statuses as $status) {
                $id = $status['id'];

                $itemsStatuses[$id] = [];

                if (! empty($status['status']))
                    $itemsStatuses[$id]['status'] = $viewRend->transEsc(
                        'status_' . $status['status'], null, $status['status']);
                else {
                    // The status is empty - set label to 'label-danger'
                    $itemsStatuses[$id]['label'] = 'label-danger';

                    // And set the status to unknown status
                    $itemsStatuses[$id]['status'] = $this->getTranslatedUnknownStatus(
                        $viewRend);
                }

                if (! empty($status['due_date']))
                    $itemsStatuses[$id]['due_date'] = $status['due_date'];

                if (! empty($status['hold_type']))
                    $itemsStatuses[$id]['hold_type'] = $viewRend->transEsc(
                        $status['hold_type']);

                if (! empty($status['label']))
                    $itemsStatuses[$id]['label'] = $status['label'];

                if (! empty($status['availability']))
                    $itemsStatuses[$id]['availability'] = $status['availability'];

                $key = array_search($id, $ids);

                if ($key !== false)
                    unset($ids[$key]);
            }

            if (isset($ids) && count($ids) > 0)
                $retVal['remaining'] = $ids;

            $retVal['statuses'] = $itemsStatuses;
            return $this->output($retVal, self::STATUS_OK);
        } else
            return $this->output(
                "ILS Driver isn't instanceof MultiBackend - ending job now.",
                self::STATUS_ERROR);
    }

    public function getMyProfileAjax()
    {
        // Get the cat_username being requested
        $cat_username = $this->params()->fromPost('cat_username');

        $hasPermissions = $this->hasPermissions($cat_username);

        if ($hasPermissions instanceof \Zend\Http\Response)
            return $hasPermissions;

        $ilsDriver = $this->getILS()->getDriver();

        if ($ilsDriver instanceof \CPK\ILS\Driver\MultiBackend) {

            $patron = [
                'cat_username' => $cat_username,
                'id' => $cat_username
            ];

            try {
                // Try to get the profile ..
                $profile = $ilsDriver->getMyProfile($patron);
            } catch (\VuFind\Exception\ILS $e) {

                // Something went wrong - include cat_username to properly
                // attach the error message into the right table

                $debugMsg = ('development' == APPLICATION_ENV) ? ': ' .
                     $e->getMessage() : '';

                $message = $this->translate('An error has occurred') . $debugMsg;

                $data = [
                    'message' => $message,
                    'cat_username' => $cat_username
                ];

                return $this->output($data, self::STATUS_ERROR);
            }

            return $this->output($profile, self::STATUS_OK);
        } else
            return $this->output(
                "ILS Driver isn't instanceof MultiBackend - ending job now.",
                self::STATUS_ERROR);
    }

    public function getMyHoldsAjax()
    {
        // Get the cat_username being requested
        $cat_username = $this->params()->fromPost('cat_username');

        $hasPermissions = $this->hasPermissions($cat_username);

        if ($hasPermissions instanceof \Zend\Http\Response)
            return $hasPermissions;

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
                $holds = $catalog->getMyHolds($patron);
            } catch (\VuFind\Exception\ILS $e) {

                // Something went wrong - include cat_username to properly
                // attach the error message into the right table

                $debugMsg = ('development' == APPLICATION_ENV) ? ': ' .
                     $e->getMessage() : '';

                $message = $this->translate('An error has occurred') . $debugMsg;

                $data = [
                    'message' => $message,
                    'cat_username' => $cat_username
                ];

                return $this->output($data, self::STATUS_ERROR);
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

            $toRet = [
                'html' => $html,
                'obalky' => $obalky,
                'canCancel' => $libraryIdentity->cancelForm,
                'cat_username' => str_replace('.', '\.', $cat_username)
            ];

            return $this->output($toRet, self::STATUS_OK);
        } else
            return $this->output(
                "ILS Driver isn't instanceof MultiBackend - ending job now.",
                self::STATUS_ERROR);
    }

    public function getMyFinesAjax()
    {
        // Get the cat_username being requested
        $cat_username = $this->params()->fromPost('cat_username');

        $hasPermissions = $this->hasPermissions($cat_username);

        if ($hasPermissions instanceof \Zend\Http\Response)
            return $hasPermissions;

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
            } catch (\VuFind\Exception\ILS $e) {

                // Something went wrong - include cat_username to properly
                // attach the error message into the right table

                $debugMsg = ('development' == APPLICATION_ENV) ? ': ' .
                     $e->getMessage() : '';

                $message = $this->translate('An error has occurred') . $debugMsg;

                $data = [
                    'message' => $message,
                    'cat_username' => $cat_username
                ];

                return $this->output($data, self::STATUS_ERROR);
            }

            return $this->output($data, self::STATUS_OK);
        } else
            return $this->output(
                "ILS Driver isn't instanceof MultiBackend - ending job now.",
                self::STATUS_ERROR);
    }

    public function getMyTransactionsAjax()
    {
        // Get the cat_username being requested
        $cat_username = $this->params()->fromPost('cat_username');

        $hasPermissions = $this->hasPermissions($cat_username);

        if ($hasPermissions instanceof \Zend\Http\Response)
            return $hasPermissions;

        $ilsDriver = $this->getILS()->getDriver();

        if ($ilsDriver instanceof \CPK\ILS\Driver\MultiBackend) {

            $patron = [
                'cat_username' => $cat_username,
                'id' => $cat_username
            ];

            try {
                // Try to get the profile ..
                $transactions = $ilsDriver->getMyTransactions($patron);
            } catch (\VuFind\Exception\ILS $e) {

                // Something went wrong - include cat_username to properly
                // attach the error message into the right table

                $debugMsg = ('development' == APPLICATION_ENV) ? ': ' .
                     $e->getMessage() : '';

                $message = $this->translate('An error has occurred') . $debugMsg;

                $data = [
                    'message' => $message,
                    'cat_username' => $cat_username
                ];

                return $this->output($data, self::STATUS_ERROR);
            }

            return $this->output($transactions, self::STATUS_OK);
        } else
            return $this->output(
                "ILS Driver isn't instanceof MultiBackend - ending job now.",
                self::STATUS_ERROR);
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
     * @return \Zend\Http\Response|boolean
     */
    protected function hasPermissions($cat_username)
    {

        // Check user is logged in ..
        if (! $user = $this->getAuthManager()->isLoggedIn()) {
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

        if (! $isOwner) {
            // TODO: Implement incident reporting.
            return $this->output(
                'You are not authorized to query data about this identity. This incident will be reported.',
                self::STATUS_ERROR);
        }

        return true;
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
}