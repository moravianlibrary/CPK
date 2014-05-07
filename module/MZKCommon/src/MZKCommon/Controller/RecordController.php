<?php
/**
 * MyResearch Controller
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
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace MZKCommon\Controller;

use VuFind\Controller\RecordController as RecordControllerBase;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class RecordController extends RecordControllerBase
{
    
    /**
     * Add a tag
     *
     * @return mixed
     */
    public function addtagAction()
    {
        // Force login:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }
    
        // Obtain the current record object:
        $driver = $this->loadRecord();
    
        // Save tags, if any:
        if ($this->params()->fromPost('submit')) {
            $tags = $this->params()->fromPost('tag');
            $tagParser = $this->getServiceLocator()->get('VuFind\Tags');
            $driver->addTags($user, $tagParser->parse($tags));
            return $this->redirectToRecord('', 'TagsAndComments');
        }
    
        // Display the "add tag" form:
        $view = $this->createViewModel();
        $view->setTemplate('record/addtag');
        return $view;
    }

    public function shortLoanAction()
    {
        $driver = $this->loadRecord();

        // If we're not supposed to be here, give up now!
        $catalog = $this->getILS();
        $checkHolds = $catalog->checkFunction("Holds", $driver->getUniqueID());
        if (!$checkHolds) {
            return $this->forwardTo('Record', 'Home');
        }

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->holds()->validateRequest($checkHolds['HMACKeys']);
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Block invalid requests:
        if (!$catalog->checkRequestIsValid(
            $driver->getUniqueID(), $gatheredDetails, $patron
        )) {
            return $this->blockedholdAction();
        }

        $shortLoanInfo = $catalog->getHoldingInfoForItem($patron['id'],
            $driver->getUniqueID(), $this->params()->fromQuery('item_id'));

        $slotsByDate = array();
        foreach ($shortLoanInfo['slots'] as $id => $slot) {
            $start_date = $slot['start_date'];
            $start_time = $slot['start_time'];
            $slotsByDate[$start_date][$start_time] = $slot;
            $slotsByDate[$start_date][$start_time]['id'] = $id;
            $slotsByDate[$start_date][$start_time]['available'] = true;
        }

        static $positions = array(
            '0830' => 0,
            '1000' => 1,
            '1200' => 2,
            '1400' => 3,
            '1600' => 4,
            '1800' => 5,
            '2000' => 6,
        );

        $results = array();
        foreach ($slotsByDate as $date => $slotsInDate) {
            $result = array_fill(0, 7, array('available' => false));
            foreach ($slotsInDate as $start_time => $slot) {
                $result[$positions[$start_time]] = $slot;
            }
            $results[$date] = $result;
        }

        $view = $this->createViewModel(array('slots' => $results));
        $view->setTemplate('record/shortloan');
        return $view;
    }

}
