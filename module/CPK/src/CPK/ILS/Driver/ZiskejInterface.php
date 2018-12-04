<?php
    /**
     * Created by PhpStorm.
     * User: Andrii But
     */

    namespace CPK\ILS\Driver;


    interface ZiskejInterface
    {
        public function getLibraries();
        public function getReader($eppn, array $params);
        public function getUserTickets($eppn, array $params);
        public function getTicket($id, $eppn);
        public function getTicketMessages($id, $eppn);
    }