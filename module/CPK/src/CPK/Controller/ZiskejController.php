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
    /**
     * Home Action
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        $view = $this->createViewModel();
        $view->setTemplate('ziskej/ziskej');
        $view->ziskejConfig = array_keys($this->getConfig()->Ziskej->toArray());

        // Check if it is post request
        if ($this->getRequest()->isPost()) {
            // Check if ziskej value for cookie, which we get through post request, is exist in config
            // Default value - disabled
            $data = in_array($this->getRequest()->getPost('ziskej'),
                $view->ziskejConfig) ? $this->getRequest()->getPost('ziskej') : 'disabled';
            setcookie('ziskej', $data, 0);
            $view->setting = $data;
        }

        return $view;
    }
}
