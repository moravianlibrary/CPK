<?php
/**
 * Table Definition for Notifications
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2015-2016.
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
 * @package  Db_Table
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Db\Table;

use CPK\Db\Table\Gateway, Zend\Config\Config, Zend\Db\Sql\Select;
use CPK\Db\Row\User as UserRow;

class Notifications extends Gateway
{

    /**
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     *
     * @var NotificationTypes
     */
    protected $notificationTypesTable;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config
     *            VuFind configuration
     *
     * @return void
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->table = 'notifications';
        $this->rowClass = 'CPK\Db\Row\Notifications';
        parent::__construct($this->table, $this->rowClass);
    }

    /**
     * Creates new Notifications Rows with scope to specified UserCard by $cat_username.
     *
     * It just overwrites existing Notifications which belongs to User Card, if found any.
     *
     * The $notificationsParsed must have keys, which can be found within the constants of NotificationTypes.
     *
     * @param string $cat_username
     * @param array $notificationsParsed
     * @return array \CPK\Db\Row\Notifications
     */
    public function createUserCardNotificationsRows($cat_username, array $notificationsParsed)
    {
        $notificationTypesTable = $this->getNotificationTypesTable();

        $returnArray = [];

        $userCardId = $this->getUserCardId($cat_username);

        $timestampNow = date('Y-m-d H:i:s');

        foreach ($notificationsParsed as $notificationType => $notificationDetails) {

            $notificationTypeId = $notificationTypesTable->getNotificationTypeId($notificationType);

            $notificationShows = $notificationDetails['shows'];

            $exists = $this->select([
                'user_card' => $userCardId,
                'type' => $notificationTypeId
            ]);

            if (count($exists)) {
                $row = $exists->current();
            } else {
                $row = $this->createRow();
            }

            $row->user_card = $userCardId;
            $row->type = $notificationTypeId;
            $row->shows = ($notificationShows)? 1 : 0;
            $row->read = 0;
            $row->last_fetched = $timestampNow;

            if (isset($notificationDetails['hash'])) {

                $notificationHash = $notificationDetails['hash'];

                if (strlen($notificationHash) > 32)
                    $notificationHash = substr($notificationHash, 0, 32);

                $row->control_hash_md5 = $notificationHash;
            }

            $row->save();

            $returnArray[$notificationType] = $row;
        }

        return $returnArray;
    }

    /**
     * Creates new Notifications Rows with scope to specified User.
     *
     * It just overwrites existing Notifications which belongs to User, if found any.
     *
     * The $notificationsParsed must have keys, which can be found within the constants of NotificationTypes.
     *
     * @param UserRow $user
     * @param array $notificationsParsed
     * @return array \CPK\Db\Row\Notifications
     */
    public function createUserNotificationsRows(UserRow $user, array $notificationsParsed)
    {
        $notificationTypesTable = $this->getNotificationTypesTable();

        $returnArray = [];

        $userId = $user->id;

        $timestampNow = date('Y-m-d H:i:s');

        foreach ($notificationsParsed as $notificationType => $notificationShows) {

            $notificationTypeId = $notificationTypesTable->getNotificationTypeId($notificationType);

            $exists = $this->select([
                'user' => $userId,
                'type' => $notificationTypeId
            ]);

            if (count($exists)) {
                $row = $exists->current();
            } else {
                $row = $this->createRow();
            }

            $row->user = $userId;
            $row->type = $notificationTypeId;
            $row->shows = ($notificationShows)? 1 : 0;
            $row->read = 0;
            $row->last_fetched = $timestampNow;

            $row->save();

            $returnArray[$notificationType] = $row;
        }

        return $returnArray;
    }

    /**
     * Retrieves Notifications of provided UserCard cat_username.
     *
     * @param string $cat_username
     *
     * @return \CPK\Db\Row\Notifications[]
     */
    public function getNotificationsRowsFromUserCardUsername($cat_username)
    {
        $userCardId = $this->getUserCardId($cat_username);

        return $this->getNotificationsRowsFrom('user_card', $userCardId);
    }

    /**
     * Retrieves the Notifications Row for a certain user of a specified notification type
     *
     * @param string $userCardId
     * @param string $notificationType
     * @return \CPK\Db\Row\Notifications
     */
    public function getNotificationsRowFromUserCardUsername($userCardId, $notificationType)
    {
        $notificationTypeId = $this->getNotificationTypesTable()->getNotificationTypeId($notificationType);

        return $this->select([
            'user_card' => $userCardId,
            'type' => $notificationTypeId
        ])->current();
    }

    /**
     * Retrives Notifications of provided User.
     *
     * @param UserRow $user
     * @return \CPK\Db\Row\Notifications[]
     */
    public function getNotificationsRowsFromUser(UserRow $user)
    {
        $userId = $user->id;

        return $this->getNotificationsRowsFrom('user', $userId);
    }

    /**
     * Retrieves the Notifications Row for a certain user of a specified notification type
     *
     * @param UserRow $user
     * @param string $notificationType
     * @return \CPK\Db\Row\Notifications
     */
    public function getNotificationsRowFromUser(UserRow $user, $notificationType)
    {
        $notificationTypeId = $this->getNotificationTypesTable()->getNotificationTypeId($notificationType);

        return $this->select([
            'user' => $user->id,
            'type' => $notificationTypeId
        ])->current();
    }

    /**
     * Gets the array of Notifications Rows matching the value within the column.
     *
     * This method probably won't work as expected when column is one of these:
     *
     * [ 'type', 'last_fetched', 'shows', 'read' ]
     *
     * @param string $column
     * @param string $value
     * @return \CPK\Db\Row\Notifications[]
     */
    protected function getNotificationsRowsFrom($column, $value)
    {
        $notificationTypesTable = $this->getNotificationTypesTable();

        $returnArray = [];

        $rows = $this->select([
            $column => $value
        ]);

        foreach ($rows as $row) {

            $notificationKey = $notificationTypesTable->getNotificationTypeFromId($row->type);
            $returnArray[$notificationKey] = $row;
        }

        return $returnArray;
    }

    /**
     * Retrieves an id of UserCard row where the provided cat_username matches
     *
     * @param string $cat_username
     *
     * @return string $userCardId
     */
    protected function getUserCardId($cat_username)
    {
        $select = new Select();
        $select->columns([
            'id'
        ])
            ->from('user_card')
            ->where([
            'cat_username' => $cat_username
        ]);

        $userCard = $this->executeAnyZendSQLSelect($select)->current();

        return $userCard['id'];
    }

    protected function getNotificationTypesTable()
    {
        if ($this->notificationTypesTable === null)
            $this->notificationTypesTable = $this->getDbTable('notification_types');

        return $this->notificationTypesTable;
    }
}