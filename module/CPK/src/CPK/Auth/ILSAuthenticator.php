<?php
/**
 * Class for managing ILS-specific authentication.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace CPK\Auth;
use VuFind\Exception\ILS as ILSException, VuFind\ILS\Connection as ILSConnection, VuFind\Auth\ILSAuthenticator as ILSAuthenticatorBase;

/**
 * Class for managing ILS-specific authentication.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class ILSAuthenticator extends ILSAuthenticatorBase
{

    /**
     * We don't actually use credentials .. this is what Shibboleth is for
     *
     * Returns associative array of patron data on success, false on failure.
     *
     * @return array|bool
     */
    public function storedCatalogLogin()
    {
        if (($user = $this->auth->isLoggedIn())
            && isset($user->cat_username) && !empty($user->cat_username)
        ) {
            $patron = [
                'cat_username' => $user->cat_username,
                'id' => $user->cat_username
            ];

            return $patron;
        }

        return false;
    }

}
