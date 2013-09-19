<?php

namespace VuFindTest\Search;

use VuFind\Search\Solr\Options;
use VuFind\Search\Solr\Params;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

class MultiFacetsTest extends \VuFindTest\Unit\TestCase {
    
    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp()
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            return $this->markTestSkipped('Continuous integration not running.');
        }
    }
    
    /**
     * This test performs Solr searches using multifacets and checks whether they work 
     * as expected (adding 'OR something' to filter query should increase count of returned records 
     * in at least one case). 
     */
    public function testMultiFacetsSearch()
    {
        //configuration
        $solr = $this->getServiceManager()->get('VuFind\Search\BackendManager')
        ->get('Solr');
        $connector = $solr->getConnector();
        $this->assertNotNull($connector);
        
        $config = $this->getServiceManager()->get('VuFind\Config');
        $this->assertNotNull($config);
        
        $options = new Options($config);
        $this->assertNotNull($options);
        
        $params = new Params($options, $config);
        $this->assertNotNull($params);
        
        $testedFields = array('institution', 'format');
        //try each field
        foreach ($testedFields as $currentField) {
        
            $params->addFacet($currentField,$currentField);
            $paramBag = $params->getBackendParameters();
            $this->assertTrue(is_array($paramBag->get('facet')));
            
            $query = new Query("*:*");
            $collection = $solr->search($query,0,0,$paramBag);
            $this->assertNotNull($collection);
            
            //get all possible filter values of current facet field
            $facets = $collection->getFacets()->getFieldFacets()->getArrayCopy();
            $list = $facets[$currentField];
            $list->rewind();
            $allFilters = array();
            while ($list->valid()) {
                $allFilters[] = $list->key();
                $list->next();
            }
            
            for ($i = 0; $i < count($allFilters); $i++ ) {
                for ($j = $i+1; $j < count($allFilters); $j++ ) {
                    //test all possible pairs of filters for current facet field
                    $params = new Params($options, $config);
                    $params->addFacet($currentField,$currentField);
                    $params->setMultiselectFacets(array($currentField));
                    $params->addFilter($currentField.':'.$allFilters[$i]);                 
                    $paramBag = $params->getBackendParameters();
                                   
                    $collection = $solr->search($query,0,0,$paramBag); 
                    $this->assertNotNull($collection);
                    $total1 = $collection->getTotal();
            
                    $params->addFilter($currentField.':'.$allFilters[$j]);
                    $paramBag = $params->getBackendParameters();
                    
                    $collection = $solr->search($query,0,0,$paramBag);
                    $this->assertNotNull($collection);
                    $this->assertNotNull($collection);
                    $total2 = $collection->getTotal();
                    
                    if ($total2 > $total1) {
                        //adding multifacet filter caused increasment of records in search results , we're done
                        return;
                    }
                }
            }
        }

        $this->fail("Multifacets test failed, multifacets has no effect on solr response.".
                " Tested fields: [ ".implode(", ", $testedFields)." ]");

    }
}

?>