<?php

/**
 * Notification view helper
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
 * @package  View_Helpers
 * @author   Václav Rosecký <xrosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace CPK\View\Helper\CPK;

use Zend\Config\Config;
use VuFind\View\Helper\Root\TransEsc;

/**
 * Notifications view Helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Václav Rosecký <xrosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Notifications extends \Zend\View\Helper\AbstractHelper {

    /**
     * Authentication manager
     *
     * @var \VuFind\Auth\Manager
     */
    protected $manager;

    /**
     * Constructor
     *
     * @param \VuFind\Auth\Manager $manager Authentication manager
     */
    public function __construct(\VuFind\Auth\Manager $manager)
    {
        $this->manager = $manager;
    }

    public function getSources() {
        $user = $this->manager->isLoggedIn();
        if (!$user) {
            return [];
        }
        $sources = [];
        $sources['cpk'] = 'Knihovny.cz';
        foreach ($user->getLibraryCards(false) as $libraryCard) {
            $libraryCode = explode('.', $libraryCard['cat_username'])[0];
            $sources[$libraryCode] = $libraryCode;
        }
        return $sources;
    }

}
