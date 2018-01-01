<?php
/**
 * Created by PhpStorm.
 * User: jirislav
 * Date: 30.12.17
 * Time: 10:13
 */

namespace CPK\ILS\Driver;


interface MultiBackendInterface
{
    public function getProlongRegistrationUrl(array $patron);
    public function getPaymentURL(string $patron, float $fine);
}