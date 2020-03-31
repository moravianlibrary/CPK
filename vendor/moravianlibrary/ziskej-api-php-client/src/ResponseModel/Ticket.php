<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\ResponseModel;

use DateTimeImmutable;
use SmartEmailing\Types\Arrays;
use SmartEmailing\Types\DatesImmutable;
use SmartEmailing\Types\PrimitiveTypes;

class Ticket
{

    /**
     * Ticket id
     *
     * @var string
     */
    private $id;

    /**
     * Ticket type
     *
     * @var string|null
     */
    private $type = null;

    /**
     * Human readable ticket ID
     *
     * @var string|null
     */
    private $hid = null;

    /**
     * Sigla of main library
     *
     * @var string|null
     */
    private $sigla = null;

    /**
     * CPK document ID
     *
     * @var string|null
     */
    private $documentId = null;

    /**
     * Status
     *
     * @var string|null
     */
    private $status = null;

    /**
     * History of ticket statuses
     *
     * @var \Mzk\ZiskejApi\ResponseModel\Status[]
     */
    private $statusHistory = [];

    /**
     * Is ticket open
     *
     * @var bool|null
     */
    private $isOpen = null;

    /**
     * Payment ID
     *
     * @var string|null
     */
    private $paymentId = null;

    /**
     * Link to payment URL
     *
     * @var string|null  //@todo url type
     */
    private $paymentUrl = null;

    /**
     * Created datetime
     *
     * @var \DateTimeImmutable
     */
    private $createdAt;

    /**
     * Last updated datetime
     *
     * @var \DateTimeImmutable|null
     */
    private $updatedAt = null;

    /**
     * Date to return
     *
     * @var \DateTimeImmutable|null
     */
    private $returnAt = null;

    /**
     * Delivery to date
     *
     * @var \DateTimeImmutable|null
     */
    private $requestedAt = null;

    /**
     * Number of ticket's messagess
     * @var int
     */
    private $countMessages = 0;

    /**
     * Number of unread ticket's messagess
     *
     * @var int
     */
    private $countMessagesUnread = 0;

    public function __construct(string $id, DateTimeImmutable $createdAt)
    {
        $this->id = $id;
        $this->createdAt = $createdAt;
    }


    /**
     * @param string[] $data
     * @return \Mzk\ZiskejApi\ResponseModel\Ticket
     *
     * @throws \Exception
     */
    public static function fromArray(array $data): Ticket
    {
        $ticket = new self(
            PrimitiveTypes::extractString($data, 'ticket_id'),
            new DateTimeImmutable(PrimitiveTypes::extractString($data, 'created_datetime'))
        );
        $ticket->type = PrimitiveTypes::extractStringOrNull($data, 'ticket_type', true);
        $ticket->hid = PrimitiveTypes::extractStringOrNull($data, 'hid', true);
        $ticket->sigla = PrimitiveTypes::extractStringOrNull($data, 'sigla', true);
        $ticket->documentId = PrimitiveTypes::extractStringOrNull($data, 'doc_id', true);

        $ticket->status = PrimitiveTypes::extractStringOrNull($data, 'status_reader', true);

        foreach (Arrays::extractArray($data, 'status_reader_history') as $statusHistory) {
            $ticket->statusHistory[] = Status::fromArray($statusHistory);
        }

        $ticket->isOpen = PrimitiveTypes::extractBoolOrNull($data, 'is_open', true);
        $ticket->paymentId = PrimitiveTypes::extractStringOrNull($data, 'payment_id', true);
        $ticket->paymentUrl = PrimitiveTypes::extractStringOrNull($data, 'payment_url', true);

        $ticket->updatedAt = PrimitiveTypes::extractStringOrNull($data, 'updated_datetime', true)
            ? new DateTimeImmutable(PrimitiveTypes::extractStringOrNull($data, 'updated_datetime', true))
            : null;

        $ticket->requestedAt = DatesImmutable::extractOrNull($data, 'date_requested', true);
        $ticket->returnAt = DatesImmutable::extractOrNull($data, 'date_return', true);


        $ticket->countMessages
            = PrimitiveTypes::extractIntOrNull($data, 'count_messages', true)
            ?? 0;
        $ticket->countMessagesUnread
            = PrimitiveTypes::extractIntOrNull($data, 'count_messages_unread', true)
            ?? 0;
        return $ticket;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getHid(): ?string
    {
        return $this->hid;
    }

    public function getSigla(): ?string
    {
        return $this->sigla;
    }

    public function getDocumentId(): ?string
    {
        return $this->documentId;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @return \Mzk\ZiskejApi\ResponseModel\Status[]
     */
    public function getStatusHistory(): array
    {
        return $this->statusHistory;
    }

    public function isOpen(): ?bool
    {
        return $this->isOpen;
    }

    public function getPaymentId(): ?string
    {
        return $this->paymentId;
    }

    public function getPaymentUrl(): ?string
    {
        return $this->paymentUrl;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getReturnAt(): ?DateTimeImmutable
    {
        return $this->returnAt;
    }

    public function getRequestedAt(): ?DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getCountMessages(): int
    {
        return $this->countMessages;
    }

    public function getCountMessagesUnread(): int
    {
        return $this->countMessagesUnread;
    }

}
