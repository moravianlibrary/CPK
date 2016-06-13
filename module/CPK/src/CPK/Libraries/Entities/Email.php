<?php

namespace CPK\Libraries\Entities;

class Email {
    private $email;
    private $note;

    function __construct($apiobject) {
        $this->email = $apiobject->email;
        $this->note = $apiobject->note;
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return mixed
     */
    public function getNote()
    {
        return $this->note;
    }

    
}