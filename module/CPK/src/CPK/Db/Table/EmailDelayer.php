<?php
/**
 * Table Definition for Email Delayer
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

use VuFind\Db\Table\Gateway as ParentGateway, Zend\Config\Config;

/**
 * Class for DB table email_delayer
 *
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
class EmailDelayer extends ParentGateway
{
    /**
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     *
     * @var object
     */
    protected $sendAttemptsCounts = [];

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
        $this->table = 'email_delayer';
        $this->rowClass = 'CPK\Db\Row\EmailDelayer';
        parent::__construct($this->table, $this->rowClass);
    }

    /**
     * Retrieves an EmailDelayer Row from email $to & EmailType $emailType
     * and also an EmailTypes Row as the second return value.
     *
     * @param string $to
     * @param string $source
     * @param string $type
     * @return \CPK\Db\Row\EmailDelayer[]|\CPK\Db\Row\EmailTypes[]
     * @throws \Exception
     */
    public function getRow($to, $source, $emailType) {

        $emailTypeRow = $this->getEmailTypeRow($emailType);

        $row = $this->select([
            'email' => $to,
            'source' => $source,
            'type' => $emailTypeRow->id
        ])->current();

        return [ $row, $emailTypeRow ];
    }

    /**
     * Increments attempts count for recipient with specified email type and
     * returns boolean whether the time delay has passed since last email sent.
     *
     * @param string $to
     * @param string $source
     * @param string $type
     * @return boolean
     * @throws \Exception
     */
    public function canSendEmailTypeTo($to, $source, $emailType) {

        list($row, $emailTypeRow) = $this->getRow($to, $source, $emailType);

        if (! $row) {

            $row = $this->createRow();
            $row->email = $to;
            $row->source = $source;
            $row->type = $emailTypeRow->id;
            $row->save();

            $this->sendAttemptsCounts[$to][$source][$emailType] = 1;

            return true;
        }

        // Increment attempts count
        $this->sendAttemptsCounts[$to][$source][$emailType] = ++$row->send_attempts_count;

        // Get the email delay
        $minimalDelay = date_parse_from_format('G:i:s', $emailTypeRow->delay);
        $minimalDelayInSeconds = $minimalDelay['hour'] * 3600 + $minimalDelay['minute'] * 60 + $minimalDelay['second'];

        // FIXME seems like there is no effective PHP's or Zend's way to do the above .. or is it?

        $toRet = false;
        if (strtotime($row->last_sent) + $minimalDelayInSeconds < time()) {

            $row->last_sent = date('Y-m-d G:i:s');
            $toRet = true;
        }

        $row->save();

        return $toRet;
    }

    /**
     * Clears number of attempts to send an type of mail
     *
     * @param unknown $to
     * @param unknown $emailType
     * @return void
     * @throws \Exception
     */
    public function clearAttempts($to, $source, $emailType)
    {
        $row = $this->getRow($to, $source, $emailType)[0];

        if ($row !== false) {
            $row->send_attempts_count = 0;
            $row->save();
        }
    }

    /**
     * Gets the number of tries within an email_delayer row.
     *
     * @param string $to
     * @param string $source
     * @param string $emailType
     * @return int
     * @throws \Exception
     */
    public function getSendAttemptsCount($to, $source, $emailType)
    {
        if (isset($this->sendAttemptsCounts[$to][$source][$emailType]))
            return (int) $this->sendAttemptsCounts[$to][$source][$emailType];
        else {
            $row = $this->getRow($to, $source, $emailType)[0];

            if (!row)
                return 0;
            else
                return (int) $row->send_attempts_count;
        }
    }


    /**
     * Returns the instance of email_type row
     *
     * @param string $emailType
     * @throws \Exception
     */
    protected function getEmailTypeRow($emailType) {

        EmailTypes::assertValid($emailType);

        $found = $this->getDbTable('email_types')->select([
            'key' => $emailType
        ])->current();

        if (! $found) {
            throw new \Exception('Email Type "' . $emailType . '" is missing in "email_types" table');
        }

        return $found;
    }
}