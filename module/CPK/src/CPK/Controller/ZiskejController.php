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

use VuFind\Controller\AbstractBase;


class ZiskejController extends AbstractBase
{
    use LoginTrait;

    public function homeAction()
    {
        $view = $this->createViewModel();
        //$view->setTemplate('ziskej/ziskej');

        $ziskejModes = array_keys($this->getConfig()->Ziskej->toArray());
        /** @var array ziskejModes */
        $view->ziskejModes = $ziskejModes;

        $request = $this->getRequest();
        if ($this->getRequest()->isPost()) {
            $data = in_array($this->getRequest()->getPost('ziskej'), $ziskejModes)
                ? $this->getRequest()->getPost('ziskej')
                : 'disabled';
            setcookie('ziskej', $data, 0, '/');
            $this->redirect()->refresh();
        }

        $ziskejCurrentMode = $request->getCookie()->ziskej ?? 'disabled';
        /** @var string ziskejCurrentMode */
        $view->ziskejCurrentMode = $ziskejCurrentMode;

        if ($ziskejCurrentMode === 'disabled') {
            return $view;
        }

        /** @var \VuFind\Db\Row\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $view;
        }

        $userData = $user->toArray();
        /** @var array userData */
        $view->userData = $userData;

        $multiBackend = $this->getILS()->getDriver();

        try {
            /** @var \Mzk\ZiskejApi\Api $ziskejApi */
            $ziskejApi = $this->serviceLocator->get('Mzk\ZiskejApi\Api');

            $libraryIds = [];
            $ziskejLibraries = $ziskejApi->getLibraries();
            foreach ($ziskejLibraries as $sigla) {
                $id = $multiBackend->siglaToSource($sigla);
                if (!empty($id)) {
                    $libraryIds[] = $id;
                }
            }

            $userCards = $user->getAllUserLibraryCards();

            $allData = [];

            /** @var \VuFind\Db\Row\UserCard $userCard */
            foreach ($userCards as $userCard) {
                $eppn = $userCard['eppn'];

                $allData[$eppn]['home_library'] = $userCard['home_library'];

                $inZiskej = in_array($userCard['home_library'], $libraryIds);
                $allData[$eppn]['library_in_ziskej'] = $inZiskej;

                if ($inZiskej) {
                    $reader = $ziskejApi->getReader($eppn);
                    $allData[$eppn]['reader'] = $reader;

                    if ($reader && $reader->isActive()) {
                        $tickets = $ziskejApi->getTicketsDetails($eppn);
                        foreach ($tickets as $ticket) {
                            $allData[$eppn]['tickets'][$ticket['hid']] = $ticket;
                            $messages = $ziskejApi->getMessages($eppn, $ticket['ticket_id']);
                            $allData[$eppn]['tickets'][$ticket['hid']]['messages'] = $messages;
                        }
                    }
                }
            }

            /** @var array ziskejData */
            $view->ziskejData = $allData;
        } catch (\Exception $ex) {
            $this->flashMessenger()->addMessage('ziskej_warning_api_disconnected', 'warning');
        }

        return $view;
    }
}
