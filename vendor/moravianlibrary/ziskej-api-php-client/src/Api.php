<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi;

final class Api
{

    /**
     * @var \Mzk\ZiskejApi\ApiClient
     */
    private $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /*
     * LOGIN
     */

    /**
     * Authenticace API and get access token
     * POST /login
     *
     * @param string $username
     * @param string $password
     * @return string
     *
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiException
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function login(string $username, string $password): string
    {
        $apiResponse = $this->apiClient->sendApiRequest(
            new ApiRequest(
                'POST',
                '/login',
                [],
                [],
                [
                    'username' => $username,
                    'password' => $password,
                ]
            )
        );

        switch ($apiResponse->getStatusCode()) {
            case 200:
                $contents = $apiResponse->getBody()->getContents();
                $data = json_decode($contents, true);
                if (empty($data['token'])) {
                    throw new \Mzk\ZiskejApi\Exception\ApiException(
                        'Ziskej API error: API did not return the token key.'
                    );
                }
                $return = $data['token'];
                break;
            default:
                throw new \Mzk\ZiskejApi\Exception\ApiResponseException($apiResponse);
                break;
        }

        return $return;
    }

    /*
     * LIBRARIES
     */

    /**
     * Get library by sigla
     *
     * @param string $sigla
     * @return \Mzk\ZiskejApi\ResponseModel\Library|null
     *
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function getLibrary(string $sigla): ?ResponseModel\Library
    {
        return in_array($sigla, $this->getLibraries())
            ? new ResponseModel\Library($sigla)
            : null;
    }

    /**
     * List all libraries
     * GET /libraries
     *
     * @return string[]
     *
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function getLibraries(): array
    {
        $apiResponse = $this->apiClient->sendApiRequest(
            new ApiRequest(
                'GET',
                '/libraries'
            )
        );

        switch ($apiResponse->getStatusCode()) {
            case 200:
                $contents = $apiResponse->getBody()->getContents();
                $array = json_decode($contents, true);
                $return = isset($array['items']) || is_array($array['items'])
                    ? $array['items']
                    : [];
                break;
            default:
                throw new \Mzk\ZiskejApi\Exception\ApiResponseException($apiResponse);
                break;
        }

        return $return;
    }

    /*
     * READERS
     */

    /**
     * Get reader detail
     * GET /readers/:eppn
     *
     * @param string $eppn
     * @return \Mzk\ZiskejApi\ResponseModel\Reader|null
     *
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function getReader(string $eppn): ?ResponseModel\Reader
    {
        $apiResponse = $this->apiClient->sendApiRequest(
            new ApiRequest(
                'GET',
                '/readers/:eppn',
                [
                    ':eppn' => $eppn,
                ],
                [
                    'expand' => 'status',
                ]
            )
        );

        switch ($apiResponse->getStatusCode()) {
            case 200:
                $contents = $apiResponse->getBody()->getContents();
                return ResponseModel\Reader::fromArray(json_decode($contents, true));
                break;
            case 404:
                return null;
            default:
                throw new \Mzk\ZiskejApi\Exception\ApiResponseException($apiResponse);
                break;
        }
    }

    /**
     * Create new reader
     *
     * @param string $eppn
     * @param \Mzk\ZiskejApi\RequestModel\Reader $reader
     * @return \Mzk\ZiskejApi\ResponseModel\Reader|null
     *
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiInputException
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function createReader(string $eppn, RequestModel\Reader $reader): ?ResponseModel\Reader
    {
        return $this->updateReader($eppn, $reader);
    }


    /**
     * Create or update reader
     * PUT /readers/:eppn
     *
     * @param string $eppn
     * @param \Mzk\ZiskejApi\RequestModel\Reader $reader
     * @return \Mzk\ZiskejApi\ResponseModel\Reader|null
     *
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiInputException
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function updateReader(string $eppn, RequestModel\Reader $reader): ?ResponseModel\Reader
    {
        $apiResponse = $this->apiClient->sendApiRequest(
            new ApiRequest(
                'PUT',
                '/readers/:eppn',
                [
                    ':eppn' => $eppn,
                ],
                [],
                $reader->toArray()
            )
        );

        switch ($apiResponse->getStatusCode()) {
            case 200:
            case 201:
            case 204:
                return $this->getReader($eppn);
                break;
            case 422:
                // Library is not active
                throw new \Mzk\ZiskejApi\Exception\ApiInputException(
                    sprintf(
                        'Ziskej API input error: Library with sigla "%s" is not active',
                        $reader->getSigla()
                    ),
                    $apiResponse->getStatusCode()
                );
            default:
                throw new \Mzk\ZiskejApi\Exception\ApiResponseException($apiResponse);
                break;
        }
    }

    /*
     * TICKETS
     */

