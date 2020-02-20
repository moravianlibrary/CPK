<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi;

use DateTimeImmutable;
use Http\Adapter\Guzzle6\Client;
use Http\Message\Authentication\Bearer;
use Monolog\Logger;
use Mzk\ZiskejApi\ResponseModel\TicketsCollection;
use Symfony\Component\Dotenv\Dotenv;

final class ApiTest extends TestCase
{

    /**
     * Test base url
     *
     * @var string
     */
    private $baseUrl = 'https://ziskej-test.techlib.cz:9080/api/v1';
    /**
     * Test eppn of active reader
     * @var string
     */
    private $eppnActive = '1185@mzk.cz';

    /**
     * Test eppn of nonexistent reader
     * @var string
     */
    private $eppnNotExists = '0@mzk.cz';

    /**
     * Test eppn of dDeactivated reader
     * @var string
     */
    private $eppnDeactivated = '1184@mzk.cz';

    /**
     * Document id
     * @var string
     */
    private $docId = 'mzk.MZK01-001579506';

    /**
     * Alternative document ids
     * @var string[]
     */
    private $docAltIds = [
        'caslin.SKC01-007434977',
        'nkp.NKC01-002901834',
    ];

    /**
     * @var string
     */
    private $ticketId = '31d0a0b8dbb74688';

    /**
     * @var string
     */
    private $note = 'This is a note';

    /**
     * @var string
     */
    private $messageText = 'This is my new message';

    /**
     * @var string
     */
    private $date = '2019-12-31';

    /**
     * Test wrong token
     * @var string
     */
    private $tokenWrong = '';

    /**
     * Logger
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new Logger('ZiskejApi');
    }

    /*
     * LOGIN
     */

