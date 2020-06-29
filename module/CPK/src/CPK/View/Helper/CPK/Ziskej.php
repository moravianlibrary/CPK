<?php

namespace CPK\View\Helper\CPK;

use Zend\View\Helper\AbstractHelper;

/**
 * Ziskej View Helper
 */
class Ziskej extends AbstractHelper
{

    /**
     * @var \CPK\Ziskej\Ziskej
     */
    private $cpkZiskej;

    public function __construct(\CPK\Ziskej\Ziskej $cpkZiskej)
    {
        $this->cpkZiskej = $cpkZiskej;
    }

    public function isEnabled(): bool
    {
        return $this->cpkZiskej->isEnabled();
    }

    public function getCurrentMode(): string
    {
        return $this->cpkZiskej->getCurrentMode();
    }

    public function isProduction(): bool
    {
        return $this->cpkZiskej->getCurrentMode() === \CPK\Ziskej\Ziskej::MODE_PRODUCTION;
    }
}
