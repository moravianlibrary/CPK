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
    
}
