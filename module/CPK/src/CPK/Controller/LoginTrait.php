<?php
/**
 * Login trait (for automatic login within every controller)
 *
 * PHP version 5
 *
 * Copyright (C) Moravian Library 2016.
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
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace CPK\Controller;

use CPK\Exception\TermsUnaccepted;
use DomainException;
use VuFind\Exception\Auth as AuthException;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;

const ACCEPT_TERMS_OF_USE_QUERY = 'AcceptTermsOfUse';

/**
 * Login trait (for automatic login within every controller)
 *
 * Use this trait in every controller you with to process login request.
 *
 * It also takes care of redirecting user to AcceptTermsOfUse if the user is on portal for the very first time.
 *
 * @category VuFind2
 * @package Controller
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org Main Site
 */
trait LoginTrait
{


    /**
     * Overriden onDispatch to process Exceptions used to redirect user somewhere else
     *
     * {@inheritdoc}
     *
     * @see \Zend\Mvc\Controller\AbstractActionController::onDispatch()
     */
    public function onDispatch(MvcEvent $e)
    {
        if (!$this instanceof AbstractActionController)
            throw new \Exception('LoginTrait must be used in class derived from \Zend\Mvc\Controller\AbstractActionController');

        $routeMatch = $e->getRouteMatch();
        if (!$routeMatch) {
            /**
             *
             * @todo Determine requirements for when route match is missing.
             *       Potentially allow pulling directly from request metadata?
             */
            throw new DomainException('Missing route matches; unsure how to retrieve action');
        }

        $action = $routeMatch->getParam('action', 'not-found');
        $method = static::getMethodFromAction($action);

        if (!method_exists($this, $method)) {
            $method = 'notFoundAction';
        }

        try {
            $this->checkShibbolethLogin();
            $actionResponse = $this->$method();
        } catch (TermsUnaccepted $e) {
            return $this->redirect()->toUrl('/?' . ACCEPT_TERMS_OF_USE_QUERY);
        }

        $e->setResult($actionResponse);

        return $actionResponse;
    }

    protected function checkShibbolethLogin()
    {

        $acceptingTermsOfUse = $this->params()->fromQuery(ACCEPT_TERMS_OF_USE_QUERY) !== null;

        // This will effectively disallow the so called "buggy local logout" state
        $honorServiceProviderAuthState = true;

        // This is here for cases when we migrate honoring SP auth state to config .. not needed nor planned yet
        if (!$honorServiceProviderAuthState) {
            // Check shibboleth login
            $honorServiceProviderAuthState =
                $this->params()->fromPost('processLogin') ||
                $this->params()->fromPost('auth_method') ||
                $this->params()->fromQuery('auth_method');
        }

        // When accepting terms of use, don't honor service provider's authentication state
        $honorServiceProviderAuthState = $honorServiceProviderAuthState && !$acceptingTermsOfUse;

        if ($honorServiceProviderAuthState) {
            try {
                if (!$this->getAuthManager()->isLoggedIn()) {
                    $this->getAuthManager()->login($this->getRequest());
                }
            } catch (AuthException $e) {
                $this->processAuthenticationException($e);
            }
        }
    }
}