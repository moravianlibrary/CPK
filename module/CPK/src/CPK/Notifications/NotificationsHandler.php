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

use CPK\Db\Table\Notifications, VuFind\Exception\ILS as ILSException;

class NotificationsHandler
{

    /**
     * Needed for translation
     *
     * @var \Zend\View\Renderer\RendererInterface
     */
    protected $viewModel;

    /**
     * DB access
     *
     * @var Notifications
     */
    protected $notificationsTable;

    /**
     * ILS Connection
     *
     * @var \VuFind\ILS\Connection
     */
    protected $ils;

    /**
     * Default css class for new notifications
     *
     * @var string
     */
    protected $newNotifClass = 'warning';

    /**
     * Temporary Notifications Row
     *
     * @var \CPK\Db\Row\Notifications
     */
    protected $notificationsRow;

    /**
     * Interval to wait before querying the ILS for notifications again, measured in seconds
     *
     * @var int
     */
    const REFETCH_INTERVAL_SECS = 60 * 15;

    /**
     * C'tor
     *
     * @param \Zend\View\Renderer\RendererInterface $viewModel            
     */
    public function __construct(\Zend\View\Renderer\RendererInterface $viewModel, Notifications $notificationsTable, \VuFind\ILS\Connection $ils)
    {
        $this->viewModel = $viewModel;
        $this->notificationsTable = $notificationsTable;
        $this->ils = $ils;
    }

    public function getUserNotifications($cat_username)
    {
        $source = explode('.', $cat_username)[0];
        
        $this->notificationsRow = $this->notificationsTable->getNotificationsRow($cat_username);
        
        if ($this->notificationsRow === false) {
            
            // Notifications have never been fetched before
            $this->createNewUserNotifications($cat_username, $source);
        } else {
            
            // They have been fetched some time ago, check if it was more than REFETCH_INTERVAL_SECS
            $lastFetched = strtotime($this->notificationsRow->last_fetched);
            
            $shouldFetchAgain = $lastFetched + self::REFETCH_INTERVAL_SECS <= time();
            
            if ($shouldFetchAgain) {
                $this->actualizeUserNotifications($cat_username, $source);
            }
        }
        
        $data['notifications'] = [];
        
        if ($this->notificationsRow->has_blocks) {
            
            $clazz = $this->newNotifClass;
            
            if (! $this->notificationsRow->blocks_read) {
                $clazz .= ' notif-unread';
            }
            
            $notification = [
                'clazz' => $clazz,
                'message' => $this->translate('notif_you_have_blocks'),
                'href' => '/MyResearch/Profile#' . $source
            ];
            
            array_push($data['notifications'], $notification);
        }
        
        if ($this->notificationsRow->has_fines) {
            
            $clazz = $this->newNotifClass;
            
            if (! $this->notificationsRow->fines_read) {
                $clazz .= ' notif-unread';
            }
            
            $notification = [
                'clazz' => $clazz,
                'message' => $this->translate('notif_you_have_fines'),
                'href' => '/MyResearch/Fines#' . $source
            ];
            
            array_push($data['notifications'], $notification);
        }
        
        if ($this->notificationsRow->has_overdues) {
            
            $clazz = $this->newNotifClass;
            
            if (! $this->notificationsRow->overdues_read) {
                $clazz .= ' notif-unread';
            }
            
            $notification = [
                'clazz' => $clazz,
                'message' => $this->translate('notif_you_have_overdues'),
                'href' => '/MyResearch/CheckedOut#' . $source
            ];
            
            array_push($data['notifications'], $notification);
        }
        
        if (count($data['notifications']) === 0) {
            // No notifications added ..
            $data['notifications'] = array(
                [
                    'clazz' => 'default',
                    'message' => $this->translate('without_notifications')
                ]
            );
        }
        
        return $data;
    }

