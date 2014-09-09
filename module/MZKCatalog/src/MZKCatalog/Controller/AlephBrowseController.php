<?php
/**
 * AlphaBrowse Module Controller
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/alphabetical_heading_browse Wiki
 */
namespace MZKCatalog\Controller;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use VuFind\Controller\AbstractBase;
use VuFindHttp\HttpServiceInterface;

/**
 * AlphabrowseController Class
 *
 * Controls the alphabetical browsing feature
 *
 * @category VuFind2
 * @package  Controller
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/alphabetical_heading_browse Wiki
 */
class AlephBrowseController extends AbstractBase
{
    
    /**
     * HTTP service
     *
     * @var \MZKCatalog\AlephBrowse\Connector
     */
    protected $browse;
    
    public function __construct(\MZKCatalog\AlephBrowse\Connector $browse)
    {
        $this->browse = $browse;
    }
    
    /**
     * Gathers data for the view of the AlphaBrowser and does some initialization
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        $config = $this->getConfig();

        $types = $this->browse->getTypes();

        // Process incoming parameters:
        $source = $this->params()->fromQuery('source', false);
        $from   = $this->params()->fromQuery('from', false);
        $page   = intval($this->params()->fromQuery('page', 0));
        $limit  = isset($config->AlphaBrowse->page_size)
            ? $config->AlphaBrowse->page_size : 20;

        // Create view model:
        $view = $this->createViewModel();

        // If required parameters are present, load results:
        if ($source && $from !== false) {
            $items = $this->browse->browse($source, $from);
            if ($items['next'] != null) {
                $view->nextPage = null;
            }
            $result = array('Browse' => $items);
            $view->result = $result;
        }

        $view->alephBrowseTypes = $types;
        $view->from = $from;
        $view->source = $source;

        return $view;
    }

}