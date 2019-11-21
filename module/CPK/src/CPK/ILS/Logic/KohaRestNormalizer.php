<?php
/**
 * Data normalizer for Koha REST API ILS driver
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2019.
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
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace CPK\ILS\Logic;

use \VuFind\Date\Converter as DateConverter;

class KohaRestNormalizer
{
    /**
     * Date converter object
     *
     * @var DateConverter
     */
    protected $dateConverter;

    /**
     * KohaRestNormalizer constructor.
     *
     * @param DateConverter $converter
     */
    public function __construct(DateConverter $converter)
    {
        $this->dateConverter = $converter;
    }

    /**
     * normalize - main normalization method
     *
     * @param array $response    JSON decoded result
     * @param string $methodName ILS Driver method
     *
     * @return array normalized response
     */
    public function normalize($response, $methodName)
    {
        switch ($methodName) {
            case 'getMyFines':
                $this->normalizeUserFinesResponse($response);
                break;
            case 'getMyHolds':
                $this->normalizeHoldItemsResponse($response);
                break;
            case 'getMyTransactions':
                $this->normalizeCheckoutsResponse($response);
                break;
        }
        return $response;
    }

    private function normalizeUserFinesResponse(&$response)
    {
        foreach ($response['outstanding_debits']['lines'] as $key => $entry) {
            $entry['date'] = !empty($entry['date'])
                ? $this->normalizeDate($entry['date'])
                : '';
            $entry['amount'] *= 100;
            $entry['amount_outstanding'] *= 100;
            $entry['description'] = trim($entry['description']);

            $response['outstanding_debits']['lines'][$key] = $entry;
        }
    }

    private function normalizeHoldItemsResponse(&$response) {
        foreach ($response as $key => $entry) {
            $entry['hold_date'] = !empty($entry['hold_date'])
                ? $this->normalizeDate($entry['hold_date'])
                : '';
            $entry['expiration_date'] = !empty($entry['expiration_date'])
                ? $this->normalizeDate($entry['expiration_date'])
                : '';

            $response[$key] = $entry;
        }
    }

    private function normalizeCheckoutsResponse(&$response)
    {
        foreach ($response as $key => $entry) {
            $entry['item_id'] = $entry['item_id'] ?? null;
            $entry['due_date'] = !empty($entry['due_date'])
                ? $this->normalizeDate($entry['due_date'])
                : '';

            //check if overdue
            $today_time = strtotime(date('Y-m-d'));
            $expire_time = strtotime(str_replace(' ', '', $entry['due_date']));
            $entry['due_status'] = ($expire_time < $today_time) ? 'overdue' : false;

            $response[$key] = $entry;
        }
    }

    public function normalizeDate($date, $with_time = false)
    {
        $create_format = $with_time ? 'c': 'Y-m-d';
        return $this->dateConverter->convertToDisplayDate($create_format, $date);
    }
}