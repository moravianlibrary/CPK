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
	    $pretty_url = $this->params()->fromRoute('page');
	    
	    $portalPagesTable = $this->getTable("portalpages");
	    $page = $portalPagesTable->getPage($pretty_url, 'en-cpk');
	    
	    $view = $this->createViewModel([
	       'page' => $page,
	    ]);
	    
	    $view->setTemplate('portal/page');
	    return $view;
	}
}