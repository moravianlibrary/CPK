<?php

namespace CPK\Libraries\Entities;

class SimpleLibrary
{
    private $sigla;
    private $name;
    private $nameen;
    private $code;
    private $city;
    private $street;
    private $zip;
    private $longitude;
    private $latitude;
    private $description;
    private $region;
    private $district;
    private $context;
    private $active;
    private $ico;
    private $dic;
    private $mvs_description;
    private $mvs_url;

    //url for getting this info
    private $url;

    /**
     * @param mixed $sigla
     */
    public function setSigla($sigla)
    {
        $this->sigla = $sigla;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @param mixed $nameen
     */
    public function setNameen($nameen)
    {
        $this->nameen = $nameen;
    }

    /**
     * @param mixed $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @param mixed $city
     */
    public function setCity($city)
    {
        $this->city = $city;
    }

    /**
     * @param mixed $street
     */
    public function setStreet($street)
    {
        $this->street = $street;
    }

    /**
     * @param mixed $zip
     */
    public function setZip($zip)
    {
        $this->zip = $zip;
    }

    /**
     * @param mixed $longitude
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;
    }

    /**
     * @param mixed $latitude
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @param mixed $region
     */
    public function setRegion($region)
    {
        $this->region = $region;
    }

    /**
     * @param mixed $district
     */
    public function setDistrict($district)
    {
        $this->district = $district;
    }

    /**
     * @param mixed $context
     */
    public function setContext($context)
    {
        $this->context = $context;
    }

    /**
     * @param mixed $active
     */
    public function setActive($active)
    {
        $this->active = $active;
    }

    /**
     * @param mixed $ico
     */
    public function setIco($ico)
    {
        $this->ico = $ico;
    }

    /**
     * @param mixed $dic
     */
    public function setDic($dic)
    {
        $this->dic = $dic;
    }

    /**
     * @param mixed $mvs_description
     */
    public function setMvsDescription($mvs_description)
    {
        $this->mvs_description = $mvs_description;
    }

    /**
     * @param mixed $mvs_url
     */
    public function setMvsUrl($mvs_url)
    {
        $this->mvs_url = $mvs_url;
    }

    /**
     * @param mixed $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getSigla()
    {
        return $this->sigla;
    }

    /**
     * @return mixed
     */
    public function getNameen()
    {
        return $this->nameen;
    }

    /**
     * @return mixed
     */
    public function getAddress()
    {
        return $this->street . ", " . $this->zip . " " .$this->city;
    }


    /**
     * @return mixed
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * @return mixed
     */
    public function getLatitude()
    {
        return $this->latitude;
    }




    
    
}
