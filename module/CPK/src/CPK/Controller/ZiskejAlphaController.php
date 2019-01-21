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

class ZiskejAlphaController extends AbstractBase
{
    use LoginTrait;

    protected $ziskej;

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

        $sensitiveZiskejConfig = $this->getConfig()->SensitiveZiskej->toArray();
        $this->ziskej          = new Ziskej($sensitiveZiskejConfig);

        // Check if it's post request
        if ($this->getRequest()->isPost()) {
            // Check if ziskej value for cookie, which we get through post request, is exist in config
            // Default value - disabled
            $data = in_array(
                $this->getRequest()->getPost('ziskej'),
                $view->ziskejConfig
            ) ? $this->getRequest()->getPost('ziskej') : 'disabled';
            setcookie('ziskej', $data, 0);
            $view->setting = $data;
        }

        $request = $this->getRequest();
        $eppn = $request->getServer()->eduPersonPrincipalName;
        $libraries        = $this->ziskej->getLibraries();
        $librariesContent = $this->getContent($libraries);
        $librarySources   = [];
        foreach ($librariesContent['items'] as $sigla) {
            $id = $this->getLibraryId($sigla);
            if ( ! empty($id)) {
                $librarySources[] = $id;
            }
        }

        $view->user = $this->getUser();
        if ($user = $this->getUser()) {
            $reader = $this->ziskej->getReader($eppn);
            if (in_array($user->getSource(), $librarySources) && $reader->getStatusCode() == 200) {
                $view->reader = $this->getContent($reader);

                $userTickets       = $this->ziskej->getUserTickets(['eppn' => $eppn]);
                $view->userTickets = $this->getContent($userTickets)['items'];

                foreach ($view->userTickets as $userTicket) {
                    $ticketDetail    = $this->ziskej->getTicketDetail($userTicket, $eppn);
                    $ticketDetails[] = $this->getContent($ticketDetail);
                }
                $view->ticketDetails = $ticketDetails;

                $messages       = $this->ziskej->getTicketMessages($view->userTickets[0], $eppn);
                $view->messages = $this->getContent($messages)['items'];
            } else {
                $view->redirect = 'https://ziskej-test.techlib.cz/';
            }
        } else {
        }
        return $view;
    }

    public function getContent($response)
    {
        $responseContent = [];
        if ( ! empty($response)) {
            $responseContent = json_decode($response->getContent(), true);
        }

        return $responseContent;
    }

    public function getLibraryId($sigla)
    {
        $ils    = $this->getILS();
        $driver = $ils->getDriver();
//            $source = $driver->siglaToSource($sigla);
//            return $driver->sourceToLibraryId($source);
        return $driver->siglaToSource($sigla);
    }

}