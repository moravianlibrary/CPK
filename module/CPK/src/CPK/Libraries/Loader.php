<?php

namespace CPK\Libraries;

use CPK\Libraries\Entities\Email;
use CPK\Libraries\Entities\Fax;
use CPK\Libraries\Entities\FullLibrary;
use CPK\Libraries\Entities\OpeningHours;
use CPK\Libraries\Entities\Person;
use CPK\Libraries\Entities\Phone;
use CPK\Libraries\Entities\Project;
use CPK\Libraries\Entities\Service;
use CPK\Libraries\Entities\SimpleLibrary;
use CPK\Libraries\Entities\Website;

class Loader {

    public $infoKnihovnyUrl = "http://info.knihovny.cz/api/";


    /**
     * @param $sigla
     *
     * @return FullLibrary object
     */
    public function LoadLibrary($sigla){
        $url   = $this->infoKnihovnyUrl.'libraries/'.$sigla;

        $client = new \Zend\Http\Client($url);
        $response = $client->send();

        // Response head error handling
        $responseStatusCode = $response->getStatusCode();
        if($responseStatusCode !== 200)
            throw new \Exception("info.knihovny.cz response status code: ".$responseStatusCode);

        $output	= $response->getBody();
        $apilibrary = \Zend\Json\Json::decode($output);

        $library = new FullLibrary();
        $this->ParseSimpleLibrary($apilibrary,$library);
        $library->created_at = $apilibrary->created_at;
        $library->updated_at = $apilibrary->updated_at;
        foreach ($apilibrary->people as $apiperson)
        {
            $person = new Person($apiperson);
            $people[] = $person;
        }
        $library->people = $people;
        
        
        foreach ($apilibrary->websites as $apiwebsite) {
            $website = new Website($apiwebsite);
            $websites[] = $website;
        }
        $library->websites = $websites;

        foreach ($apilibrary->emails as $apiemail) {
            $email = new Email($apiemail);
            $emails[] = $email;
        }
        $library->emails = $emails;
        
        foreach ($apilibrary->phones as $apiphone) {
            $phone = new Phone($apiphone);
            $phones[] = $phone;
        }
        $library->phones = $phones;
        
        foreach ($apilibrary->faxes as $apifax) {
            $fax = new Fax($apifax);
            $faxes[] = $fax;
        }
        $library->faxes = $faxes;
        
        if(isset($apilibrary->opening_hours) && ! empty($apilibrary->opening_hours)) {
            $apiOpeningHours = $apilibrary->opening_hours;
            $openingHours = new OpeningHours($apiOpeningHours);

            $library->opening_hours = $openingHours;
        }

        foreach ($apilibrary->projects as $apiproject) {
            $project = new Project($apiproject);
            $projects[] = $project;
        }
        $library->projects = $projects;

        foreach ($apilibrary->services as $apiservice) {
            $service = new Service($apiservice);
            $services[] = $service;
        }
        $library->services = $services;

        return $library;


    }

    private function ParseSimpleLibrary($apilibrary,SimpleLibrary $libraryObject) {
        $libraryObject->setSigla($apilibrary->sigla);
        $libraryObject->setName($apilibrary->name);
        $libraryObject->setNameen($apilibrary->name_en);
        $libraryObject->setCode($apilibrary->code);
        $libraryObject->setCity($apilibrary->city);
        $libraryObject->setStreet($apilibrary->street);
        $libraryObject->setZip($apilibrary->zip);
        $libraryObject->setLongitude($apilibrary->longitude);
        $libraryObject->setLatitude($apilibrary->latitude);
        $libraryObject->setDescription($apilibrary->description);
        $libraryObject->setRegion($apilibrary->region);
        $libraryObject->setDistrict($apilibrary->district);
        $libraryObject->setContext($apilibrary->context);
        $libraryObject->setActive($apilibrary->active);
        $libraryObject->setIco($apilibrary->ico);
        $libraryObject->setDic($apilibrary->dic);
        $libraryObject->setMvsDescription($apilibrary->mvs_description);
        $libraryObject->setMvsUrl($apilibrary->mvs_url);
        $libraryObject->setUrl($apilibrary->url);
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
            $this->ParseSimpleLibrary($apilibrary,$library);

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