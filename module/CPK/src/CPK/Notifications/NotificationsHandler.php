<?php

/**
 * Notifications backend handler
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2016.
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

use CPK\Db\Table\Notifications, VuFind\Exception\ILS as ILSException, CPK\Db\Row\User;
use CPK\Db\Table\NotificationTypes;
use CPK\Db\Row\Notifications as NotificationsRow;

/**
 * This class handles getting User's notifications either on UserCard scope,
 * or on User scope.
 *
 * There can be set "read" flag to a notification, typically when user clicks
 * the notification itself in the UI.
 *
 * Also if the notification is not API relevant, there can be set the "shows"
 * flag of notification determining whether to show the notification to user
 * or not.
 *
 * On the other hand, when the notification is API relevant, it is automatically
 * being refetched every NotificationsHandler::REFETCH_INTERVAL_SECS seconds.
 *
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
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
     * Temporary Notifications Rows related to all non-Dummy User Cards
     *
     * @var NotificationsRow[]
     */
    protected $userCardNotificationsRows = [];

    /**
     * Temporary Notifications Rows related to User
     *
     * @var NotificationsRow[]
     */
    protected $userNotificationsRows = [];

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

    /**
     * Retrieves all notifications related to User Card specified with provided $cat_username
     *
     * @param string $cat_username
     * @return string[][][]
     */
    public function getUserCardNotifications($cat_username)
    {
        $source = explode('.', $cat_username)[0];

        $this->getUserCardApiRelevantNotifications($cat_username, $source);

        $this->getUserCardApiNonrelevantNotifications($cat_username, $source);

        return $this->prepareNotificationsRowsForOutput($this->userCardNotificationsRows, $source);
    }

    /**
     * Retrieves all notifications related to provided User
     *
     * @param string $cat_username
     * @return string[][][]
     */
    public function getUserNotifications(User $user)
    {
        $this->getUserApiRelevantNotifications($user);

        $this->getUserApiNonrelevantNotifications($user);

        return $this->prepareNotificationsRowsForOutput($this->userNotificationsRows);
    }

    /**
     * Sets an notification type as read for specific user
     *
     * @param User $user
     * @param string $notificationType
     * @param string $source
     * @return boolean $success
     */
    public function setUserCardNotificationRead(User $user, $notificationType, $source)
    {
        NotificationTypes::assertValid($notificationType);

        foreach ($user->getLibraryCards() as $libCard) {
            if ($libCard->home_library === $source) {

                $notificationRow = $this->notificationsTable->getNotificationsRowFromUserCardUsername($libCard->id, $notificationType);

                if ($notificationRow instanceof NotificationsRow)
                    $this->userCardNotificationsRows[$notificationType] = $notificationRow;

                break;
            }
        }

        if (isset($this->userCardNotificationsRows[$notificationType])) {

            $this->userCardNotificationsRows[$notificationType]->read = true;
            $this->userCardNotificationsRows[$notificationType]->save();

            return true;
        }

        return false;
    }

    /**
     * Sets an notification type as read for specific user
     *
     * @param User $user
     * @param string $notificationType
     * @return boolean $success
     */
    public function setUserNotificationRead(User $user, $notificationType)
    {
        $notificationRow = $this->notificationsTable->getNotificationsRowFromUser($user, $notificationType);

        if ($notificationRow instanceof NotificationsRow)
            $this->userNotificationsRows[$notificationType] = $notificationRow;

        if (isset($this->userNotificationsRows[$notificationType])) {

            $this->userNotificationsRows[$notificationType]->read = true;
            $this->userNotificationsRows[$notificationType]->save();

            return true;
        }

        return false;
    }

    /**
     * Sets a "shows" flag to an User scoped notification.
     *
     * @param User $user
     * @param string $notificationType
     * @param boolean $showNotification
     * @throws \Exception
     */
    public function setUserNotificationShows(User $user, $notificationType, $showNotification)
    {
        $apiNonRelevantTypes = NotificationTypes::getAllApiNonrelevantTypes();

        if (array_search($notificationType, $apiNonRelevantTypes) === false) {
            throw new \Exception($notificationType . ' is not recognized API nonrelevant NotificationType');
        }

        $notificationsRow = $this->notificationsTable->getNotificationsRowFromUser($user, $notificationType);

        if (! $notificationsRow instanceof NotificationsRow) {

            $notificationsParsed = [
                $notificationType => $showNotification
            ];

            $notificationsRow = $this->notificationsTable->createUserNotificationsRows($user, $notificationsParsed)[$notificationType];
        } else {

            if (boolval($showNotification) !== boolval($notificationsRow->shows)) {
                $notificationsRow->shows = ($showNotification) ? 1 : 0;
                $notificationsRow->save();
            }
        }

        $this->userNotificationsRows[$notificationType] = $notificationsRow;
    }

    /**
     * Gets all UserCard API relevant Notifications identified by user_card's cat_username
     *
     * @param string $cat_username
     */
    protected function getUserCardApiRelevantNotifications($cat_username, $source)
    {
        $userCardNotifications = $this->notificationsTable->getNotificationsRowsFromUserCardUsername($cat_username);

        $this->userCardNotificationsRows = array_merge($this->userCardNotificationsRows, $userCardNotifications);

        $hasAllApiRelevantKeys = $this->hasAllApiRelevantKeys($this->userCardNotificationsRows);

        if (! $hasAllApiRelevantKeys) {

            // API relevant Notifications have never been fetched before
            $this->createNewUserCardApiRelevantNotifications($cat_username, $source);
        } else {

            // They have been fetched some time ago, check if it was more than REFETCH_INTERVAL_SECS
            $this->fetchUserCardApiRelevantNotifications($cat_username, $source);
        }
    }

    /**
     * Gets all UserCard API nonrelevant Notifications identified by user_card's cat_username
     *
     * @param string $cat_username
     */
    protected function getUserCardApiNonrelevantNotifications($cat_username, $source)
    {
        /*
         * Here should come the logic for getting the UserCard scoped Notifications which
         * are not API relevant (doesn't need ILS API to work)
         *
         * The result should be appended to array of Notifications Row in this class variable:
         * $this->userCardNotificationsRows
         */
    }

    /**
     * Gets all User API relevant Notifications identified by provided User Row
     *
     * @param User $user
     */
    protected function getUserApiRelevantNotifications(User $user)
    {
        /*
         * Here should come the logic for getting the User scoped Notifications which
         * are API relevant (does need ILS API to work)
         *
         * The result should be appended to array of Notifications Row in this class variable:
         * $this->userNotificationsRows
         */
    }

    /**
     * Gets all User API nonrelevant Notifications identified by provided User Row
     *
     * @param User $user
     */
    protected function getUserApiNonrelevantNotifications(User $user)
    {
        $notificationsRows = $this->notificationsTable->getNotificationsRowsFromUser($user);

        $this->userNotificationsRows = array_merge($this->userNotificationsRows, $notificationsRows);
    }

    /**
     * Creates new API relevant Notifications Rows in DB
     *
     * @param string $cat_username
     * @param string $source
     */
    protected function createNewUserCardApiRelevantNotifications($cat_username, $source)
    {
        $hasBlocks = $this->fetchHasBlocks($cat_username, $source);

        $hasFines = $this->fetchHasFines($cat_username, $source);

        $hasOverdues = $this->fetchHasOverdues($cat_username, $source);

        $userNotifications = [
            NotificationTypes::BLOCKS => $hasBlocks,
            NotificationTypes::FINES => $hasFines,
            NotificationTypes::OVERDUES => $hasOverdues
        ];

        $this->userCardNotificationsRows = $this->notificationsTable->createUserCardNotificationsRows($cat_username, $userNotifications);
    }

    /**
     * Retrieves new Notifications for a user.
     *
     * It also sets particular notifications as unread only if there was an notification active before & now it's not active anymore.
     *
     * Note that it does not update any notifications which are not fetched via API.
     *
     * @param string $notificationsType
     * @param string $cat_username
     * @param string $source
     */
    protected function actualizeUserCardApiRelevantNotificationsRow($notificationsType, $cat_username, $source)
    {
        $notificationsRow = &$this->userCardNotificationsRows[$notificationsType];

        switch ($notificationsType) {

            case NotificationTypes::BLOCKS:

                $showNotification = $this->fetchHasBlocks($cat_username, $source);
                break;

            case NotificationTypes::FINES:

                $showNotification = $this->fetchHasFines($cat_username, $source);
                break;

            case NotificationTypes::OVERDUES:

                $showNotification = $this->fetchHasOverdues($cat_username, $source);
                break;

            default:
                $showNotification = false;
        }

        $wasShownBefore = boolval($notificationsRow->shows);

        $shouldSetUnread = $wasShownBefore && $showNotification === false;

        if ($shouldSetUnread) {
            $notificationsRow->read = false;
        }

        $notificationsRow->shows = ($showNotification) ? 1 : 0;

        $notificationsRow->last_fetched = date('Y-m-d H:i:s');

        $notificationsRow->save();
    }

    /**
     * Checks whether it is needed to fetch again the notifications.
     *
     * It also fetches those if neccessarry.
     *
     * @param string $cat_username
     * @param string $source
     */
    protected function fetchUserCardApiRelevantNotifications($cat_username, $source)
    {
        foreach ($this->userCardNotificationsRows as $notificationsType => $notificationsRow) {
            $lastFetched = strtotime($notificationsRow->last_fetched);

            $shouldFetchAgain = $lastFetched + self::REFETCH_INTERVAL_SECS <= time();

            if ($shouldFetchAgain) {
                $this->actualizeUserCardApiRelevantNotificationsRow($notificationsType, $cat_username, $source);
            }
        }
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
     * Checks if the provided array object of NotificationsRow has all the keys
     * matching all API relevant keys.
     *
     * @param array $notifications
     * @return boolean $hasAllApiRelevantKeys
     */
    protected function hasAllApiRelevantKeys(array $notifications)
    {
        $apiRelevantKeys = NotificationTypes::getAllApiRelevantTypes();
        $presentApiRelevantKeys = array_intersect(array_keys($notifications), $apiRelevantKeys);

        return empty(array_diff($apiRelevantKeys, $presentApiRelevantKeys));
    }

    /**
     * Checks if the provided array object of NotificationsRow has all the keys
     * matching all API nonrelevant keys.
     *
     * @param array $notifications
     * @return boolean $hasAllApiNonrelevantKeys
     */
    protected function hasAllApiNonrelevantKeys(array $notifications)
    {
        $apiNonrelevantKeys = NotificationTypes::getAllApiNonrelevantTypes();
        $presentApiNonrelevantKeys = array_intersect(array_keys($notifications), $apiNonrelevantKeys);

        return empty(array_diff($apiNonrelevantKeys, $presentApiNonrelevantKeys));
    }

    /**
     * Parses the input array of Notification Rows into on associative array
     * being returned to AngularJS notifications controller for further process.
     *
     * @param \CPK\Db\Row\Notifications[] $notificationsRows
     * @param string $source
     * @return string[][][]
     */
    protected function prepareNotificationsRowsForOutput(array $notificationsRows, $source = null)
    {
        $data['notifications'] = [];
        $data['source'] = $source;

        foreach ($notificationsRows as $notificationType => $notificationRow) {

            if ($notificationRow->shows) {
                $clazz = $this->newNotifClass;

                if (! $notificationRow->read)
                    $clazz .= ' notif-unread';

                $notification = [
                    'clazz' => $clazz,
                    'message' => $this->translate("notif_you_have_$notificationType"),
                    'href' => NotificationTypes::getNotificationTypeClickUrl($notificationType, $source),
                    'type' => $notificationType
                ];

                array_push($data['notifications'], $notification);
            }
        }

        return $data;
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