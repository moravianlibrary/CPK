<?php
/**
 * Search Controller
 *
 * PHP version 5
 *
 * Copyright (C) MZK 2015.
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
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace CPK\Controller;

use VuFind\Controller\SearchController as SearchControllerBase;

/**
 * SearchController
 *
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class SearchController extends SearchControllerBase
{
	/**
	 * Results action.
	 *
	 * @return mixed
	 */
	public function resultsAction()
	{
		// Special case -- redirect tag searches.
		$tag = $this->params()->fromQuery('tag');
		if (!empty($tag)) {
			$query = $this->getRequest()->getQuery();
			$query->set('lookfor', $tag);
			$query->set('type', 'tag');
		}
		if ($this->params()->fromQuery('type') == 'tag') {
			return $this->forwardTo('Tag', 'Home');
		}

		// Default case -- standard behavior.
		$view = parent::resultsAction();

		$view->myLibs = $this->getUsersHomeLibraries();

		return $view;
	}

	/**
	 * User's Library cards (home_library values)
	 *
	 * @return	array
	 */
	public function getUsersHomeLibraries()
	{
        $account = $this->getAuthManager();
        if ($account->isLoggedIn()) { // is loggedIn

			$user = $this->getUser();
			$libraryCards = $user->getLibraryCards()->toArray();

			$myLibs = array();

			foreach ($libraryCards as $libCard) {
				$homeLib = $libCard['home_library'];
				$myLibs[] = $homeLib;
			}

			return array_unique($myLibs);
		} else
		  return [];
	}
}