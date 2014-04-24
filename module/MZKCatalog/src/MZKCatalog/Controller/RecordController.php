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

    public function digiRequestAction()
    {
        // Process form submission:
        if ($this->params()->fromPost('submit')) {
            $this->processDigiRequest();
        }
        
        // Retrieve the record driver:
        $driver = $this->loadRecord();
        
        $view = $this->createViewModel(
            array(
            )
        );
        $view->setTemplate('record/digirequest');
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