    public function testApiPostLogin(): void
    {
        $apiClient = new ApiClient(null, $this->baseUrl, null, $this->logger);
        $api = new Api($apiClient);

        $dotEnv = new Dotenv();
        $dotEnv->load(__DIR__ . '/.env');

        $token = $api->login($_ENV['username'], $_ENV['password']);

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    /*
     * LIBRARIES
     */

    public function testApiGetLibrary(): void
    {
        $apiClient = new ApiClient(null, $this->baseUrl, null, $this->logger);
        $api = new Api($apiClient);

        $library = $api->getLibrary('BOA001');

        $this->assertInstanceOf(ResponseModel\Library::class, $library);
    }

    public function testApiGetLibraryNull(): void
    {
        $apiClient = new ApiClient(null, $this->baseUrl, null, $this->logger);
        $api = new Api($apiClient);

        $library = $api->getLibrary('XYZ001');

        $this->assertNull($library);
    }

    public function testApiGetLibraries(): void
    {
        $guzzleClient = Client::createWithConfig([
            'connect_timeout' => 10,
        ]);

        $apiClient = new ApiClient($guzzleClient, $this->baseUrl, null, $this->logger);
        $api = new Api($apiClient);

        $output = $api->getLibraries();

        $this->assertIsArray($output);
        $this->assertNotEmpty($output);
    }

    /*
     * READERS
     */

    public function testApiIsReaderTrue(): void
    {
        $api = ApiFactory::createApi();

        $reader = $api->getReader($this->eppnActive);

        $this->assertInstanceOf(ResponseModel\Reader::class, $reader);

        if ($reader) {
            $this->assertSame(true, $reader->isActive());
        }
    }

    public function testApiIsReaderTrueDeactivated(): void
    {
        $api = ApiFactory::createApi();

        $reader = $api->getReader($this->eppnDeactivated);

        $this->assertInstanceOf(ResponseModel\Reader::class, $reader);

        if ($reader) {
            $this->assertSame(false, $reader->isActive());
        }
    }

    public function testApiIsReaderFalse(): void
    {
        $api = ApiFactory::createApi();

        $reader = $api->getReader($this->eppnNotExists);

        $this->assertNull($reader);
    }

    public function testApiIsReaderActiveTrue(): void
    {
        $api = ApiFactory::createApi();

        $reader = $api->getReader($this->eppnActive);

        if ($reader) {
            $this->assertTrue($reader->isActive());
        } else {
            $this->assertNull($reader);
        }
    }


    public function testApiGetReader200(): void
    {
        $api = ApiFactory::createApi();

        $reader = $api->getReader($this->eppnActive);

        $this->assertInstanceOf(ResponseModel\Reader::class, $reader);

        if ($reader) {
            $this->assertSame(true, $reader->isActive());
        }
    }

    public function testApiGetReader401Unauthorized(): void
    {
        $this->expectException(\Mzk\ZiskejApi\Exception\ApiResponseException::class);
        $this->expectExceptionCode(401);

        $api = new Api(new ApiClient(null, $this->baseUrl, new Bearer($this->tokenWrong), $this->logger));

        $reader = $api->getReader($this->eppnActive);

        $this->assertInstanceOf(ResponseModel\Reader::class, $reader);
    }

    public function testApiGetReader404NotFound(): void
    {
        $api = ApiFactory::createApi();

        $reader = $api->getReader($this->eppnNotExists);

        $this->assertNull($reader);
    }

    public function testApiGetReader422DeactivatedReader(): void
    {
        $api = ApiFactory::createApi();

        $reader = $api->getReader($this->eppnDeactivated);

        $this->assertInstanceOf(ResponseModel\Reader::class, $reader);

        if ($reader) {
            $this->assertSame(false, $reader->isActive());
        }
    }


    public function testApiCreateReader200(): void
    {
        $api = ApiFactory::createApi();

        $inputReader = new RequestModel\Reader(
            'Jakub',
            'Novák',
            'jakub.novak@example.com',
            'BOA001',
            true,
            true
        );

        $outputReader = $api->createReader($this->eppnActive, $inputReader);

        if ($outputReader) {
            $this->assertInstanceOf(ResponseModel\Reader::class, $outputReader);
            $this->assertIsString($outputReader->getReaderId());

            $this->assertSame($inputReader->getEmail(), $outputReader->getEmail());
            $this->assertSame($inputReader->getFirstName(), $outputReader->getFirstName());
            $this->assertSame($inputReader->getLastName(), $outputReader->getLastName());
            $this->assertSame($inputReader->isGdprData(), $outputReader->isGdprData());
            $this->assertSame($inputReader->isGdprReg(), $outputReader->isGdprReg());
            $this->assertSame($inputReader->isNotificationEnabled(), $outputReader->isNotificationEnabled());
            $this->assertSame($inputReader->getSigla(), $outputReader->getSigla());
        } else {
            $this->assertNull($outputReader);
        }
    }

    public function testApiCreateReader422(): void
    {
        $this->expectException(\Mzk\ZiskejApi\Exception\ApiInputException::class);
        $this->expectExceptionCode(422);

        $api = ApiFactory::createApi();

        $reader = new RequestModel\Reader(
            'Jakub',
            'Novák',
            'jakub.novak@example.com',
            'XXX001',
            true,
            true
        );

        $output = $api->createReader($this->eppnActive, $reader);

        $this->assertIsArray($output);
        $this->assertEmpty($output);
    }

    public function testApiCreateReader401(): void
    {
        $this->expectException(\Mzk\ZiskejApi\Exception\ApiResponseException::class);
        $this->expectExceptionCode(401);

        $authentication = new Bearer($this->tokenWrong);
        $apiClient = new ApiClient(null, $this->baseUrl, $authentication, $this->logger);
        $api = new Api($apiClient);

        $reader = new RequestModel\Reader(
            'Jakub',
            'Novák',
            'jakub.novak@example.com',
            'BOA001',
            true,
            true
        );

        $output = $api->createReader($this->eppnActive, $reader);    //@todo

        $this->assertIsArray($output);
        $this->assertEmpty($output);
    }


    public function testApiUpdateReader200(): void
    {
        $api = ApiFactory::createApi();

        $inputReader = new RequestModel\Reader(
            'Jakub',
            'Novák',
            'jakub.novak@example.com',
            'BOA001',
            true,
            true
        );

        $outputReader = $api->updateReader($this->eppnActive, $inputReader);

        if ($outputReader) {
            $this->assertInstanceOf(ResponseModel\Reader::class, $outputReader);
            $this->assertIsString($outputReader->getReaderId());

            $this->assertSame($inputReader->getEmail(), $outputReader->getEmail());
            $this->assertSame($inputReader->getFirstName(), $outputReader->getFirstName());
            $this->assertSame($inputReader->getLastName(), $outputReader->getLastName());
            $this->assertSame($inputReader->isGdprData(), $outputReader->isGdprData());
            $this->assertSame($inputReader->isGdprReg(), $outputReader->isGdprReg());
            $this->assertSame($inputReader->isNotificationEnabled(), $outputReader->isNotificationEnabled());
            $this->assertSame($inputReader->getSigla(), $outputReader->getSigla());
        } else {
            $this->assertNull($outputReader);
        }
    }

    public function testApiUpdateReader422(): void
    {
        $this->expectException(\Mzk\ZiskejApi\Exception\ApiInputException::class);
        $this->expectExceptionCode(422);

        $api = ApiFactory::createApi();

        $reader = new RequestModel\Reader(
            'Jakub',
            'Novák',
            'jakub.novak@example.com',
            'XXX001',
            true,
            true
        );

        $output = $api->updateReader($this->eppnActive, $reader);

        $this->assertIsArray($output);
        $this->assertEmpty($output);
    }

    public function testApiUpdateReader401(): void
    {
        $this->expectException(\Mzk\ZiskejApi\Exception\ApiResponseException::class);
        $this->expectExceptionCode(401);

        $authentication = new Bearer($this->tokenWrong);
        $apiClient = new ApiClient(null, $this->baseUrl, $authentication, $this->logger);
        $api = new Api($apiClient);

        $reader = new RequestModel\Reader(
            'Jakub',
            'Novák',
            'jakub.novak@example.com',
            'BOA001',
            true,
            true
        );

        $output = $api->updateReader($this->eppnActive, $reader);    //@todo

        $this->assertIsArray($output);
        $this->assertEmpty($output);
    }

    /*
     * TICKETS
     */

    public function testApiGetTicketsList(): void
    {
        $api = ApiFactory::createApi();

        $output = $api->getTicketsList($this->eppnActive);

        $this->assertIsArray($output);
    }

    public function testApiGetTicketsDetails(): void
    {
        $api = ApiFactory::createApi();

        $output = $api->getTickets($this->eppnActive);

        $this->assertInstanceOf(TicketsCollection::class, $output);
    }

    public function testApiCreateTicket(): void
    {
        $api = ApiFactory::createApi();

        $ticket = new RequestModel\Ticket($this->docId);

        $output = $api->createTicket($this->eppnActive, $ticket);

        $this->assertIsString($output);
    }

    public function testApiCreateTicketFull(): void
    {
        $api = ApiFactory::createApi();

        $ticket = new RequestModel\Ticket($this->docId);

        $ticket->setDateRequested(new DateTimeImmutable($this->date));
        $ticket->setNote($this->note);
        $ticket->setDocumentAltIds($this->docAltIds);

        $output = $api->createTicket($this->eppnActive, $ticket);

        $this->assertIsString($output);
    }

    public function testApiGetTicket(): void
    {
        $api = ApiFactory::createApi();

        $output = $api->getTicket($this->eppnActive, $this->ticketId);

        $this->assertIsArray($output);
    }

    /*
     * MESSAGES
     */

    public function testApiGetMessages(): void
    {
        $api = ApiFactory::createApi();

        $output = $api->getMessages($this->eppnActive, $this->ticketId);

        $this->assertIsArray($output);
    }

    public function testApiCreateMessage(): void
    {
        $api = ApiFactory::createApi();

        $message = new RequestModel\Message($this->messageText);

        $output = $api->createMessage($this->eppnActive, $this->ticketId, $message);

        $this->assertIsArray($output);
    }

    public function testApiReadMessages(): void
    {
        $api = ApiFactory::createApi();

        $messages = new RequestModel\Messages(true);

        $output = $api->updateMessages($this->eppnActive, $this->ticketId, $messages);

        $this->assertIsArray($output);
    }

}
