<?php

namespace CPK\View\Helper\CPK;

use Zend\Http\Header\Cookie;
use Zend\View\Helper\AbstractHelper;

/**
 * Ziskej View Helper
 */
class Ziskej extends AbstractHelper
{

    /**
     * @var string
     */
    private $mode;

    public function __construct(Cookie $cookie)
    {
        if (!empty($cookie->ziskej) && $cookie->ziskej != 'disabled') {
            $this->mode = $cookie->ziskej;
        }

    }

    public function isEnabled(): bool
    {
        return (bool)$this->mode;
    }
}
