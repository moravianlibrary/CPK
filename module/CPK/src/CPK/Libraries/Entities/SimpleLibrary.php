<?php

namespace CPK\Libraries\Entities;

class SimpleLibrary
{
    private $sigla;
    private $name;
    private $nameen;
    private $bname;
    private $bnameen;
    private $cname;
    private $cnameen;
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


    public function ParseSimpleLibrary($apilibrary,SimpleLibrary $libraryObject) {
        if(isset($apilibrary->sigla) && ! empty($apilibrary->sigla))
            $libraryObject->setSigla($apilibrary->sigla);
        if(isset($apilibrary->name) && ! empty($apilibrary->name))
            $libraryObject->setName($apilibrary->name);
        if(isset($apilibrary->name_en) && ! empty($apilibrary->name_en))
            $libraryObject->setNameen($apilibrary->name_en);
        if(isset($apilibrary->bname) && ! empty($apilibrary->bname))
            $libraryObject->setBName($apilibrary->bname);
        if(isset($apilibrary->bname_en) && ! empty($apilibrary->bname_en))
            $libraryObject->setBNameen($apilibrary->bname_en);
        if(isset($apilibrary->cname) && ! empty($apilibrary->cname))
            $libraryObject->setCName($apilibrary->cname);
        if(isset($apilibrary->cname_en) && ! empty($apilibrary->cname_en))
            $libraryObject->setCNameen($apilibrary->cname_en);
        if(isset($apilibrary->code) && ! empty($apilibrary->code))
            $libraryObject->setCode($apilibrary->code);
        if(isset($apilibrary->city) && ! empty($apilibrary->city))
            $libraryObject->setCity($apilibrary->city);
        if(isset($apilibrary->street) && ! empty($apilibrary->street))
            $libraryObject->setStreet($apilibrary->street);
        if(isset($apilibrary->zip) && ! empty($apilibrary->zip))
            $libraryObject->setZip($apilibrary->zip);
        if(isset($apilibrary->longitude) && ! empty($apilibrary->longitude))
            $libraryObject->setLongitude($apilibrary->longitude);
        if(isset($apilibrary->latitude) && ! empty($apilibrary->latitude))
            $libraryObject->setLatitude($apilibrary->latitude);
        if(isset($apilibrary->description) && ! empty($apilibrary->description))
            $libraryObject->setDescription($apilibrary->description);
        if(isset($apilibrary->region) && ! empty($apilibrary->region))
            $libraryObject->setRegion($apilibrary->region);
        if(isset($apilibrary->district) && ! empty($apilibrary->district))
            $libraryObject->setDistrict($apilibrary->district);
        if(isset($apilibrary->context) && ! empty($apilibrary->context))
            $libraryObject->setContext($apilibrary->context);
        if(isset($apilibrary->active) && ! empty($apilibrary->active))
            $libraryObject->setActive($apilibrary->active);
        if(isset($apilibrary->ico) && ! empty($apilibrary->ico))
            $libraryObject->setIco($apilibrary->ico);
        if(isset($apilibrary->dic) && ! empty($apilibrary->dic))
            $libraryObject->setDic($apilibrary->dic);
        if(isset($apilibrary->mvs_description) && ! empty($apilibrary->mvs_description))
            $libraryObject->setMvsDescription($apilibrary->mvs_description);
        if(isset($apilibrary->mvs_url) && ! empty($apilibrary->mvs_url))
            $libraryObject->setMvsUrl($apilibrary->mvs_url);
        if(isset($apilibrary->url) && ! empty($apilibrary->url))
            $libraryObject->setUrl($apilibrary->url);
    }

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
     * @param mixed $name
     */
    public function setBName($bname)
    {
        $this->bname = $bname;
    }

    /**
     * @param mixed $nameen
     */
    public function setBNameen($bnameen)
    {
        $this->bnameen = $bnameen;
    }

    /**
     * @param mixed $name
     */
    public function setCName($cname)
    {
        $this->cname = $cname;
    }

    /**
     * @param mixed $nameen
     */
    public function setCNameen($cnameen)
    {
        $this->cnameen = $cnameen;
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
    public function getBName()
    {
        return $this->bname;
    }

    /**
     * @return mixed
     */
    public function getCName()
    {
        return $this->cname;
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
    public function getBNameen()
    {
        return $this->bnameen;
    }

    /**
     * @return mixed
     */
    public function getCNameen()
    {
        return $this->cnameen;
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

    public function getAlternativeNamesFormated()
    {
        $b = $this->bname;
        $c = $this->cname;

        if ($b==null&&$c==null) return null;
        if ($b==null) {
            return "(" . $c . ")";
        };
        if ($c==null) {
            return "(" . $b . ")";
        }
        return "(" . $b . " - " . $c . ")";
    }


    
    
}
