<?php
/**
 * Portal Controller
 *
 * PHP version 5
 *
 * Copyright (C) Moravian Library 2015.
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
 * @author  Martin Kravec <martin.kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
namespace CPK\Controller;

use VuFind\Controller\AbstractBase;

/**
 * PortalController
 *
 * @author  Martin Kravec <martin.kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
class PortalController extends AbstractBase 
{
	/**
	 * View page
	 *
	 * @return mixed
	 */
	public function pageAction()
	{
	    $prettyUrl = $this->params()->fromRoute('subaction');
	    $language = $this->params()->fromRoute('param');
	    
	    $portalPagesTable = $this->getTable("portalpages");
	    $page = $portalPagesTable->getPage($prettyUrl);
	    
	    $view = $this->createViewModel([
	       'page' => $page,
	    ]);
	    
	    $view->setTemplate('portal/page');
	    
	    if (! $page) $view->setTemplate('error/404');
	   
	    if ($page['published'] != '1') {
	        $view->setTemplate('error/404');
	        $displayToken = $this->params()->fromQuery('displayToken');
	        if (! empty($displayToken)) {
	            /* @todo Rewrite next line with permissions control,
	            when method permissionsManagerAction will be finished */
    	        $randomToken = '94752eedb5baaf2896e35b4a76d9575c';
        	    if ($displayToken === $randomToken) {
        	        $view->setTemplate('portal/page');
        	    }
	        }
	    }
	    
	    return $view;
	}
}