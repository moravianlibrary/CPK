<?php

namespace CPK\Libraries\Entities;

class Website {
    private $url;
    private $note;

    function __construct($apiobject) {
        $this->url = $apiobject->url;
        $this->note = $apiobject->note;
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return mixed
     */
    public function getNote()
    {
        return $this->note;
    }

    /**
     * @return boolean
     */
    public function hasNote()
    {
        return ! is_null($this->note);
    }
}