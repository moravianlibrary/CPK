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
     * C'tor
     *
     * @param \Zend\View\Renderer\RendererInterface $viewModel
     */
    public function __construct(\Zend\View\Renderer\RendererInterface $viewModel, \VuFind\ILS\Connection $ils)
    {
        $this->viewModel = $viewModel;
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

        $notifs = [];
        if (!empty($profile['blocks'])) {
            $notificationType = NotificationTypes::BLOCKS;
            $notifs[] = [
                'message' => $this->translate("notif_you_have_$notificationType"),
                'href' => NotificationTypes::getNotificationTypeClickUrl($notificationType, $source),
                'type' => $notificationType,
                'clazz' => 'warning',
            ];
        }

        $expire = date_create_from_format('d. m. Y', $profile['expire']);
        $dateDiff = date_diff($expire, date_create());
        $invalidRegistration = ($dateDiff->days < 31 && $dateDiff->invert != 0)
            || ($dateDiff->invert == 0 && $dateDiff->days > 0);

        if ($invalidRegistration) {
            $notificationType = NotificationTypes::EXPIRED_REGISTRATION;
            $notifs[] = [
                'message' => $this->translate("notif_you_have_$notificationType"),
                'href' => NotificationTypes::getNotificationTypeClickUrl($notificationType, $source),
                'type' => $notificationType,
                'clazz' => 'warning',
            ];
        }

        return $notifs;
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