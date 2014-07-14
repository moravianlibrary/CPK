<?php

namespace PortalsCommon\Controller;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for controllers.
 */
class Factory
{
    /**
     * Construct the RecordController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return RecordController
     */
    public static function getRecordController(ServiceManager $sm)
    {
        return new RecordController(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

}
