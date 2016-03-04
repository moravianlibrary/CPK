<?php

/**
 * Notifications backend handler
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * @package  Notifications
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Notifications;

use CPK\Db\Table\Notifications;

class NotificationsHandler
{

    /**
     * Needed for translation
     *
     * @var \Zend\View\Renderer\RendererInterface
     */
    protected $viewModel;

    /**
     * Default css class for new notifications
     *
     * @var string
     */
    protected $newNotifClass = 'warning';

    /**
     * C'tor
     *
     * @param \Zend\View\Renderer\RendererInterface $viewModel            
     */
    public function __construct(\Zend\View\Renderer\RendererInterface $viewModel, Notifications $notificationsTable)
    {
        $this->viewModel = $viewModel;
        $this->notificationsTable = $notificationsTable;
    }

    /**
     * Returns notifications of user's blocks.
     *
     * TODO: Make use of DB's notifications cache
     *
     * @param \CPK\ILS\Driver\MultiBackend $ilsDriver            
     * @param array $patron            
     */
    public function getMyBlocks(\CPK\ILS\Driver\MultiBackend $ilsDriver, $patron)
    {
        $source = $patron['source'];
        
        $profile = $ilsDriver->getMyProfile($patron);
        
        $errors = $notifications = [];
        
        if (is_array($profile) && count($profile) === 0) {
            
            array_push($errors, 'Error fetching profile in "' . $source . '": ' . $this->translate('profile_fetch_problem'));
        } else 
            if (count($profile['blocks'])) {
                
                $notification = [
                    'clazz' => $this->newNotifClass,
                    'message' => $this->translate('notif_you_have_blocks'),
                    'href' => '/MyResearch/Profile#' . $source
                ];
                
                array_push($notifications, $notification);
            }
        
        return [
            'errors' => $errors,
            'notifications' => $notifications
        ];
    }

    /**
     * Returns notifications of user's fines.
     *
     * TODO: Make use of DB's notifications cache
     *
     * @param \CPK\ILS\Driver\MultiBackend $ilsDriver            
     * @param array $patron            
     */
    public function getMyFines(\CPK\ILS\Driver\MultiBackend $ilsDriver, $patron)
    {
        $source = $patron['source'];
        
        $fines = $ilsDriver->getMyFines($patron);
        
        unset($fines['source']);
        
        $errors = $notifications = [];
        
        if (count($fines)) {
            
            $notification = [
                'clazz' => $this->newNotifClass,
                'message' => $this->translate('notif_you_have_fines'),
                'href' => '/MyResearch/Fines#' . $source
            ];
            
            array_push($notifications, $notification);
        }
        
        return [
            'errors' => $errors,
            'notifications' => $notifications
        ];
    }

    /**
     * Returns notifications of user's overdues.
     *
     * TODO: Make use of DB's notifications cache
     *
     * @param \CPK\ILS\Driver\MultiBackend $ilsDriver            
     * @param array $patron            
     */
    public function getMyOverdues(\CPK\ILS\Driver\MultiBackend $ilsDriver, $patron)
    {
        $source = $patron['source'];
        
        $result = $ilsDriver->getMyTransactions($patron);
        
        $errors = $notifications = [];
        
        foreach ($result as $current) {
            
            if (isset($current['dueStatus']) && $current['dueStatus'] === "overdue") {
                
                // We found an overdue .. show warning
                
                $notification = [
                    'clazz' => $this->newNotifClass,
                    'message' => $this->translate('notif_you_have_overdues'),
                    'href' => '/MyResearch/CheckedOut#' . $source
                ];
                
                array_push($notifications, $notification);
                
                break;
            }
        }
        
        return [
            'errors' => $errors,
            'notifications' => $notifications
        ];
    }

    /**
     * Translate a string if a translator is available.
     *
     * @param string $msg
     *            Message to translate
     * @param array $tokens
     *            Tokens to inject into the translated string
     * @param string $default
     *            Default value to use if no translation is found (null
     *            for no default).
     *            
     * @return string
     */
    protected function translate($msg, $tokens = [], $default = null)
    {
        return $this->viewModel->plugin('translate')->__invoke($msg, $tokens, $default);
    }
}