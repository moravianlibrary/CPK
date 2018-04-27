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

        $notifications = [];
        try {
            $notifications['notifications'] = array_merge(
                $this->fetchBlocksDetails($cat_username, $source),
                $this->fetchFinesDetails($cat_username, $source),
                $this->fetchOverduesDetails($cat_username, $source)
            );
        } catch (\Exception $ex) {
            // FIXME: error handling
        }

        return $notifications;
    }

    /**
     * Retrieves all notifications related to provided User
     *
     * @param string $cat_username
     * @return string[][][]
     */
    public function getUserNotifications(User $user)
    {
        $notifications = [ 'notifications' => [] ];
        $isDummy = $user->home_library === 'Dummy';
        if ($isDummy) {
            $notifications['notifications'][] = [
                'message' => $this->translate("notif_you_have_" . NotificationTypes::USER_DUMMY),
                'href' => '/LibraryCards/Home?viewModal=help-with-log-in-and-registration',
                'type' => NotificationTypes::USER_DUMMY,
                'clazz' => 'warning',
            ];
        }
        return $notifications;
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
        $blocksDetails = $this->fetchBlocksDetails($cat_username, $source);

        $finesDetails = $this->fetchFinesDetails($cat_username, $source);

        $overduesDetails = $this->fetchOverduesDetails($cat_username, $source);

        $userNotifications = [
            NotificationTypes::BLOCKS => $blocksDetails,
            NotificationTypes::FINES => $finesDetails,
            NotificationTypes::OVERDUES => $overduesDetails
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

                $notificationDetails = $this->fetchBlocksDetails($cat_username, $source);
                break;

            case NotificationTypes::FINES:

                $notificationDetails = $this->fetchFinesDetails($cat_username, $source);
                break;

            case NotificationTypes::OVERDUES:

                $notificationDetails = $this->fetchOverduesDetails($cat_username, $source);
                break;

            default:
                return;
        }

        $wasShownBefore = boolval($notificationsRow->shows);

        $notificationShows = $notificationDetails['shows'];

        $shouldSetUnread = $wasShownBefore && $notificationShows === false;

        if (! $shouldSetUnread) {
            $controlHash = $notificationDetails['hash'];

            $shouldSetUnread = ($controlHash !== $notificationsRow->control_hash_md5);

            $notificationsRow->control_hash_md5 = $controlHash;
        }

        if ($shouldSetUnread) {
            $notificationsRow->read = 0;
        }

        $notificationsRow->shows = ($notificationShows) ? 1 : 0;

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
    protected function fetchBlocksDetails($cat_username, $source)
    {
        $profile = $this->ils->getMyProfile([
            'cat_username' => $cat_username,
            'id' => $cat_username
        ]);

        if (is_array($profile) && count($profile) === 0) {
            throw new ILSException('Error fetching profile in "' . $source . '": ' .
                $this->translate('profile_fetch_problem'));
        }

        if (!empty($profile['blocks'])) {
            $notificationType = NotificationTypes::BLOCKS;
            return [[
                'message' => $this->translate("notif_you_have_$notificationType"),
                'href' => NotificationTypes::getNotificationTypeClickUrl($notificationType, $source),
                'type' => $notificationType,
                'clazz' => 'warning',
            ]];
        }

        return [];
    }

    /**
     * Returns notifications of user's fines.
     *
     * @param string $cat_username
     * @param string $source
     */
    protected function fetchFinesDetails($cat_username, $source)
    {
        $fines = $this->ils->getMyFines([
            'cat_username' => $cat_username,
            'id' => $cat_username
        ]);

        unset($fines['source']);

        if ((!empty($fines)) && (end($fines)['balance'] < 0)) {
            $notificationType = NotificationTypes::FINES;
            return [[
                'message' => $this->translate("notif_you_have_$notificationType"),
                'href' => NotificationTypes::getNotificationTypeClickUrl($notificationType, $source),
                'type' => $notificationType,
                'clazz' => 'warning',
            ]];
        }

        return [];
    }

    /**
     * Returns notifications of user's overdues.
     *
     * @param string $cat_username
     * @param string $source
     */
    protected function fetchOverduesDetails($cat_username, $source)
    {
        $result = $this->ils->getMyTransactions([
            'cat_username' => $cat_username,
            'id' => $cat_username
        ]);

        $overduedIds = [];

        foreach ($result as $current) {
            if (isset($current['dueStatus']) && $current['dueStatus'] === "overdue")
                array_push($overduedIds, $current['id']);
        }

        if (!empty($overduedIds)) {
            $notificationType = NotificationTypes::OVERDUES;
            return [[
                'message' => $this->translate("notif_you_have_$notificationType"),
                'href' => NotificationTypes::getNotificationTypeClickUrl($notificationType, $source),
                'type' => $notificationType,
                'clazz' => 'warning',
            ]];
        }

        return [];
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