<?php
/**
 * Libraries Controller
 *
 * PHP version 5
 *
 * Copyright (C) Moravian Library 2015.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @author  Jakub Šesták <sestak@mzk.cz>
 * @license http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
namespace CPK\Controller;

use CPK\Libraries\Entities\SearchResults;
use VuFind\Controller\AbstractBase;
use Zend\View\Model\JsonModel;

/**
 * PortalController
 *
 * @author  Jakub Šesták <sestak@mzk.cz>
 * @license http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
class LibrariesController extends AbstractBase
{
    use LoginTrait;

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
     * Constructor
     *
     * @param   \Zend\Config\Config  $config
     */
    public function __construct(\Zend\Config\Config $config)
    {
        parent::__construct();
        $this->config = $config;

        $this->adresarKnihovenApiUrl = ! empty($this->config->AdresarKnihoven->apiUrl)
            ? $this->config->AdresarKnihoven->apiUrl
            : null;
    }

	public function listAction()
	{
		$view = $this->createViewModel();

		$getParameters = $this->getRequest()->getQuery()->toArray();
		$query = (isset($getParameters['query']) && ! empty($getParameters['query'])) ? $getParameters['query'] : null;
		$page = (isset($getParameters['page']) && ! empty($getParameters['page'])) ? $getParameters['page'] : null;
		if($page==null) $page = 1;

		$librariesLoader = $this->getServiceLocator()->get('CPK\Libraries');

		$searchresults = new SearchResults($query, $page, $this->config);
		$libraries = $searchresults->getLibraries();
		if ($libraries==null) {
			$view->setTemplate('libraries/not-found');
			return $view;
		}
		$resultsCount = $searchresults->getNumberOfResults();
		$view->page = $page;
		$view->resultsCount = $resultsCount;
		$view->from = (($page-1)*10)+1;
		$view->to = min($page * 10,$resultsCount);

		$view->query = $query;
		$view->pagination = $searchresults->GetPagination();
		$view->libraries = $libraries;
		$view->apikey= (isset($this->getConfig()->GoogleMaps->apikey) && ! empty($this->getConfig()->GoogleMaps->apikey)) ? $this->getConfig()->GoogleMaps->apikey : null;
		$view->setTemplate('libraries/list');
		return $view;

	}

	public function libraryAction()
	{
		$view = $this->createViewModel();

		$getParameters = $this->getRequest()->getQuery()->toArray();
		$sigla = $getParameters['sigla'];

		$librariesLoader = $this->getServiceLocator()->get('CPK\Libraries');

		$library = $librariesLoader->LoadLibrary($sigla);

		$view->library = $library;

		$view->apikey= empty($this->getConfig()->GoogleMaps) ? '' : $this->getConfig()->GoogleMaps->apikey;

		$view->setTemplate('libraries/library');

		return $view;

	}

	public function autocompleteJsonAction()
	{
		$term = $this->params()->fromQuery('term');

		$apiResponse = file_get_contents($this->adresarKnihovenApiUrl."/autocomplete?q=$term");
		$dataArray = \Zend\Json\Json::decode($apiResponse, \Zend\Json\Json::TYPE_ARRAY);

		$result = new JsonModel($dataArray);

		return $result;
	}

    public function markersJsonAction()
    {
        $resultsLimit = 7000;
        $resultsPerIteration = 1000;
        $iterations = $resultsLimit / $resultsPerIteration;

        $queryParam = urlencode($this->params()->fromQuery('q'));
        $filterParam = urlencode($this->params()->fromQuery('filter'));

        $query = (! empty($queryParam)) ? $queryParam : "*";

        $filters = [];
        if (! empty($filterParam)) {
            $filters = explode("|", \LZCompressor\LZString::decompressFromBase64(specialUrlDecode($filterParam)));
        }

        $data = [];
        for ($i = 0; $i < $iterations; $i++) {

            $offset = $i * $resultsPerIteration;

            $url = $this->config->Index->url."/".$this->config->Index->default_core."/select?";

            /*$url .= "q=$query";
            $url .= "&fq=NOT+recordtype%3Alibrary+AND+NOT+recordtype%3Asfx";*/
            
            //$url .= "q=recordtype:library";

            $url .= "q=recordtype:library";
            $url .= "%0A";
            $url .= "merged_child_boolean:(true)";

            foreach ($filters as $filter) {
                $url .= "%0A";
                $url .= "$filter";
            }

            if ($query != '*') {

                //$url .= "allLibraryFields_txt_mv:($query)";

                $reader = $this->getServiceLocator()->get('VuFind\SearchSpecsReader');
                $specs = $reader->get('searchspecs.yaml');
                $librariesFields = isset($specs['Author']['DismaxFields']) ? $specs['Libraries']['DismaxFields'] : [];

                if (count($librariesFields)) {
                    $url .= "&fq=";
                    foreach ($librariesFields as $libraryField) {
                        $field = explode("^", $libraryField)[0];
                        $url .= $field.":($query)+OR+";
                    }

                    $url = substr($url, 0, -4);
                }
            }

            $url .= "&fl=name_search_txt,address_search_txt_mv,reg_lib_search_txt_mv,gps_str,id";
            $url .= "&wt=json";
            $url .= "&indent=true";
            $url .= '&rows=' . $resultsPerIteration;

            $url .= '&start=' . $offset;

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            $solrResponse = curl_exec($ch);
            curl_close($ch);
            $results = \Zend\Json\Json::decode($solrResponse, \Zend\Json\Json::TYPE_ARRAY);

            if (isset($results['response']['numFound']) && $results['response']['numFound'] > 0) {
                foreach ($results['response']['docs'] as $library) {
                    if (! empty($library['gps_str'])) {
                        $data[] = [
                            'name' => ! empty($library['name_search_txt']) ? $library['name_search_txt'] : '',
                            'address' => ! empty($library['address_search_txt_mv'][0]) ? $library['address_search_txt_mv'][0] : '',
                            'id' => $library['id'] ? $library['id'] : '',
                            'latitude' => explode(" ", $library['gps_str'])[0],
                            'longitude' => explode(" ", $library['gps_str'])[1],
                        ];
                    }
                }
            }
        }

        return new JsonModel($data);
    }
}