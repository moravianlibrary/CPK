<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\Exception;

use Mzk\ZiskejApi\ApiResponse;

class ApiResponseException extends \Exception
{

    public function __construct(ApiResponse $apiResponse)
    {
        parent::__construct(
            sprintf(
                'Ziskej API response error: "%d %s"',
                $apiResponse->getStatusCode(),
                $apiResponse->getReasonPhrase()
            ),
            $apiResponse->getStatusCode(),
            parent::getPrevious()
        );
        //@todo log this exception
    }

}
