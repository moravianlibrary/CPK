<?php

namespace CPK\Libraries;

use CPK\Libraries\Entities\FullLibrary;
use CPK\Libraries\Entities\SimpleLibrary;

class Loader {

    public $infoKnihovnyUrl = "http://info.knihovny.cz/api/";


    /**
     * @param $sigla
     *
     * @return FullLibrary object
     */
    public function LoadLibrary($sigla){

    }


    public function GetSearchResults($query, $page){
        $offset = ($page - 1) * 10;
        return $this->LoadLibraries($query, "10", $offset, "active");
        
    }

    /**
     * @param $query
     * @param $limit
     * @param $offset
     * @param $status
     *
     * @return array of SimpleLibrary objects
     */
    public function LoadLibraries($query, $limit, $offset, $status){

        $params['q'] = $query;
        $params['status'] 	  = $status;
        $params['limit'] 	  = $limit;
        $params['offset']     = $offset;
        $buildedQuery = http_build_query($params);

        $url   = $this->infoKnihovnyUrl.'libraries?'.$buildedQuery;

        $client = new \Zend\Http\Client($url);
        $response = $client->send();

        // Response head error handling
        $responseStatusCode = $response->getStatusCode();
        if($responseStatusCode !== 200)
            throw new \Exception("info.knihovny.cz response status code: ".$responseStatusCode);

        $output	= $response->getBody();


        $apilibraries = \Zend\Json\Json::decode($output);
        foreach ($apilibraries as $apilibrary)
        {
            $library = new SimpleLibrary();
            $library->setSigla($apilibrary->sigla);
            $library->setName($apilibrary->name);
            $library->setNameen($apilibrary->name_en);
            $library->setCode($apilibrary->code);
            $library->setCity($apilibrary->city);
            $library->setStreet($apilibrary->street);
            $library->setZip($apilibrary->zip);
            $library->setLongitude($apilibrary->longitude);
            $library->setLatitude($apilibrary->latitude);
            $library->setDescription($apilibrary->description);
            $library->setRegion($apilibrary->region);
            $library->setDistrict($apilibrary->district);
            $library->setContext($apilibrary->context);
            $library->setActive($apilibrary->active);
            $library->setIco($apilibrary->ico);
            $library->setDic($apilibrary->dic);
            $library->setMvsDescription($apilibrary->mvs_description);
            $library->setMvsUrl($apilibrary->mvs_url);
            $library->setUrl($apilibrary->url);

            $libraries[] = $library;
        }

        return $libraries;

    }

    /**
     * @return array of Project objects
     */
    public function LoadAllProjects(){
        return "ahoj";
    }

    /**
     * @return array of Service objects
     */
    public function LoadAllServices(){

    }

    /**
     * @param $sigla
     *
     * @return array of Project objects
     */
    public function LoadLibraryProjects($sigla){

    }

    /**
     * @return array of Service objects
     */
    public function LoadLibraryServices($sigla){

    }

    /**
     * @param $query
     * @param $limit
     * @param $lang
     *
     * @return array of Strings
     */
    public function LoadAutocomplete($query, $limit, $lang){

    }


}