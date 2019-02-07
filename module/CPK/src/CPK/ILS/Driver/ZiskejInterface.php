<?php
/**
 * Created by PhpStorm.
 * User: Andrii But
 */

namespace CPK\ILS\Driver;


interface ZiskejInterface
{
    public function getLibraries();

    public function getReader($eppn, $expand);

    public function getUserTickets($expand);

    public function getTicketDetail($id, $eppn);

    public function getTicketMessages($id, $eppn);
}