    /**
     * Get tickets for reader
     * GET /readers/:eppn/tickets
     *
     * @param string $eppn
     * @return string[] List of ticket ids
     *
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function getTicketsList(string $eppn): array
    {
        $apiResponse = $this->apiClient->sendApiRequest(
            new ApiRequest(
                'GET',
                '/readers/:eppn/tickets',
                [
                    ':eppn' => $eppn,
                ]
            )
        );

        switch ($apiResponse->getStatusCode()) {
            case 200:
                $contents = $apiResponse->getBody()->getContents();
                $array = json_decode($contents, true);
                $return = isset($array['items']) || is_array($array['items'])
                    ? $array['items']
                    : [];
                break;
            default:
                throw new \Mzk\ZiskejApi\Exception\ApiResponseException($apiResponse);
                break;
        }

        return (array)$return;
    }

    /**
     * Get tickets for reader with details
     * GET /readers/:eppn/tickets
     *
     * @param string $eppn
     * @return string[][] List of tickets with details
     *
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function getTicketsDetails(string $eppn): array
    {
        $apiResponse = $this->apiClient->sendApiRequest(
            new ApiRequest(
                'GET',
                '/readers/:eppn/tickets',
                [
                    ':eppn' => $eppn,
                ],
                [
                    'expand' => 'detail',
                ]
            )
        );

        switch ($apiResponse->getStatusCode()) {
            case 200:
                $contents = $apiResponse->getBody()->getContents();
                $array = json_decode($contents, true);
                $return = isset($array['items']) || is_array($array['items'])
                    ? $array['items']
                    : [];
                break;
            default:
                throw new \Mzk\ZiskejApi\Exception\ApiResponseException($apiResponse);
                break;
        }

        return (array)$return;
    }

    /**
     * Create new ticket for reader
     * POST /readers/:eppn/tickets
     *
     * @param string $eppn
     * @param \Mzk\ZiskejApi\RequestModel\Ticket $ticket
     * @return string
     *
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiException
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function createTicket(string $eppn, RequestModel\Ticket $ticket): string
    {
        $apiResponse = $this->apiClient->sendApiRequest(
            new ApiRequest(
                'POST',
                '/readers/:eppn/tickets',
                [
                    ':eppn' => $eppn,
                ],
                [],
                $ticket->toArray()
            )
        );

        switch ($apiResponse->getStatusCode()) {
            //@todo api should return code 201, but return 200
            case 200:
            case 201:
                $contents = $apiResponse->getBody()->getContents();
                $array = json_decode($contents, true);
                if (empty($array['id'])) {
                    throw new \Mzk\ZiskejApi\Exception\ApiException(
                        'Ziskej API error: API did not return "id" parameter.'
                    );
                }
                $return = $array['id'];
                break;
            default:
                throw new \Mzk\ZiskejApi\Exception\ApiResponseException($apiResponse);
                break;
        }

        return (string)$return;
    }

    /**
     * Ticket detail
     * GET /readers/:eppn/tickets/:ticket_id
     *
     * @param string $eppn
     * @param string $ticketId
     * @return string[] Ticket details
     *
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function getTicket(string $eppn, string $ticketId): array
    {
        $apiResponse = $this->apiClient->sendApiRequest(
            new ApiRequest(
                'GET',
                '/readers/:eppn/tickets/:ticket_id',
                [
                    ':eppn' => $eppn,
                    ':ticket_id' => $ticketId,
                ]
            )
        );

        switch ($apiResponse->getStatusCode()) {
            case 200:
                $contents = $apiResponse->getBody()->getContents();
                $return = json_decode($contents, true);
                break;
            case 404:
                //@todo ticket not found
            default:
                throw new \Mzk\ZiskejApi\Exception\ApiResponseException($apiResponse);
                break;
        }

        return (array)$return;
    }

    /*
     * MESSAGES
     */

    /**
     * Get notes for order
     *
     * @param string $eppn
     * @param string $ticketId
     * @return string[][]
     *
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function getMessages(string $eppn, string $ticketId): array
    {
        $apiResponse = $this->apiClient->sendApiRequest(
            new ApiRequest(
                'GET',
                '/readers/:eppn/tickets/:ticket_id/messages',
                [
                    ':eppn' => $eppn,
                    ':ticket_id' => $ticketId,
                ]
            )
        );

        switch ($apiResponse->getStatusCode()) {
            case 200:
                $contents = $apiResponse->getBody()->getContents();
                $array = json_decode($contents, true);
                $return = isset($array['items']) || is_array($array['items'])
                    ? $array['items']
                    : [];
                break;
            default:
                throw new \Mzk\ZiskejApi\Exception\ApiResponseException($apiResponse);
                break;
        }

        return (array)$return;
    }

    /**
     * Create new note to order
     *
     * @param string $eppn
     * @param string $ticketId
     * @param \Mzk\ZiskejApi\RequestModel\Message $message
     * @return string[]
     *
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function createMessage(string $eppn, string $ticketId, RequestModel\Message $message): array
    {
        $apiResponse = $this->apiClient->sendApiRequest(
            new ApiRequest(
                'POST',
                '/readers/:eppn/tickets/:ticket_id/messages',
                [
                    ':eppn' => $eppn,
                    ':ticket_id' => $ticketId,
                ],
                [],
                $message->toArray()
            )
        );

        switch ($apiResponse->getStatusCode()) {
            case 201:
                $contents = $apiResponse->getBody()->getContents();
                $array = json_decode($contents, true);
                $return = $array;
                break;
            default:
                throw new \Mzk\ZiskejApi\Exception\ApiResponseException($apiResponse);
                break;
        }

        return (array)$return;
    }

    /**
     * Set messages as read
     *
     * @param string $eppn
     * @param string $ticketId
     * @param \Mzk\ZiskejApi\RequestModel\Messages $messages
     * @return string[]
     *
     * @throws \Http\Client\Exception
     * @throws \Mzk\ZiskejApi\Exception\ApiResponseException
     */
    public function updateMessages(string $eppn, string $ticketId, RequestModel\Messages $messages): array
    {
        $apiResponse = $this->apiClient->sendApiRequest(
            new ApiRequest(
                'PUT',
                '/readers/:eppn/tickets/:ticket_id/messages',
                [
                    ':eppn' => $eppn,
                    ':ticket_id' => $ticketId,
                ],
                [],
                $messages->toArray()
            )
        );

        switch ($apiResponse->getStatusCode()) {
            case 200:
                $contents = $apiResponse->getBody()->getContents();
                $array = json_decode($contents, true);
                $return = $array;
                break;
            default:
                throw new \Mzk\ZiskejApi\Exception\ApiResponseException($apiResponse);
                break;
        }

        return (array)$return;
    }

}
