<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi;

use Http\Client\Common\Plugin\AuthenticationPlugin;
use Http\Client\Common\Plugin\BaseUriPlugin;
use Http\Client\Common\Plugin\LoggerPlugin;
use Http\Client\Common\PluginClient;
use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Http\Message\Authentication;
use Http\Message\Formatter\FullHttpMessageFormatter;
use Http\Message\MultipartStream\MultipartStreamBuilder;
use Psr\Log\LoggerInterface;
use function GuzzleHttp\Psr7\stream_for;

final class ApiClient
{

    /**
     * Base URI of the client
     *
     * @var string|\Psr\Http\Message\UriInterface|null
     */
    private $baseUri = null;

    /**
     * @var \Http\Client\HttpClient
     */
    private $httpClient;

    /**
     * @var \Http\Message\Authentication|null
     */
    private $authentication = null;

    /**
     * @var \Psr\Log\LoggerInterface|null
     */
    private $logger = null;

    /**
     * @var mixed[]
     */
    private $plugins = [];

    public function __construct(
        ?HttpClient $httpClient,
        ?string $baseUri,
        ?Authentication $authentication,
        ?LoggerInterface $logger
    ) {
        $this->baseUri = $baseUri;
        $this->authentication = $authentication;
        $this->logger = $logger;

        // set base uri
        if ($this->baseUri) {
            $this->plugins[] = new BaseUriPlugin(UriFactoryDiscovery::find()->createUri($this->baseUri), [
                'replace' => true,
            ]);
        }

        if ($this->authentication) {
            $this->plugins[] = new AuthenticationPlugin($this->authentication);
        }

        if ($this->logger) {
            $formater = new FullHttpMessageFormatter();
            $this->plugins[] = new LoggerPlugin($this->logger, $formater);
        }

        $this->httpClient = new PluginClient(
            !empty($httpClient) ? $httpClient : HttpClientDiscovery::find(),
            $this->plugins
        );
    }

    /**
     * Send ApiRequest and get ApiResponse
     *
     * @param \Mzk\ZiskejApi\ApiRequest $requestObject
     * @return \Mzk\ZiskejApi\ApiResponse
     *
     * @throws \Http\Client\Exception
     */
    public function sendApiRequest(ApiRequest $requestObject): ApiResponse
    {
        $messageFactory = MessageFactoryDiscovery::find();

        if ($requestObject->getMethod() === 'POST' && !empty($requestObject->getParamsData())) {
            // POST request with form values
            $streamFactory = StreamFactoryDiscovery::find();
            $builder = new MultipartStreamBuilder($streamFactory);
            /**
             * @var string $key
             * @var mixed $val
             */
            foreach ($requestObject->getParamsData() as $key => $val) {
                if (is_array($val)) {
                    //@todo!!! how to send array as post parameter?
                    continue;
                    //$val = json_encode($val);
                }
                $builder->addResource((string)$key, $val);
            }
            $boundary = $builder->getBoundary();
            $headers = [
                'Content-Type' => 'multipart/form-data; boundary="' . $boundary . '"',
            ];
            $body = $builder->build();
        } else {
            // other requests
            $headers = [
                'Content-Type' => 'application/json',
            ];
            $body = stream_for(json_encode($requestObject->getParamsData()));
        }

        $request = $messageFactory->createRequest(
            $requestObject->getMethod(),
            $requestObject->getUri(),
            $headers,
            $body
        );

        $response = $this->httpClient->sendRequest($request);

        return new ApiResponse($response);
    }

}
