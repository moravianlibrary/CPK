<?php

namespace CPK\ILS\Logic;

use \VuFind\Date\Converter as DateConverter;

class KohaRestNormalizer
{
        protected $dateConverter;

    public function __construct(DateConverter $converter)
    {
        $this->dateConverter = $converter;
    }

    public function normalize($response, $methodName) {
        switch ($methodName) {
            case 'getMyProfile':
                $this->normalizeUserProfileResponse($response);
                break;
            case 'getMyFines':
                $this->normalizeUserFinesResponse($response);
                break;
            case 'getMyHolds':
                $this->normalizeHoldItemsResponse($response);
                break;
            case 'getMyTransactions':
                $this->normalizeCheckoutsResponse($response);
                break;
            case 'renewMyItems':
                $this->normalizeRenewItem($response);
                break;
        }

        return $response;
    }

    public function normalizeUserProfileResponse(&$response) {
        $response['expiry_date'] = !empty($response['expiry_date'])
            ? $this->dateConverter->convertToDisplayDate(
                'Y-m-d', $response['expiry_date']
            ) : '';
    }

    public function normalizeUserFinesResponse(&$response) {
        foreach ($response['outstanding_debits']['lines'] as $key => $entry) {
            if ($entry['account_type'] == 'Pay'
                || (int)$entry['amount_outstanding'] == 0
            ) {
                unset($response['outstanding_debits']['lines'][$key]);
                continue;
            }

            $entry['date'] = !empty($entry['date'])
                ? $this->normalizeDate($entry['date'])
                : '';

            $entry['amount'] *= 100;
            $entry['amount_outstanding'] *= 100;
            $entry['description'] = trim($entry['description']);

            $response['outstanding_debits']['lines'][$key] = $entry;
        }
    }

    public function normalizeHoldItemsResponse(&$response) {
        foreach ($response as $key => $entry) {
            $entry['biblionumber'] = isset($entry['biblionumber']) ? $entry['biblionumber'] : null; //TODO deal with 'KOHA-OAI-TEST:'
            $entry['reservedate'] = !empty($entry['reservedate'])
                ? $this->normalizeDate($entry['reservedate'])
                : '';
            $entry['expirationdate'] = !empty($entry['expirationdate'])
                ? $this->normalizeDate($entry['expirationdate'])
                : '';

            $response[$key] = $entry;
        }
    }

    public function normalizeCheckoutsResponse(&$response) {
        foreach ($response as $key => $entry) {
            $entry['item_id'] = isset($entry['item_id']) ? $entry['item_id'] : null;
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

    public function normalizeRenewItem(&$response) {
        $response['due_date'] = !empty($response['due_date'])
            ? $this->normalizeDate($response['due_date'])
            : '';
    }

    public function normalizeDate($date, $with_time = false) {
        $create_format = $with_time ? 'c': 'Y-m-d';
        return $this->dateConverter->convertToDisplayDate($create_format, $date);
    }
}