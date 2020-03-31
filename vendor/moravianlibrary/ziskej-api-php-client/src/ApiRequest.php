<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi;

class ApiRequest
{

    /**
     * HTTP Method
     * @var string
     */
    protected $method;

    /**
     * URI endpoint
     * @var string
     */
    protected $endpoint;

    /**
     * @var string[]
     */
    protected $urlQuery = [];

    /**
     * URL params
     * @var string[]
     */
    protected $paramsUrl = [];

    /**
     * Data params
     * @var string[]
     */
    protected $paramsData = [];

    /**
     * RequestModel constructor.
     * @param string $method
     * @param string $endpoint
     * @param string[] $urlQuery
     * @param mixed[] $paramsUrl
     * @param string[] $paramsData
     */
    public function __construct(
        string $method,
        string $endpoint,
        array $urlQuery = [],
        array $paramsUrl = [],
        array $paramsData = []
    ) {
        $this->method = $method;
        $this->endpoint = $endpoint;
        $this->urlQuery = $urlQuery;
        $this->paramsUrl = $paramsUrl;
        $this->paramsData = $paramsData;
    }


    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return string[]
     */
    public function getParamsData(): array
    {
        return $this->paramsData;
    }

    public function getUri(): string
    {
        $uri = '';

        if (!empty($this->urlQuery)) {
            $uri .= $this->render($this->endpoint, $this->urlQuery);
        } else {
            $uri .= $this->endpoint;
        }

        $paramsUrl = $this->paramsUrl;
        if (!empty($paramsUrl)) {
            $uri .= '?' . http_build_query($paramsUrl);
        }

        //@todo create url by using Url object

        return $uri;
    }

    /**
     * @param string $string
     * @param string[] $replaces
     * @return string
     */
    private function render(string $string, array $replaces): string
    {
        $search = [];
        $replace = [];
        foreach ($replaces as $key => $value) {
            $search[] = $key;
            $replace[] = $value;
        }
        return str_replace($search, $replace, $string);
    }

}
