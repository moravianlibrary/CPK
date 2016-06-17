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
		$view = $this->createViewModel();

		$getParameters = $this->getRequest()->getQuery()->toArray();
		$query = (isset($getParameters['query']) && ! empty($getParameters['query'])) ? $getParameters['query'] : null;
		$page = (isset($getParameters['page']) && ! empty($getParameters['page'])) ? $getParameters['page'] : null;
		if($page==null) $page = 1;

		$librariesLoader = $this->getServiceLocator()->get('CPK\Libraries');

		$libraries = $librariesLoader->GetSearchResults($query, $page);
		if ($libraries==null) {
			$view->setTemplate('libraries/not-found');
			return $view;
		}
		$resultsCount = $librariesLoader->GetCountOfAllSearchResults($query);
		$view->page = $page;
		$view->resultsCount = $resultsCount;
		$view->from = (($page-1)*10)+1;
		$view->to = min($page * 10,$resultsCount);
		
		$view->query = $query;
		$view->pagination = $librariesLoader->GetPagination($query, $page);

		$view->libraries = $libraries;
		$view->apikey= (isset($this->getConfig()->GoogleMaps->apikey) && ! empty($this->getConfig()->GoogleMaps->apikey)) ? $this->getConfig()->GoogleMaps->apikey : null;
		$view->setTemplate('libraries/list');
		return $view;

	}

	public function libraryAction()
	{
		$view = $this->createViewModel();

		$getParameters = $this->getRequest()->getQuery()->toArray();
		$sigla = $getParameters['sigla'];

		$librariesLoader = $this->getServiceLocator()->get('CPK\Libraries');

		$library = $librariesLoader->LoadLibrary($sigla);

		$view->library = $library;

		$view->apikey= $this->getConfig()->GoogleMaps->apikey;

		$view->setTemplate('libraries/library');

		return $view;

	}




}