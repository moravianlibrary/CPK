<?php
/**
 * Libraries Controller
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
 * @author  Jakub Šesták <sestak@mzk.cz>
 * @license http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
namespace CPK\Controller;

use VuFind\Controller\AbstractBase;

/**
 * PortalController
 *
 * @author  Jakub Šesták <sestak@mzk.cz>
 * @license http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
class LibrariesController extends AbstractBase
{
	public function listAction()
	{
		$view = $this->createViewModel([

		]);

		$librariesLoader = $this->getServiceLocator()->get('CPK\Libraries');

		$libraries = $librariesLoader->LoadLibraries("brno","10","0","active");

		$view->libraries = $libraries;

		$view->apikey= $this->getConfig()->GoogleMaps->apikey;

		$view->setTemplate('libraries/list');

		return $view;

	}

	public function libraryAction()
	{
		$view = $this->createViewModel([

		]);

		$librariesLoader = $this->getServiceLocator()->get('CPK\Libraries');

		$library = $librariesLoader->LoadLibrary("BOA001");

		$view->library = $library;

		$view->apikey= $this->getConfig()->GoogleMaps->apikey;

		$view->setTemplate('libraries/library');

		return $view;

	}




}