<?php

namespace MZKPortal\Search\Params;
use Zend\ServiceManager\ServiceManager;

class Factory
{
    /**
     * Factory for Solr params object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Solr
     */
    
    public static function getSolrParams(ServiceManager $sm) {
        $options    = $sm->getServiceLocator()->get('VuFind\SearchOptionsPluginManager')->get('Solr');
        $config     = $sm->getServiceLocator()->get('VuFind\Config');
        $translator = $sm->getServiceLocator()->get('VuFind\Translator');
        $solr = new \MZKPortal\Search\Solr\Params(clone($options), $config, $translator);
        return $solr;
    }
}