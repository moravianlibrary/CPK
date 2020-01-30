<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\ResponseModel;

use DateTimeImmutable;
use Mzk\ZiskejApi\DateImmutable;

class Ticket
{

    /**
     * Ticket id
     *
     * @var string|null
     */
    private $id = null;

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
     * Ticket creation date
     *
     * @var \DateTimeImmutable|null
     */
    private $dateCreated = null;

    /**
     * Latest delivery date
     *
     * @var \DateTimeImmutable|null
     */
    private $dateRequested = null;

    /**
     * Return date
     *
     * @var \DateTimeImmutable|null
     */
    private $dateReturn = null;

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

    /**
     * @param string[] $item
     * @return \Mzk\ZiskejApi\ResponseModel\Ticket
     */
    public static function fromArray(array $item): Ticket
    {
        $ticket = new self();
        $ticket->id = !empty($item['ticket_id']) ? $item['ticket_id'] : null;
        $ticket->type = !empty($item['ticket_type']) ? $item['ticket_type'] : null;
        $ticket->hid = !empty($item['hid']) ? (string)$item['hid'] : null;
        $ticket->sigla = !empty($item['sigla']) ? $item['sigla'] : null;
        $ticket->documentId = !empty($item['doc_id']) ? $item['doc_id'] : null;
        $ticket->status = !empty($item['status_reader']) ? $item['status_reader'] : null;
        $ticket->isOpen = !empty($item['is_open']) ? (bool)$item['is_open'] : null;
        $ticket->paymentId = !empty($item['payment_id']) ? $item['payment_id'] : null;
        $ticket->paymentUrl = !empty($item['payment_url']) ? $item['payment_url'] : null;
        $ticket->dateCreated = !empty($item['date_created'])
            ? DateImmutable::createFrom($item['date_created'])
            : null;
        $ticket->dateRequested = !empty($item['date_requested'])
            ? DateImmutable::createFrom($item['date_requested'])
            : null;
        $ticket->dateReturn = !empty($item['date_return'])
            ? DateImmutable::createFrom($item['date_return'])
            : null;
        $ticket->countMessages = !empty($item['count_messages']) ? (int)$item['count_messages'] : 0;
        $ticket->countMessagesUnread = !empty($item['count_messages_unread']) ? (int)$item['count_messages_unread'] : 0;
        return $ticket;
    }

    public function getId(): ?string
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

    public function getDateCreated(): ?DateTimeImmutable
    {
        return $this->dateCreated;
    }

    public function getDateRequested(): ?DateTimeImmutable
    {
        return $this->dateRequested;
    }

    public function getDateReturn(): ?DateTimeImmutable
    {
        return $this->dateReturn;
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
