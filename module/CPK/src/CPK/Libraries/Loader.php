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

        if(isset($apilibrary->people) && ! empty($apilibrary->people)) {
            $projects = [];
            foreach ($apilibrary->people as $apiperson) {
                $person = new Person($apiperson);
                $people[] = $person;
            }
            $library->people = $people;
        }


        if(isset($apilibrary->websites) && ! empty($apilibrary->websites)) {
            $projects = [];
            foreach ($apilibrary->websites as $apiwebsite) {
                $website = new Website($apiwebsite);
                $websites[] = $website;
            }
            $library->websites = $websites;
        }

        if(isset($apilibrary->emails) && ! empty($apilibrary->emails)) {
            $services = [];
            foreach ($apilibrary->emails as $apiemail) {
                $email = new Email($apiemail);
                $emails[] = $email;
            }
            $library->emails = $emails;
        }

        if(isset($apilibrary->phones) && ! empty($apilibrary->phones)) {
            $services = [];
            foreach ($apilibrary->phones as $apiphone) {
                $phone = new Phone($apiphone);
                $phones[] = $phone;
            }
            $library->phones = $phones;
        }

        if(isset($apilibrary->faxes) && ! empty($apilibrary->faxes)) {
            $services = [];
            foreach ($apilibrary->faxes as $apifax) {
                $fax = new Fax($apifax);
                $faxes[] = $fax;
            }
            $library->faxes = $faxes;
        }
        
        if(isset($apilibrary->opening_hours) && ! empty($apilibrary->opening_hours)) {
            $apiOpeningHours = $apilibrary->opening_hours;
            $openingHours = new OpeningHours($apiOpeningHours);

            $library->opening_hours = $openingHours;
        }

        if(isset($apilibrary->projects) && ! empty($apilibrary->projects)) {
            $projects = [];
            foreach ($apilibrary->projects as $apiproject) {
                $project = new Project($apiproject);
                $projects[] = $project;
            }
            $library->projects = $projects;
        }

        if(isset($apilibrary->services) && ! empty($apilibrary->services)) {
            $services = [];
            foreach ($apilibrary->services as $apiservice) {
                $service = new Service($apiservice);
                $services[] = $service;
            }
            $library->services = $services;
        }

        return $library;


    }

    private function ParseSimpleLibrary($apilibrary,SimpleLibrary $libraryObject) {
        if(isset($apilibrary->sigla) && ! empty($apilibrary->sigla))
            $libraryObject->setSigla($apilibrary->sigla);
        if(isset($apilibrary->name) && ! empty($apilibrary->name))
            $libraryObject->setName($apilibrary->name);
        if(isset($apilibrary->name_en) && ! empty($apilibrary->name_en))
            $libraryObject->setNameen($apilibrary->name_en);
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


    public function GetSearchResults($query, $page){
        $offset = ($page - 1) * 10;
        return $this->LoadLibraries($query, "10", $offset, "active");
    }

    public function GetAllSearchResults($query){
        return $this->LoadLibraries($query, "9999", null, "active");
    }

    public function GetCountOfAllSearchResults($query){
        return count($this->GetAllSearchResults($query));
    }

    public function GetNumberOfPages($query){
        $count = $this->GetCountOfAllSearchResults($query);
        return ceil($count/10);
    }



    public function GetPagination($query, $page){
        $totalPages = $this->GetNumberOfPages($query);
        $currentPage = $page;

        $paginationSet = [];
        if($totalPages<=5) {
            for ($i = 1; $i <= $totalPages; $i++) {
                $paginationSet[] = $i;
            }
            return $paginationSet;
        }
        else {
            $paginationSet[] = 1;

            for ($i = 1; $i <= 3; $i++) {
                $candidatePage = $currentPage - $i;
                if ($candidatePage > 0)
                    $paginationSet[] = $candidatePage;
            }
            $paginationSet[] = $currentPage;
            for ($i = 1; $i <= 3; $i++) {
                $candidatePage = $currentPage + $i;
                if ($candidatePage < $totalPages)
                    $paginationSet[] = $candidatePage;
            }

            $paginationSet[] = $totalPages;


            sort($paginationSet);

            return array_unique($paginationSet);

        }




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
        if ($offset!=null)
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