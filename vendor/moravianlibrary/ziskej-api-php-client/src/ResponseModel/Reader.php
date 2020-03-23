<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\ResponseModel;

use SmartEmailing\Types\PrimitiveTypes;

class Reader
{

    /**
     * Ziskej ID
     *
     * @var string
     */
    private $readerId;

    /**
     * Active in Ziskej
     *
     * @var bool
     */
    private $isActive;

    /**
     * Firstname
     *
     * @var string|null
     */
    private $firstName = null;

    /**
     * Lastname
     * @var string|null
     */
    private $lastName = null;

    /**
     * Email address
     *
     * @var string|null //@todo refactor to email object
     */
    private $email = null;

    /**
     * zda posílat notifikace
     *
     * @var bool|null
     */
    private $isNotificationEnabled = null;

    /**
     * sigla mateřské knihovny
     *
     * @var string|null
     */
    private $sigla = null;

    /**
     * souhlas s registrací
     *
     * @var bool|null
     */
    private $isGdprReg;

    /**
     * souhlas s uložením dat
     *
     * @var bool|null
     */
    private $isGdprData;

    /**
     * Cound of tickets
     *
     * @var int|null
     */
    private $countTickets = null;

    /**
     * Count of open tickets
     *
     * @var int|null
     */
    private $countTicketsOpen = null;

    /**
     * Count of messages
     *
     * @var int|null
     */
    private $countMessages = null;

    /**
     * Count of unread messages
     *
     * @var int|null
     */
    private $countMessagesUnread = null;

    public function __construct(
        string $readerId,
        bool $isActive,
        bool $isGdprReg,
        bool $isGdprData
    ) {
        $this->readerId = $readerId;
        $this->isActive = $isActive;
        $this->isGdprReg = $isGdprReg;
        $this->isGdprData = $isGdprData;
    }


    /**
     * @param mixed[] $data
     * @return \Mzk\ZiskejApi\ResponseModel\Reader
     */
    public static function fromArray(array $data): Reader
    {
        $self = new self(
            PrimitiveTypes::extractString($data, 'reader_id'),
            PrimitiveTypes::extractBool($data, 'is_active'),
            PrimitiveTypes::extractBool($data, 'is_gdpr_reg'),
            PrimitiveTypes::extractBool($data, 'is_gdpr_data')
        );

        $self->firstName = PrimitiveTypes::extractStringOrNull($data, 'first_name', true);
        $self->lastName = PrimitiveTypes::extractStringOrNull($data, 'last_name', true);
        $self->email = PrimitiveTypes::extractStringOrNull($data, 'email', true);
        //@todo make not null:
        $self->isNotificationEnabled
            = PrimitiveTypes::extractBoolOrNull($data, 'notification_enabled', true);
        $self->sigla = PrimitiveTypes::extractStringOrNull($data, 'sigla', true);
        $self->countTickets = PrimitiveTypes::extractIntOrNull($data, 'count_tickets', true);
        $self->countTicketsOpen = PrimitiveTypes::extractIntOrNull($data, 'count_tickets_open', true);
        $self->countMessages = PrimitiveTypes::extractIntOrNull($data, 'count_messages', true);
        $self->countMessagesUnread
            = PrimitiveTypes::extractIntOrNull($data, 'count_messages_unread', true);
        return $self;
    }

    public function getReaderId(): string
    {
        return $this->readerId;
    }

    public function setReaderId(string $readerId): void
    {
        $this->readerId = $readerId;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function isNotificationEnabled(): ?bool
    {
        return $this->isNotificationEnabled;
    }

    public function setIsNotificationEnabled(?bool $isNotificationEnabled): void
    {
        $this->isNotificationEnabled = $isNotificationEnabled;
    }

    public function getSigla(): ?string
    {
        return $this->sigla;
    }

    public function setSigla(?string $sigla): void
    {
        $this->sigla = $sigla;
    }

    public function isGdprReg(): ?bool
    {
        return $this->isGdprReg;
    }

    public function setIsGdprReg(?bool $isGdprReg): void
    {
        $this->isGdprReg = $isGdprReg;
    }

    public function isGdprData(): ?bool
    {
        return $this->isGdprData;
    }

    public function setIsGdprData(?bool $isGdprData): void
    {
        $this->isGdprData = $isGdprData;
    }

    public function getCountTickets(): ?int
    {
        return $this->countTickets;
    }

    public function setCountTickets(?int $countTickets): void
    {
        $this->countTickets = $countTickets;
    }

    public function getCountTicketsOpen(): ?int
    {
        return $this->countTicketsOpen;
    }

    public function setCountTicketsOpen(?int $countTicketsOpen): void
    {
        $this->countTicketsOpen = $countTicketsOpen;
    }

    public function getCountMessages(): ?int
    {
        return $this->countMessages;
    }

    public function setCountMessages(?int $countMessages): void
    {
        $this->countMessages = $countMessages;
    }

    public function getCountMessagesUnread(): ?int
    {
        return $this->countMessagesUnread;
    }

    public function setCountMessagesUnread(?int $countMessagesUnread): void
    {
        $this->countMessagesUnread = $countMessagesUnread;
    }

}
