<?php
/**
 * ZiskejAlpha Controller
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2018.
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
 * @author  Andrii But <but@mzk.cz>
 * @license http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */

namespace CPK\Controller;


use CPK\ILS\Driver\Ziskej;
use VuFind\Controller\AbstractBase;
use Zend\Json\Json;

class ZiskejAlphaController extends AbstractBase
{
    use LoginTrait;

    /**
     * Home Action
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        $view = $this->createViewModel();
        $view->setTemplate('ziskej/ziskej-alpha');
        $view->ziskejConfig = array_keys($this->getConfig()->Ziskej->toArray());
        $request            = $this->getRequest();
        // Check if it's post request
        if ($this->getRequest()->isPost()) {
            // Check if ziskej value for cookie, which we get through post request, is exist in config
            // Default value - disabled
            $data = in_array($this->getRequest()->getPost('ziskej'), $view->ziskejConfig) ? $this->getRequest()
                ->getPost('ziskej') : 'disabled';
            setcookie('ziskej', $data, 0);
            $this->redirect()->refresh();
        }
        $ziskejCookie  = $request->getCookie()->ziskej;
        $view->setting = $ziskejCookie;

        if ( ! isset($ziskejCookie) || $ziskejCookie == 'disabled') {
            $view->setting = 'disabled';

            return $view;
        }

        $ziskej = $this->getZiskej();
        $eppn   = $request->getServer()->eduPersonPrincipalName;

        if (isset($ziskejCookie) && $ziskejCookie != 'disabled' && $this->getUser()) {
            $libraries        = $ziskej->getLibraries();
            $librariesContent = $this->getContent($libraries);
            $librarySources   = [];
            foreach ($librariesContent['items'] as $sigla) {
                $id = $this->getLibraryId($sigla);
                if ( ! empty($id)) {
                    $librarySources[] = $id;
                }
            }

            $user = $this->getUser();
            $reader = $ziskej->getReader($eppn);
            if ($reader->getStatusCode() == 200) {
                if (in_array($user->home_library, $librarySources)) {
                    $view->reader = $this->getContent($reader);

                    $userTickets       = $ziskej->getUserTickets($eppn);
                    $view->userTickets = $this->getContent($userTickets)['items'];

                    foreach ($view->userTickets as $userTicket) {
                        $ticketDetail        = $ziskej->getTicketDetail($userTicket, $eppn);
                        $ticketDetailContent = $this->getContent($ticketDetail);
                        if ($ticketDetailContent['count_messages'] > 0) {
                            $messages                        = $ziskej->getTicketMessages($userTicket, $eppn);
                            $messagesContent                 = $this->getContent($messages)['items'];
                            $ticketDetailContent['messages'] = $messagesContent;
                        }
                        $ticketDetails[] = $ticketDetailContent;
                    }
                    $view->ticketDetails = $ticketDetails;
                } else {
                    $view->libNotInZiskej = true;
                }
            } else {
                $view->redirect = $this->getConfig()->Ziskej->$ziskejCookie;
            }
        }

        return $view;
    }

    public function getContent($response)
    {
        $responseContent = [];
        if ( ! empty($response)) {
            $responseContent = Json::decode($response->getContent(), true);
        }

        return $responseContent;
    }

    public function getLibraryId($sigla)
    {
        $ils    = $this->getILS();
        $driver = $ils->getDriver();

        return $driver->siglaToSource($sigla);
    }

    public function getLibrarySigla($source)
    {
        $ils    = $this->getILS();
        $driver = $ils->getDriver();

        return $driver->sourceToSigla($source);
    }

    protected function getZiskej()
    {
        $cookie           = $this->getRequest()->getCookie()->ziskej;
        $url              = $this->getConfig()->Ziskej->$cookie;
        $sensZiskejConfig = $this->getConfig()->SensitiveZiskej->toArray();

        $ziskej = Ziskej::getZiskej();
        $ziskej->setConfig($sensZiskejConfig);
        $ziskej->setApiUrl($url);

        return $ziskej;
    }

    public function registrationAction()
    {
        $ziskej  = $this->getZiskej();
        $request = $this->getRequest();

        if ($request->isPost() && $this->getUser()) {
            $user         = $this->getUser();
            $gdprReg      = $request->getPost('is_gdpr_reg');
            if ( ! $gdprReg) {
                $this->flashMessenger()->addMessage('Potrebujeme souhlas s registraci', 'error');
                return $this->redirect()->toRoute('ziskejalpha');
            }
            $gdprData     = $request->getPost('is_gdpr_data');
            $notification = $request->getPost('notification_enabled');
            $eppn         = $request->getServer()->eduPersonPrincipalName;
            $sigla        = $this->getLibrarySigla($user->home_library);
            if ($sigla) {
                $params       = [
                    'first_name'           => $user->firstname,
                    'last_name'            => $user->lastname,
                    'email'                => $user->email,
                    'notification_enabled' => $notification ? true : false,
                    'sigla'                => $sigla,
                    'is_gdpr_reg'          => $gdprReg ? true : false,
                    'is_gdpr_data'         => $gdprData ? true : false,
                ];
                $status_codes = [200, 201];
                $resp         = $ziskej->regOrUpdateReader($eppn, $params);
                if (in_array($resp->getStatusCode(), $status_codes)) {
                    $this->flashMessenger()->addMessage('You were registered', 'success');
                } else {
                    $this->flashMessenger()->addMessage($this->getContent($resp)['error'], 'error');
                }
            } else {
                $this->flashMessenger()->addMessage('Ziskej do not support your library ', 'error');
            }
        }

        return $this->redirect()->toRoute('ziskejalpha');
    }

    public function createTicketAction()
    {
        $ziskej  = $this->getZiskej();
        $request = $this->getRequest();

        if ($request->isPost() && $this->getUser()) {
            $eppn     = $request->getServer()->eduPersonPrincipalName;
            $recordId = $request->getPost('recordId');
            try {
                $resp   = $ziskej->createTicket($eppn, $recordId, []);
                $status = $resp->getStatusCode();
            } catch (\Exception $e) {
                $status = 500;
            }

            if ($status == 201 || $status == 200) {
                $this->flashMessenger()->addMessage('Ticket created', 'success');
            } elseif ($status == 500) {
                $this->flashMessenger()->addMessage('Ziskej server is not working', 'error');
            } else {
                $this->flashMessenger()->addMessage($this->getContent($resp)['error'], 'error');
            }
        }

        return $this->redirect()->toRoute('ziskejalpha');
    }
}