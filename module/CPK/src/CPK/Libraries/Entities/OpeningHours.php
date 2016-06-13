<?php

namespace CPK\Libraries\Entities;

class OpeningHours {
    private $mo;
    private $tu;
    private $we;
    private $th;
    private $fr;
    private $sa;
    private $su;
    private $note;

    function __construct($apiobject) {
        $this->mo = $apiobject->mo;
        $this->tu = $apiobject->tu;
        $this->we = $apiobject->we;
        $this->th = $apiobject->th;
        $this->fr = $apiobject->fr;
        $this->sa = $apiobject->sa;
        $this->su = $apiobject->su;
        $this->note = $apiobject->note;
    }

    /**
     * @return mixed
     */
    public function getMo()
    {
        return $this->mo;
    }

    /**
     * @return mixed
     */
    public function getTu()
    {
        return $this->tu;
    }

    /**
     * @return mixed
     */
    public function getWe()
    {
        return $this->we;
    }

    /**
     * @return mixed
     */
    public function getTh()
    {
        return $this->th;
    }

    /**
     * @return mixed
     */
    public function getFr()
    {
        return $this->fr;
    }

    /**
     * @return mixed
     */
    public function getSa()
    {
        return $this->sa;
    }

    /**
     * @return mixed
     */
    public function getSu()
    {
        return $this->su;
    }

    /**
     * @return mixed
     */
    public function getNote()
    {
        return $this->note;
    }
    
    
    
}