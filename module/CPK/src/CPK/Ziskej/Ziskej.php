<?php
namespace CPK\Ziskej;

use VuFind\Cookie\CookieManager;
use Zend\Config\Config;

/**
 * CPK Ziskej Class
 */
class Ziskej
{
    public const MODE_DISABLED = 'disabled';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var CookieManager
     */
    private $cookieManager;

    /**
     * @var string
     */
    private $defaultMode = self::MODE_DISABLED;

    public function __construct(
        Config $config,
        CookieManager $cookieManager
    ) {
        $this->config = $config;
        $this->cookieManager = $cookieManager;
    }

    /**
     * Get if ziskej is enabled on cpk
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->getCurrentMode() !== self::MODE_DISABLED;
    }

    /**
     * Get list of Ziskej API urls
     *
     * @return mixed[]
     */
    public function getUrls(): array
    {
        return $this->config->get('Ziskej')->toArray();
    }

    /**
     * Get list of Ziskej API modes
     *
     * @return string[]
     */
    public function getModes(): array
    {
        return array_keys($this->getUrls());
    }

    /**
     * Check if mode exists
     *
     * @param string $mode
     * @return bool
     */
    public function isMode(string $mode): bool
    {
        return in_array($mode, $this->getModes());
    }

    /**
     * Get current mode
     *
     * @return string
     */
    public function getCurrentMode(): string
    {
        return !empty($this->cookieManager->get('ziskej'))
            ? $this->cookieManager->get('ziskej')
            : $this->defaultMode;
    }

    /**
     * Set mode to cookie
     *
     * @param string $mode
     */
    public function setMode(string $mode): void
    {
        $cookieMode = $this->isMode($mode) ? $mode : self::MODE_DISABLED;
        \setcookie('ziskej', $cookieMode, 0, '/');
    }

    /**
     * Get current base url
     *
     * @return string
     */
    public function getCurrentUrl(): string
    {
        return $this->getUrls()[$this->getCurrentMode()];
    }

    /**
     * Get location of private key file
     *
     * @return string
     * @throws \Exception
     */
    public function getPrivateKeyFileLocation(): string
    {
        $keyFile = $this->config->get('Certs')['ziskej'];

        if (!$keyFile || !is_readable($keyFile)) {
            throw new \Exception('Certificate file to generate token not found');
        }

        return $keyFile;
    }

    /**
     * Get techlib url
     * @return string|null
     */
    public function getZiskejTechlibUrl(): ?string
    {
        return $this->config->get('Ziskej_minimal')['url'];
    }

    /**
     * Get current techlib front url
     * @return string|null
     */
    public function getCurrentZiskejTechlibFrontUrl(): ?string
    {
        $array = $this->config->get('ZiskejTechlibFrontUrl')->toArray();

        if (!isset($array[$this->getCurrentMode()])) {
            return null;
        }

        return $array[$this->getCurrentMode()];
    }

}
