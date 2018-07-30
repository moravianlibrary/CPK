<?php
/**
 * Table Definition for Notification Types
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
 * @package  Db_Table
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Db\Table;

use Zend\Config\Config;

class NotificationTypes extends ConstantsValidator
{

    /**
     * Please see the getAllApiRelevantTypes method after
     * adding another constant defining Notification Type
     */
    const FINES = 'fines';

    const BLOCKS = 'blocks';

    const OVERDUES = 'overdues';

    const USER_DUMMY = 'user_dummy';

    const EXPIRED_REGISTRATION = 'expired_registration';

    /**
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Object holding all notifications types to prevent
     * multiple db querying as the number of notification
     * types is not big
     *
     * @var array
     */
    protected $allTypesCache = null;

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
        $this->table = 'notification_types';
        $this->rowClass = 'CPK\Db\Row\NotificationTypes';
        parent::__construct($this->table, $this->rowClass);
    }

    /**
     * Returns array of all the notification types, which needs API to work
     *
     * @return string[]
     */
    public static function getAllApiRelevantTypes()
    {
        return [
            self::FINES,
            self::BLOCKS,
            self::OVERDUES,
            self::EXPIRED_REGISTRATION
        ];
    }

    /**
     * Returns array of all the notification types, which doesn't use API to work
     *
     * @return string[]
     */
    public static function getAllApiNonrelevantTypes()
    {
        return [
            self::USER_DUMMY
        ];
    }

    /**
     * Retrieves the notification_type row's id.
     *
     * @param string $notificationTypeKey
     * @return string $id
     * @throws \Exception
     */
    public function getNotificationTypeId($notificationTypeKey)
    {
        $this->assertValid($notificationTypeKey);

        if ($this->allTypesCache === null)
            $this->allTypesCache = $this->select()->toArray();

        foreach ($this->allTypesCache as $notificationType) {
            if ($notificationType['key'] === $notificationTypeKey)
                return $notificationType['id'];
        }

        return null;
    }

    /**
     * Retrieves the notification_type row's key.
     *
     * @param string $id
     * @return string $key
     * @throws \Exception
     */
    public function getNotificationTypeFromId($id)
    {
        if ($this->allTypesCache === null)
            $this->allTypesCache = $this->select()->toArray();

        foreach ($this->allTypesCache as $notificationType) {
            if ($notificationType['id'] === $id)
                return $notificationType['key'];
        }

        return null;
    }

    /**
     * Retrieves appropriate URL to redirect user to after he clicks the notification specified by
     * notification type
     *
     * @param string $notificationType
     * @param string $source
     * @return string $onClickUrl
     */
    public static function getNotificationTypeClickUrl($notificationType, $source = null)
    {
        static::assertValid($notificationType);

        $controller = '/MyResearch/';

        switch ($notificationType) {

            case static::EXPIRED_REGISTRATION:
            case static::BLOCKS:
                $action = 'Profile';
                break;

            case static::FINES:
                $action = 'Fines';
                break;

            case static::OVERDUES:
                $action = 'CheckedOut';
                break;

            case static::USER_DUMMY:

                $controller = '/LibraryCards/';
                $action = 'Home?viewModal=help-with-log-in-and-registration';
                break;

        }

        if ($source !== null)
            $action .= '#' . $source;

        return $controller . $action;
    }

    protected static function getInvalidConstantValueMessage()
    {
        return "Notification type not recognized.";
    }
}