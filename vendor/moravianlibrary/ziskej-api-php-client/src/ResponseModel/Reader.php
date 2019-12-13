<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\ResponseModel;

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
     * @param mixed[] $input
     * @return \Mzk\ZiskejApi\ResponseModel\Reader
     */
    public static function fromArray(array $input): self
    {
        $model = new self(
            (string)$input['reader_id'],
            (bool)$input['is_active'],
            (bool)$input['is_gdpr_reg'],
            (bool)$input['is_gdpr_data']
        );

        $model->firstName = !empty($input['first_name']) ? (string)$input['first_name'] : null;
        $model->lastName = !empty($input['last_name']) ? (string)$input['last_name'] : null;
        $model->email = !empty($input['email']) ? (string)$input['email'] : null;
        $model->isNotificationEnabled = !empty($input['notification_enabled'])
            ? (bool)$input['notification_enabled'] : null;
        $model->sigla = !empty($input['sigla']) ? (string)$input['sigla'] : null;
        $model->countTickets = !empty($input['count_tickets']) ? (int)$input['count_tickets'] : null;
        $model->countTicketsOpen = !empty($input['count_tickets_open']) ? (int)$input['count_tickets_open'] : null;
        $model->countMessages = !empty($input['count_messages']) ? (int)$input['count_messages'] : null;
        $model->countMessagesUnread = !empty($input['count_messages_unread'])
            ? (int)$input['count_messages_unread'] : null;

        return $model;
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
