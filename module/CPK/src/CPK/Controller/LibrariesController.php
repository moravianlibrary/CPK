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
        return $this->redirect()->toRoute(
            'default',
            array(
                'controller' => 'Search',
                'action' => 'Results',
            ),
            array('query' => array(
                'type0[]' => 'Libraries'
            ))
        );
	}

	public function libraryAction()
	{
	     return $this->redirect()->toRoute('default');
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

            $url .= "q=recordtype:library";
            $url .= "%0A";
            $url .= "merged_boolean:(true)";

            if ($query != '*') {

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

            $andFacets = [];
            $orFacets = [];

            foreach ($filters as $filter) {
                if ($filter[0] == '~') {
                    $facetName = str_replace("~", "", explode(":", $filter)[0]);
                    $facet = str_replace("~", "", $filter);
                    if (! isset($orFacets[$facetName])) {
                        $orFacets[$facetName] = [];
                        $orFacets[$facetName][] = $facet;
                    } else {
                        $orFacets[$facetName][] = $facet;
                    }

                } else {
                    $andFacets[] = $filter;
                }
            }

            foreach ($andFacets as $facet) {
                $url .= "&fq=".urlencode($facet);
            }

            foreach ($orFacets as $facetNames) {
                $url .= "&fq=";
                foreach ($facetNames as $facet) {
                    $url .= urlencode($facet)."+OR+";
                }
                $url = substr($url, 0, -4);
            }

            $url .= "&fl=name_display,address_map_display_mv,gps_display,local_ids_str_mv";
            $url .= "&wt=json";
            $url .= "&indent=true";
            $url .= "&sort=library_relevance_str+asc";
            $url .= '&rows=' . $resultsPerIteration;

            $url .= '&start=' . $offset;

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            $solrResponse = curl_exec($ch);
            curl_close($ch);

            try {
                $results = \Zend\Json\Json::decode($solrResponse, \Zend\Json\Json::TYPE_ARRAY);
            } catch (\Exception $e) {
                return new JsonModel(['error' => 'Map cannot be loaded. Bad Solr response.']);
            }

            if (isset($results['response']['numFound']) && $results['response']['numFound'] > 0) {
                foreach ($results['response']['docs'] as $library) {
                    if (! empty($library['gps_display'])) {
                        $data[] = [
                            'name' => ! empty($library['name_display']) ? $library['name_display'] : '',
                            'address' => ! empty($library['address_map_display_mv'][0]) ? $library['address_map_display_mv'][0] : '',
                            'id' => $library['local_ids_str_mv'] ? $library['local_ids_str_mv'][0] : '',
                            'latitude' => explode(" ", $library['gps_display'])[0],
                            'longitude' => explode(" ", $library['gps_display'])[1],
                        ];
                    }
                }
            }
        }

        return new JsonModel($data);
    }
}