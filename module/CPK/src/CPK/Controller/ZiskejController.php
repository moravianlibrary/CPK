<?php
/**
 * Ziskej Controller
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

use CPK\Controller\Exception\TicketNotFoundException;
use VuFind\Controller\AbstractBase;


class ZiskejController extends AbstractBase
{
    use LoginTrait;

    /**
     * @return \Zend\Http\Response|\Zend\View\Model\ViewModel
     * @throws \Http\Client\Exception
     */
    public function homeAction()
    {
        /** @var \Zend\View\Model\ViewModel $view */
        $view = $this->createViewModel();

        /** @var \CPK\Ziskej\ZiskejMvs $cpkZiskejMvs */
        $cpkZiskejMvs = $this->serviceLocator->get(\CPK\Ziskej\ZiskejMvs::class);

        /** @var \CPK\Ziskej\ZiskejEdd $cpkZiskejEdd */
        $cpkZiskejEdd = $this->serviceLocator->get(\CPK\Ziskej\ZiskejEdd::class);

        if ($this->getRequest()->isPost()) {
            if ($this->getRequest()->getPost('ziskejMvsMode')) {
                $cpkZiskejMvs->setMode($this->getRequest()->getPost('ziskejMvsMode'));
            }
            if ($this->getRequest()->getPost('ziskejEddMode')) {
                $cpkZiskejEdd->setMode($this->getRequest()->getPost('ziskejEddMode'));
            }
            $this->flashMessenger()->addMessage('message_ziskej_mode_saved', 'success');
            return $this->redirect()->refresh();
        }

        $view->setVariable('ziskejMvsModes', $cpkZiskejMvs->getModes());
        $view->setVariable('ziskejEddModes', $cpkZiskejEdd->getModes());

        $view->setVariable('ziskejMvsCurrentMode', $cpkZiskejMvs->getCurrentMode());
        $view->setVariable('ziskejEddCurrentMode', $cpkZiskejEdd->getCurrentMode());

        if (!$cpkZiskejMvs->isEnabled() && !$cpkZiskejEdd->isEnabled()) {
            return $view;
        }

        /** @var \VuFind\Db\Row\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $view;
        }
        $view->setVariable('user', $user);

        /** @var \VuFind\Db\Row\UserCard[] $userCards */
        $userCards = $user->getAllUserLibraryCards();

        /** @var \CPK\ILS\Driver\MultiBackend $multiBackend */
        $multiBackend = $this->getILS()->getDriver();

        try {
            /** @var \Mzk\ZiskejApi\Api $ziskejApi */
            $ziskejApi = $this->serviceLocator->get('Mzk\ZiskejApi\Api');

            /** @var string[] $ziskejLibsCodes */
            $ziskejLibsCodes = [];

            $ziskejLibs = $ziskejApi->getLibrariesAll();
            foreach ($ziskejLibs->getAll() as $ziskejLib) {
                $id = $multiBackend->siglaToSource($ziskejLib->getSigla());
                if (!empty($id)) {
                    $ziskejLibsCodes[] = $id;
                }
            }

            $data = [];

            /** @var \VuFind\Db\Row\UserCard $userCard */
            foreach ($userCards as $userCard) {
                $eppn = $userCard['eppn'];

                $data[$eppn]['home_library'] = $userCard['home_library'];   //@todo

                $inZiskej = in_array($userCard['home_library'], $ziskejLibsCodes);
                $data[$eppn]['library_in_ziskej'] = $inZiskej;  //@todo

                if ($inZiskej) {
                    /** @var \Mzk\ZiskejApi\ResponseModel\Reader $ziskejReader */
                    $ziskejReader = $ziskejApi->getReader($eppn);
                    $data[$eppn]['reader'] = $ziskejReader;

                    if ($ziskejReader && $ziskejReader->isActive()) {
                        /** @var \Mzk\ZiskejApi\ResponseModel\TicketsCollection $ziskejTickets */
                        $ziskejTickets = $ziskejApi->getTickets($eppn)->getAll();
                        /** @var \Mzk\ZiskejApi\ResponseModel\Ticket $ziskejTicket */
                        foreach ($ziskejTickets as $ziskejTicket) {
                            $data[$eppn]['tickets'][$ziskejTicket->getId()]['ticket'] = $ziskejTicket;
                            $data[$eppn]['tickets'][$ziskejTicket->getId()]['messages'] = $ziskejApi->getMessages($eppn, $ziskejTicket->getId())->getAll();
                        }
                    }
                }
            }

            /** @var array ziskejData */
            $view->setVariable('data', $data);
        } catch (\Exception $ex) {
            $this->flashMessenger()->addMessage($ex->getMessage(), 'warning');
            //$this->flashMessenger()->addMessage('ziskej_warning_api_disconnected', 'warning');    //@todo zapnout na produkci
        }

        return $view;
    }

    /**
     * @return \Zend\Http\Response
     */
    public function paymentAction()
    {
        $eppnDomain = $this->params()->fromRoute('eppn_domain');
        $ticketId = $this->params()->fromRoute('ticket_id');
        $payment_transaction_id = $this->params()->fromRoute('payment_transaction_id');

        return $this->redirect()->toRoute('MyResearch-ziskejTicket', [
            'eppn_domain' => $eppnDomain,
            'ticket_id' => $ticketId
        ]);
    }

    /**
     * Ziskej order finished page
     *
     * @return mixed|\Zend\View\Model\ViewModel
     * @throws \CPK\Controller\Exception\TicketNotFoundException
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function finishedAction(){
        $eppnDomain = $this->params()->fromRoute('eppn_domain');
        if (!$eppnDomain) {
            throw new TicketNotFoundException('The requested order was not found');
        }

        $ticketId = $this->params()->fromRoute('ticket_id');
        if (!$ticketId) {
            throw new TicketNotFoundException('The requested order was not found');
        }

        $user = $this->getAuthManager()->isLoggedIn();
        if (!$user) {
            return $this->forceLogin();
        }

        /** @var \VuFind\Db\Row\UserCard[] $userCards */
        $userCards = $user->getAllUserLibraryCards();

        $userCard = null;
        $eppn = null;
        /** @var \VuFind\Db\Row\UserCard $userCard */
        foreach ($userCards as $card) {
            if ($eppnDomain == $card->getEppnDomain()) {
                $userCard = $card;
                $eppn = $card->eppn;
            }
        }

        if (!$userCard || !$eppn) {
            throw new TicketNotFoundException('The requested order was not found');
        }

        /** @var \Mzk\ZiskejApi\Api $ziskejApi */
        $ziskejApi = $this->serviceLocator->get('Mzk\ZiskejApi\Api');

        $ticket = $ziskejApi->getTicket($eppn, $ticketId);

        $view = $this->createViewModel();
        $view->setVariable('userCard', $userCard);
        $view->setVariable('ticket', $ticket);
        return $view;
    }

}
