<?php
/**
 * Table Definition for Koha API tokens
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
 * @author   Bohdan Inhliziian <inhliziian@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Db\Table;

use Zend\Config\Config;

class KohaTokens extends Gateway
{
    public function __construct(Config $config)
    {
        parent::__construct('koha_tokens');
    }

    public function createAccessToken($source, $tokenData)
    {
        $timestamp = date('Y-m-d H:i:s');
        $token = [
            'source' => $source,
            'access_token' => $tokenData['access_token'],
            'token_type' => $tokenData['token_type'],
            'timestamp_expiration' => date(
                'Y-m-d H:i:s',
                strtotime("+" . $tokenData['expires_in'] . " second", strtotime($timestamp))
            ),
        ];

        $this->getDbTable($this->table)->insert($token);

        // Now commit whole transaction
        $this->getDbConnection()->commit();

        return $token;
    }

    public function renewAccessToken($source, $tokenData)
    {
        $timestamp = date('Y-m-d H:i:s');
        $token = [
            'access_token' => $tokenData['access_token'],
            'token_type' => $tokenData['token_type'],
            'timestamp_created' => $timestamp,
            'timestamp_expiration' => date(
                'Y-m-d H:i:s',
                strtotime("+" . $tokenData['expires_in'] . " second", strtotime($timestamp))
            ),
        ];

        // This will prevent autocommit to Db
        $this->getDbTable($this->table)->update($token, ['source' => $source]);

        return $token;
    }

    public function getAccessToken($source)
    {
        $data = $this->getDbTable($this->table)->select(['source' => $source])->toArray();
        if (!$data || count($data) == 0) {
            return false;
        }

        return $data[0];
    }
}