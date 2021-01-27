<?php

namespace CPK\View\Helper\CPK;

use Zend\View\Helper\AbstractHelper;

/**
 * Ziskej Edd View Helper
 */
class ZiskejEdd extends AbstractHelper
{

    /**
     * @var \CPK\Ziskej\ZiskejEdd
     */
    private $cpkZiskejEdd;

    public function __construct(\CPK\Ziskej\ZiskejEdd $cpkZiskej)
    {
        $this->cpkZiskejEdd = $cpkZiskej;
    }

    public function isEnabled(): bool
    {
        return $this->cpkZiskejEdd->isEnabled();
    }

    public function getCurrentMode(): string
    {
        return $this->cpkZiskejEdd->getCurrentMode();
    }

    public function isProduction(): bool
    {
        return $this->cpkZiskejEdd->getCurrentMode() === \CPK\Ziskej\ZiskejEdd::MODE_PRODUCTION;
    }

    public function getModes()
    {
        return $this->cpkZiskejEdd->getModes();
    }
}
