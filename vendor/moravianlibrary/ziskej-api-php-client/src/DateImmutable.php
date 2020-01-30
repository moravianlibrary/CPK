<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi;

use DateTimeImmutable;
use DateTimeZone;

class DateImmutable extends DateTimeImmutable
{

    public static function createFrom(string $date): ?DateTimeImmutable
    {
        $dateTimeImmutable = parent::createFromFormat('Y-m-d H:i:s', $date . ' ' . '00:00:00', new DateTimeZone('UTC'));

        if ($dateTimeImmutable) {
            return $dateTimeImmutable;
        } else {
            return null;
        }
    }

}
