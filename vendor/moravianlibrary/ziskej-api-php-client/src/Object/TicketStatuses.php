<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\Object;

class TicketStatuses
{

    /**
     * @var string[][]
     */
    private static $statuses = [
        // podana zadost o objednavku, zatim nezaplacena ani neprijata
        'created' => [
            'title' => 'Nová, nezaplacená',
            'status' => 'info',
        ],
        // platba zaplacena, objednavka zatim neprijata
        'paid' => [
            'title' => 'Nová, zaplacená',
            'status' => 'success',
        ],
        // zadost prijata
        'accepted' => [
            'title' => 'Ve zpracování',
            'status' => 'success',
        ],
        // objednavka pripravena k vyzvednuti
        'prepared' => [
            'title' => 'Vyřízena',
            'status' => 'success',
        ],
        // objednavka pujcena (vyzvednuta, zatim nevracena)
        'lent' => [
            'title' => 'Vypůjčena',
            'status' => 'success',
        ],
        // objednavka vracena
        'closed' => [
            'title' => 'Uzavřena',
            'status' => 'default',
        ],
        // zadost o objednavku / objednavka stornovana
        'cancelled' => [
            'title' => 'Stornována',
            'status' => 'warning',
        ],
        // zadost o objednavku zamitnuta
        'rejected' => [
            'title' => 'Zamítnuta',
            'status' => 'danger',
        ],
    ];

    /**
     * @return string[][]
     */
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
