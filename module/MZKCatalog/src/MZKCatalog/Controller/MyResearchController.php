<?php
/**
 * MyResearch Controller
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace MZKCatalog\Controller;


use MZKCommon\Controller\MyResearchController as MyResearchControllerBase,
VuFind\Exception\Auth as AuthException,
VuFind\Exception\ListPermission as ListPermissionException,
VuFind\Exception\RecordMissing as RecordMissingException,
Zend\Stdlib\Parameters,
Zend\Session\Container as SessionContainer;

/**
 * Controller for the user account area.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MyResearchController extends MyResearchControllerBase
{

    public function profileAction()
    {
        $view = parent::profileAction();
        if ($view) {
            $profile = $view->profile;
            $profile['bookshelf'] = substr($profile['barcode'], -2, 2);
            $view->profile = $profile;
            $expire = date_create_from_format('d. m. Y', $view->profile['expire']);
            $dateDiff = date_diff($expire, date_create());
            if ($dateDiff->days < 30 && $dateDiff->invert != 0) {
                $this->flashMessenger()->setNamespace('error')->addMessage('library_card_expiration_warning');
            }
        }
        return $view;
    }

    public function finesAction() {
        $view = parent::finesAction();
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        if ($view) {
            $catalog = $this->getILS();
            $accruedOverdue = $catalog->getAccruedOverdue($patron);
            if ($accruedOverdue > 0) {
                $message = $this->translate('accrued_overdue_summary_text');
                $this->flashMessenger()->setNamespace('error')->addMessage($message . ' ' . $accruedOverdue);
            }
        }
        return $view;
    }

}
