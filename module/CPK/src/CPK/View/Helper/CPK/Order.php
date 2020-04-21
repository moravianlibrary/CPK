<?php

namespace CPK\View\Helper\CPK;

use Zend\View\Helper\AbstractHelper;

/**
 * Order View Helper
 */
class Order extends AbstractHelper
{

    public function __construct()
    {
    }

    public function getStatusClass(string $status = null): string
    {
        switch ($status) {
            case 'created':
                return 'warning';
                break;
            case 'paid':
                return 'warning';
                break;
            case 'accepted':
                return 'success';
                break;
            case 'prepared':
                return 'success';
                break;
            case 'lent':
                return 'success';
                break;
            case 'closed':
                return 'default';
                break;
            case 'cancelled':
                return 'default';
                break;
            case 'rejected':
                return 'danger';
                break;
            default:
                return 'default';
                break;
        }
    }

}
