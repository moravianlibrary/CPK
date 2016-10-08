<?php

namespace CPK\Libraries\Entities;

class SearchResults
{
    //set in constructor
    private $currentPage;
    private $query;

    //load on first use
    private $librariesPage;
    private $count;

    private $resultsPerPage = 10;

    /**
     * Link to API of adresar knihoven.
     * @var string
     */
    protected $adresarKnihovenApiUrl;

    /**
     * Config
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * SearchResults constructor.
     * @param $query - null for empty query
     * @param $currentPage
     * @param \Zend\Config\Config  $config
     */
    public function __construct($query, $currentPage, \Zend\Config\Config $config)
    {
        $this->query = $query;
        if ($currentPage == null)
            throw new \Exception("Invalid argument: currentPage");
        $this->currentPage = $currentPage;

        $this->config = $config;

        $this->adresarKnihovenApiUrl = ! empty($this->config->AdresarKnihoven->apiUrl)
            ? $this->config->AdresarKnihoven->apiUrl
            : null;
    }

    /**
     * @param $query
     * @param $limit
     * @param $offset
     * @param $status
     *
     * @return array of SimpleLibrary objects
     */
    private function LoadLibraries($query, $limit, $offset, $status)
    {

        $params['q'] = $query;
        $params['status'] = $status;
        $params['limit'] = $limit;
        if ($offset != null)
            $params['offset'] = $offset;
        $buildedQuery = http_build_query($params);

        $url = $this->adresarKnihovenApiUrl . '/v1/libraries?' . $buildedQuery;

        $client = new \Zend\Http\Client($url, array('timeout' => 60));
        $response = $client->send();

        // Response head error handling
        $responseStatusCode = $response->getStatusCode();
        if ($responseStatusCode !== 200)
            throw new \Exception("info.knihovny.cz response status code: " . $responseStatusCode);

        $output = $response->getBody();


        $apiObject = \Zend\Json\Json::decode($output);

        $respLimit = $apiObject->limit;
        $respOffset = $apiObject->offset;
        $this->count = $apiObject->all;
        $apiLibraries = $apiObject->libraries;

        $libraries = [];
        foreach ($apiLibraries as $apilibrary) {
            $library = new SimpleLibrary();
            $library->ParseSimpleLibrary($apilibrary, $library);

            $libraries[] = $library;
        }

        return $libraries;

    }


    private function LoadPageResults()
    {
        $offset = ($this->currentPage - 1) * $this->resultsPerPage;
        $this->librariesPage = $this->LoadLibraries($this->query, $this->resultsPerPage, $offset, "active");
    }

    public function getCurrentPage()
    {
        return $this->currentPage;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function getLibraries()
    {
        if ($this->librariesPage == null)
            $this->LoadPageResults();
        return $this->librariesPage;
    }

    public function getNumberOfResults()
    {
        return $this->count;
    }

    public function GetNumberOfPages()
    {
        $count = $this->getNumberOfResults();
        return ceil($count / $this->resultsPerPage);
    }


    public function GetPagination()
    {
        $totalPages = $this->GetNumberOfPages($this->query);
        $currentPage = $this->currentPage;

        $paginationSet = [];
        if ($totalPages <= 5) {
            for ($i = 1; $i <= $totalPages; $i++) {
                $paginationSet[] = $i;
            }
            return $paginationSet;
        } else {
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

}
