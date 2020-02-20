<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\Object;

class TicketStatuses
{

    private static $statuses = [
        // podána žádost o objednávku, zatím nezaplacena ani nepřijata
        'created' => [
            'title' => 'Nová, nezaplacená',
            'status' => 'info',
        ],
        // platba zaplacena, objednávka zatím nepřijata
        'paid' => [
            'title' => 'Nová, zaplacená',
            'status' => 'success',
        ],
        // žádost přijata
        'accepted' => [
            'title' => 'Ve zpracování',
            'status' => 'success',
        ],
        // objednávka připravena k vyzvednutí
        'prepared' => [
            'title' => 'Vyřízena',
            'status' => 'success',
        ],
        // objednávka pujcena (vyzvednuta, zatím nevrácena)
        'lent' => [
            'title' => 'Vypůjčena',
            'status' => 'success',
        ],
        // objednávka vrácena
        'closed' => [
            'title' => 'Uzavřena',
            'status' => 'default',
        ],
        // žádost o objednávku / objednávka stornována
        'cancelled' => [
            'title' => 'Stornována',
            'status' => 'warning',
        ],
        // žádost o objednávku zamítnuta
        'rejected' => [
            'title' => 'Zamítnuta',
            'status' => 'danger',
        ],
    ];


    public static function getAll(): array
    {
        return self::$statuses;
    }

    public static function getStatus(string $code): string
    {
        $statuses = self::$statuses;

        if (isset($statuses[$code])) {
            $status = $statuses[$code];
            if (isset($status['status'])) {
                return $status['status'];
            } else {
                return 'default';
            }
        } else {
            return 'default';
        }
    }

}
