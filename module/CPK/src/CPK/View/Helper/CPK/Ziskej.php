<?php

namespace CPK\View\Helper\CPK;

use Zend\ServiceManager\ServiceManager;
use Zend\View\Helper\AbstractHelper;

class Ziskej extends AbstractHelper
{

    /**
     * @var string
     */
    private $mode;

    public function __construct(ServiceManager $serviceManager)
    {
        /** @var \Zend\Http\PhpEnvironment\Request $request */
        $request = $serviceManager->getServiceLocator()
            ->get('Request');

        /** @var \Zend\Http\Header\Cookie $cookie */
        $cookie = $request->getCookie();

        if (!empty($cookie->ziskej) && $cookie->ziskej != 'disabled') {
            $this->mode = $cookie->ziskej;
        }

    }

    public function isEnabled(): bool
    {
        return (bool)$this->mode;
    }
}
