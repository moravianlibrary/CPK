<?php
/**
 * Table Definition for Email Types
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

use VuFind\Db\Table\Gateway, Zend\Config\Config;

/**
 * Class for DB table email_types.
 *
 * It also serves as enumerator with validity assertion.
 *
 * You can add here another constant after inserting
 * new rows into "email_types" table.
 *
 * Value of new constant has to be equal with the key
 * specified in the DB table row.
 *
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
class EmailTypes extends Gateway
{
    // Append here another types

    const IDP_NO_EPPN = 'idp_no_eppn';

    const ILS_API_NOT_AVAILABLE = 'ils_api_not_available';

    /**
     *
     * @var \Zend\Config\Config
     */
    protected $config;

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
        $this->table = 'email_types';
        $this->rowClass = 'CPK\Db\Row\EmailTypes';
        parent::__construct($this->table, $this->rowClass);
    }

    /*
     * Do not change the following
     */

    private static $constCache = null;

    /**
     * Throws an exception if there is provided unknown email type.
     *
     * @param string $emailType
     * @throws \Exception
     */
    public static function assertValid($emailType) {

        if (self::$constCache === null) {
            $constants = (new \ReflectionClass(get_called_class()))->getConstants();

            self::$constCache = array_values($constants);
        }

        if (! in_array($emailType, self::$constCache)) {
            throw new \Exception('Email type invalid');
        }
    }
}