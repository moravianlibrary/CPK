<?php
/**
 * Table Definition for Notifications
 *
 * PHP version 5
 *
 * Copyright (C) Moravian Library 2015.
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

use VuFind\Db\Table\Gateway, Zend\Config\Config, Zend\Db\Sql\Select;

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
     * Creates new Notifications Row
     *
     * @param string $cat_username
     *
     * @return array \CPK\Db\Row\Notifications
     */
    public function createNotificationsRows($cat_username, array $notificationsParsed)
    {
        if ($this->notificationTypesTable === null)
            $this->notificationTypesTable = $this->getDbTable('notification_types');

        $returnArray = [];

        $userCardId = $this->getUserCardId($cat_username);

        $timestampNow = date('Y-m-d H:i:s');

        foreach ($notificationsParsed as $notificationTypeKey => $notificationShows) {
            $row = $this->createRow();

            $row->user = $userCardId;
            $row->type = $this->notificationTypesTable->getNotificationTypeId($notificationTypeKey);
            $row->shows = $notificationShows;
            $row->last_fetched = $timestampNow;

            $row->save();

            $returnArray[$notificationTypeKey] = $row;
        }

        return $returnArray;
    }

    /**
     * Retrieves Notifications for a user.
     *
     * @param string $cat_username
     *
     * @return array \CPK\Db\Row\Notifications
     */
    public function getNotificationsRows($cat_username)
    {
        if ($this->notificationTypesTable === null)
            $this->notificationTypesTable = $this->getDbTable('notification_types');

        $rows = $returnArray = [];

        $rows = $this->select([
            'user' => $this->getUserCardId($cat_username)
        ]);

        foreach ($rows as $row) {

            $notificationKey = $this->notificationTypesTable->getNotificationTypeFromId($row->type);

            $returnArray[$notificationKey] = $row;
        }

        return $returnArray;
    }

    /**
     * Retrieves the Notifications Row for a certain user of a specified notification type
     *
     * @param string $userCardId
     * @param string $notificationType
     * @return \CPK\Db\Row\Notifications
     */
    public function getNotificationsRow($userCardId, $notificationType)
    {
        if ($this->notificationTypesTable === null)
            $this->notificationTypesTable = $this->getDbTable('notification_types');

        return $this->select([
            'user' => $userCardId,
            'type' => $this->notificationTypesTable->getNotificationTypeId($notificationType)
        ])
            ->current();
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

    /**
     * Executes any Select
     *
     * @param Select $select
     *
     * @return Result $result
     */
    protected function executeAnyZendSQLSelect(Select $select)
    {
        $statement = $this->sql->prepareStatementForSqlObject($select);
        return $statement->execute();
    }
}