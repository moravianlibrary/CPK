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
namespace MZKCatalog\Controller;

use MZKCommon\Controller\RecordController as RecordControllerBase;

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

    const DEFAULT_REQUIRED_DATE = 2114380800; // 1. 1. 2037

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config VuFind configuration
     */
    public function __construct(\Zend\Config\Config $config)
    {
        // Call standard record controller initialization:
        parent::__construct($config);
    
        if (!isset($config->{'DigiRequest'}->to)) {
            
        }
        
        if (!isset($config->{'DigiRequest'}->from)) {
            
        }
        
        // Load default tab setting:
        $this->digiRequestFrom = $config->Site->email;
        $this->digiRequestTo = split(',', $config->{'DigiRequest'}->to);
        $this->digiRequestSubject = $config->{'DigiRequest'}->subject;
        
    }

    /**
     * Action for dealing with holds.
     *
     * @return mixed
     */
    public function holdAction()
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
        
        if (!isset($gatheredDetails['requiredBy'])) {
            $gatheredDetails['requiredBy'] = $this->getServiceLocator()->get('VuFind\DateConverter')
            ->convertToDisplayDate("U", self::DEFAULT_REQUIRED_DATE);
        }
    
        // Block invalid requests:
        if (!$catalog->checkRequestIsValid(
                $driver->getUniqueID(), $gatheredDetails, $patron
        )) {
            return $this->blockedholdAction();
        }
    
        // Send various values to the view so we can build the form:
        //$pickup = $catalog->getPickUpLocations($patron, $gatheredDetails);
        
        $details = $catalog->getHoldingInfoForItem($patron['id'], $gatheredDetails['id'],
                $gatheredDetails['item_id']);
        $pickup = array();
        foreach ($details['pickup-locations'] as $key => $value) {
            $pickup[] = array(
                "locationID" => $key, "locationDisplay" => $value
            );
        }
        
        $dueDate = $details['due-date'];
        $queued = $dueDate != null;
        
        $requestGroups = $catalog->checkCapability('getRequestGroups')
        ? $catalog->getRequestGroups($driver->getUniqueID(), $patron)
        : array();
        $extraHoldFields = isset($checkHolds['extraHoldFields'])
        ? explode(":", $checkHolds['extraHoldFields']) : array();
    
        // Process form submissions if necessary:
        if (!is_null($this->params()->fromPost('placeHold'))) {
            // If the form contained a pickup location or request group, make sure
            // they are valid:
            $valid = $this->holds()->validateRequestGroupInput(
                    $gatheredDetails, $extraHoldFields, $requestGroups
            );
            if (!$valid) {
                $this->flashMessenger()->setNamespace('error')
                ->addMessage('hold_invalid_request_group');
            } elseif (!$this->holds()->validatePickUpInput(
                    $gatheredDetails['pickUpLocation'], $extraHoldFields, $pickup
            )) {
                $this->flashMessenger()->setNamespace('error')
                ->addMessage('hold_invalid_pickup');
            } else {
                // If we made it this far, we're ready to place the hold;
                // if successful, we will redirect and can stop here.
    
                // Add Patron Data to Submitted Data
                $holdDetails = $gatheredDetails + array('patron' => $patron);
    
                // Attempt to place the hold:
                $function = (string)$checkHolds['function'];
                $results = $catalog->$function($holdDetails);
    
                // Success: Go to Display Holds
                if (isset($results['success']) && $results['success'] == true) {
                    $this->flashMessenger()->setNamespace('info')
                    ->addMessage('hold_place_success');
                    if ($this->inLightbox()) {
                        return false;
                    }
                    return $this->redirect()->toRoute('myresearch-holds');
                } else {
                    // Failure: use flash messenger to display messages, stay on
                    // the current form.
                    if (isset($results['status'])) {
                        $this->flashMessenger()->setNamespace('error')
                        ->addMessage($results['status']);
                    }
                    if (isset($results['sysMessage'])) {
                        $this->flashMessenger()->setNamespace('error')
                        ->addMessage($results['sysMessage']);
                    }
                }
            }
        }
    
        // Find and format the default required date:
        $defaultRequired = self::DEFAULT_REQUIRED_DATE;
        if (!$queued) {
            $defaultRequired = $this->holds()->getDefaultRequiredDate(
                    $checkHolds, $catalog, $patron, $gatheredDetails
                );
        }


        $defaultRequired = $this->getServiceLocator()->get('VuFind\DateConverter')
        ->convertToDisplayDate("U", $defaultRequired);
        try {
            $defaultPickup
            = $catalog->getDefaultPickUpLocation($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultPickup = false;
        }
        try {
            $defaultRequestGroup = empty($requestGroups)
            ? false
            : $catalog->getDefaultRequestGroup($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultRequestGroup = false;
        }
    
        $requestGroupNeeded = in_array('requestGroup', $extraHoldFields)
        && !empty($requestGroups)
        && (empty($gatheredDetails['level'])
                || $gatheredDetails['level'] != 'copy');
    
        return $this->createViewModel(
                array(
                    'gatheredDetails' => $gatheredDetails,
                    'pickup' => $pickup,
                    'dueDate' => $dueDate,
                    'defaultPickup' => $defaultPickup,
                    'homeLibrary' => $this->getUser()->home_library,
                    'extraHoldFields' => $extraHoldFields,
                    'defaultRequiredDate' => $defaultRequired,
                    'requestGroups' => $requestGroups,
                    'defaultRequestGroup' => $defaultRequestGroup,
                    'requestGroupNeeded' => $requestGroupNeeded,
                    'queued' => $queued,
                    'helpText' => isset($checkHolds['helpText'])
                    ? $checkHolds['helpText'] : null
                )
        );
    }

    public function digiRequestAction()
    {
        // Force login:
        if (!($user = $this->getUser())) {
            return $this->forceLogin(null);
        }

        // Process form submission:
        if ($this->params()->fromPost('submit')) {
            $this->processDigiRequest();
        }
        
        // use user email as default value
        $email = $user->email;
        // Retrieve the record driver:
        $driver = $this->loadRecord();
        
        $view = $this->createViewModel(
            array(
                'email'  => $email,
                'driver' => $driver
            )
        );
        $view->setTemplate('record/digitalization-request');
        return $view;
    }

    /**
     * ProcessSave -- store the results of the Save action.
     *
     * @return mixed
     */
    protected function processDigiRequest()
    {
        $post = $this->getRequest()->getPost()->toArray();
        $email = $post['email'];
        $reason = $post['reason'];
        $driver = $this->loadRecord();
        $params = array(
            'email'  => $email,
            'reason' => $reason,
            'driver' => $driver
        );
        $text = $this->getViewRenderer()->render('Email/digitalization-request.phtml', $params);
        $mailer = $this->getServiceLocator()->get('VuFind\Mailer');
        foreach ($this->digiRequestTo as $recipient) {
            $mailer->send(
                $this->digiRequestFrom,
                $recipient,
                $this->digiRequestSubject,
                $text
            );
        }
        return $this->redirectToRecord();
    }

}
