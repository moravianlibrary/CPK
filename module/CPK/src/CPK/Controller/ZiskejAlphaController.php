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
            $SensitiveZiskejConfig = $this->getConfig()->SensitiveZiskej->toArray();
            $this->setZiskej($SensitiveZiskejConfig);
            // Check if it's post request
            if ($this->getRequest()->isPost()) {
                // Check if ziskej value for cookie, which we get through post request, is exist in config
                // Default value - disabled
                $data = in_array($this->getRequest()->getPost('ziskej'),
                    $view->ziskejConfig) ? $this->getRequest()->getPost('ziskej') : 'disabled';
                setcookie('ziskej', $data, 0);
                $view->setting = $data;
            }
            $libraries = $this->ziskej->getLibraries();
            $reader = $this->ziskej->getReader('1185@mzk.cz');
            d($libraries);
            d($reader);
            $libraryIds = [];
            foreach ($libraries['items'] as $sigla) {
                $id = $this->getLibraryId($sigla);
                if (!empty($id)) {
                    $libraryIds[] = $id;
                }
            }
            d($libraryIds);
            $userTickets = $this->ziskej->getUserTickets('1185@mzk.cz');
            d($userTickets);
            return $view;
        }

        /**
         * @return object ZiskejDriver
         */
        public function getZiskej()
        {
            return $this->ziskej;
        }

        /**
         * @param $config
         */
        public function setZiskej($config)
        {
            $this->ziskej = new Ziskej($config);
        }

        public function getLibraryId($sigla)
        {
            $ils = $this->getILS();
            $driver = $ils->getDriver();
            $source = $driver->siglaToSource($sigla);
            return $driver->sourceToLibraryId($source);
        }

    }