    /**
     * Sets an notification type as read for specific user
     *
     * @param \CPK\Db\Row\User $user            
     * @param string $notificationType            
     */
    public function setUserNotificationRead(\CPK\Db\Row\User $user, $notificationType)
    {
        list ($notificationType, $source) = explode('#', $notificationType);
        
        foreach ($user->getLibraryCards() as $libCard) {
            if ($libCard->home_library === $source) {
                $this->notificationsRow = $this->notificationsTable->getNotificationsRow($libCard->id, true);
                break;
            }
        }
        
        if (isset($this->notificationsRow)) {
            
            switch ($notificationType) {
                
                case 'Fines':
                    $this->notificationsRow->fines_read = true;
                    break;
                
                case 'Profile':
                    $this->notificationsRow->blocks_read = true;
                    break;
                
                case 'CheckedOut':
                    $this->notificationsRow->overdues_read = true;
                    break;
            }
            
            $this->notificationsRow->save();
        }
    }

    /**
     * Creates new Notifications Row in DB
     *
     * @param string $cat_username            
     * @param string $source            
     */
    protected function createNewUserNotifications($cat_username, $source)
    {
        $hasBlocks = $this->fetchHasBlocks($cat_username, $source);
        
        $hasFines = $this->fetchHasFines($cat_username, $source);
        
        $hasOverdues = $this->fetchHasOverdues($cat_username, $source);
        
        $this->notificationsRow = $this->notificationsTable->createNotificationsRow($cat_username, $hasBlocks, $hasFines, $hasOverdues);
    }

    /**
     * Retrieves new Notifications for a user.
     *
     * It also sets particular notifications as unread only if there was an notification active before & now it's not active anymore ..
     *
     * @param string $cat_username            
     * @param string $source            
     */
    protected function actualizeUserNotifications($cat_username, $source)
    {
        $hasBlocks = $this->fetchHasBlocks($cat_username, $source);
        
        $hasFines = $this->fetchHasFines($cat_username, $source);
        
        $hasOverdues = $this->fetchHasOverdues($cat_username, $source);
        
        if ($this->notificationsRow->has_blocks && $hasBlocks === false) {
            $this->notificationsRow->blocks_read = false;
        }
        
        if ($this->notificationsRow->has_fines && $hasFines === false) {
            $this->notificationsRow->fines_read = false;
        }
        
        if ($this->notificationsRow->has_overdues && $hasOverdues === false) {
            $this->notificationsRow->overdues_read = false;
        }
        
        $this->notificationsRow->has_blocks = $hasBlocks;
        $this->notificationsRow->has_fines = $hasFines;
        $this->notificationsRow->has_overdues = $hasOverdues;
        
        $this->notificationsRow->last_fetched = date('Y-m-d H:i:s');
        
        $this->notificationsRow->save();
    }

    /**
     * Returns notifications of user's blocks.
     *
     * @param string $cat_username            
     * @param string $source            
     */
    protected function fetchHasBlocks($cat_username, $source)
    {
        $profile = $this->ils->getMyProfile([
            'cat_username' => $cat_username,
            'id' => $cat_username
        ]);
        
        if (is_array($profile) && count($profile) === 0) {
            
            throw new ILSException('Error fetching profile in "' . $source . '": ' . $this->translate('profile_fetch_problem'));
        } else 
            if (count($profile['blocks']))
                return true;
        
        return false;
    }

    /**
     * Returns notifications of user's fines.
     *
     * @param string $cat_username            
     * @param string $source            
     */
    protected function fetchHasFines($cat_username, $source)
    {
        $fines = $this->ils->getMyFines([
            'cat_username' => $cat_username,
            'id' => $cat_username
        ]);
        
        unset($fines['source']);
        
        if (count($fines))
            return true;
        
        return false;
    }

    /**
     * Returns notifications of user's overdues.
     *
     * @param string $cat_username            
     * @param string $source            
     */
    protected function fetchHasOverdues($cat_username, $source)
    {
        $result = $this->ils->getMyTransactions([
            'cat_username' => $cat_username,
            'id' => $cat_username
        ]);
        
        foreach ($result as $current) {
            
            if (isset($current['dueStatus']) && $current['dueStatus'] === "overdue")
                return true;
        }
        
        return false;
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