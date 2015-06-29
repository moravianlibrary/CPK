<?php

namespace MZKPortal\Search\Solr;
use VuFindSearch\ParamBag;


class Params extends \VuFind\Search\Solr\Params
{
    protected $languageDependentFacets = array();
    protected $locale = 'cs';
    public function __construct($options, \VuFind\Config\PluginManager $configLoader, \Zend\I18n\Translator\Translator $translator = null)
    {
        parent::__construct($options, $configLoader);
        if ($translator) {
            $this->locale = $translator->getLocale();
        }
        // Use basic facet limit by default, if set:
        $config = $configLoader->get('facets');
        if (isset($config->LanguageDependentFacets) && isset($config->LanguageDependentFacets->facets)) {
            $this->languageDependentFacets = $config->LanguageDependentFacets->facets->toArray();
        }
    }
   
    /**
     * Return current facet configurations -
     *  language filter is applied on facets in 
     *
     * @return array $facetSet
     */
    public function getFacetSettings()
    {
        $result = parent::getFacetSettings();
        //eliminate additional facets from facets.ini -> Results
        $tmpCopy = $result['field'];
        foreach ($this->languageDependentFacets as $currentFacet) {
            if (in_array($currentFacet, $result['field'])) {
                $newFacet = $currentFacet . '_' . $this->locale . '_txtF_mv';
                $tmpCopy = array_diff(
                    $tmpCopy,
                    array_intersect(
                        $tmpCopy,
                            array($currentFacet, $currentFacet . '_cs_txtF_mv', $currentFacet . '_en_txtF_mv')
                    )
                );
                if (in_array($newFacet, $result['field'])) {          
                    $tmpCopy[] = $newFacet;
                } else {
                    $tmpCopy[] = $currentFacet;
                }
            }
        }
        $result['field'] = array_values($tmpCopy);

        return $result;
    }
}
