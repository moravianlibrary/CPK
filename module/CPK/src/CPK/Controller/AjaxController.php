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
     * Get Buy Links
     *
     * @author Martin Kravec <Martin.Kravec@mzk.cz>
     *
     * @return \Zend\Http\Response
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
     * @param string $institute
     *
     * @return array
     */
    public function callSfxAjax()
    {
        $institute = $this->params()->fromQuery('institute');
        if (! $institute)
            $institute = 'ANY';

        $recordID = $this->params()->fromQuery('recordID');
        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        $recordDriver = $recordLoader->load($recordID);

        $parentRecordID = $recordDriver->getParentRecordID();
        $parentRecordDriver = $recordLoader->load($parentRecordID);
        $isbn = $parentRecordDriver->getIsn();
        if ($isbn === false)
        	$isbn = $recordDriver->getIsn();
        

        $url = $this->params()->fromQuery('sfxUrl');

        
        $openUrl = $recordDriver->getOpenURL();
        $additionalParams = array();
        parse_str($openUrl, $additionalParams);
        
        foreach ($additionalParams as $key => $val) {
        	$additionalParams[str_replace("rft_", "rft.", $key)] = $val;
        }

        $params = array(
            'sfx.institute' => $institute,
            'ctx_ver' => 'Z39.88-2004',
            'ctx_enc' => 'info:ofi/enc:UTF-8',
            'sfx.response_type' => 'simplexml',
            'rft.isbn' => str_replace("-", "",
                (string) $isbn)
        );

        $allParams = array_merge($params, $additionalParams);

        $wantItFactory = $this->getServiceLocator()->get('WantIt\Factory');
        $electronicChoiceHandler = $wantItFactory->createElectronicChoiceHandlerObject(
            $recordDriver);

        $sfxResult = $electronicChoiceHandler->getRequestDataResponseAsArray($url,
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

    	$vars[] = array(
    			'field866' => $field866
    	);

    	// Done
    	return $this->output($vars, self::STATUS_OK);
    }

    /**
     * Downloads SerialSoulution360link content for current record.
     *
     * @param string $institute
     *
     * @return array
     */
    public function callSerialSoulution360link()
    {
        $institute = $this->params()->fromQuery('institute');
        if (! $institute)
            $institute = 'ANY';

        $recordID = $this->params()->fromQuery('recordID');
        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        $recordDriver = $recordLoader->load($recordID);

        $url = $this->params()->fromQuery('sfxUrl');

        $additionalParams = array();
        parse_str($recordDriver->getOpenURL(), $additionalParams);

        $params = array();

        $allParams = array_merge($params, $additionalParams);

        $wantItFactory = $this->getServiceLocator()->get('WantIt\Factory');
        $electronicChoiceHandler = $wantItFactory->createElectronicChoiceHandlerObject(
            $recordDriver);

        $ss360linkResult = $electronicChoiceHandler->getRequestDataResponseAsArray(
            $url, $allParams);

        $vars[] = array(
            'ss360link' => $ss360linkResult
        );

        // Done
        return $this->output($vars, self::STATUS_OK);
    }

    /**
     * Downloads EbscoLinksource content for current record.
     *
     * @param string $institute
     *
     * @return array
     */
    public function callEbscoLinksource()
    {
        $institute = $this->params()->fromQuery('institute');
        if (! $institute)
            $institute = 'ANY';

        $recordID = $this->params()->fromQuery('recordID');
        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        $recordDriver = $recordLoader->load($recordID);

        $url = $this->params()->fromQuery('sfxUrl');

        $additionalParams = array();
        parse_str($recordDriver->getOpenURL(), $additionalParams);

        $params = array();

        $allParams = array_merge($params, $additionalParams);

        $wantItFactory = $this->getServiceLocator()->get('WantIt\Factory');
        $electronicChoiceHandler = $wantItFactory->createElectronicChoiceHandlerObject(
            $recordDriver);

        $ebscoLinksourceResult = $electronicChoiceHandler->getRequestDataResponseAsArray(
            $url, $allParams);

        $vars[] = array(
            'ebscoLinksource' => $ebscoLinksourceResult
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

            $statuses = $ilsDriver->getStatuses($ids);

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
                    $itemsStatuses[$id]['hold_type'] = $viewRend->transEsc($status['hold_type']);

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

    protected function getTranslatedUnknownStatus($viewRend)
    {
        $initialString = 'Unknown Status';
        return $viewRend->transEsc('status_' . $initialString, null, $initialString);
    }
}