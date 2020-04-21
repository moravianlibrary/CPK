<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\ResponseModel;

use DateTimeImmutable;
use SmartEmailing\Types\PrimitiveTypes;

class Message
{

    /**
     * @var string
     */
    private $sender;

    /**
     * @var \DateTimeImmutable
     */
    private $createdAt;

    /**
     * @var bool
     */
    private $read;

    /**
     * @var string
     */
    private $text;

    /**
     * Message constructor.
     * @param string $sender
     * @param \DateTimeImmutable $date
     * @param bool $read
     * @param string $text
     */
    public function __construct(string $sender, DateTimeImmutable $date, bool $read, string $text)
    {
        $this->sender = $sender;
        $this->createdAt = $date;
        $this->read = $read;
        $this->text = $text;
    }


    /**
     * @param string[] $data
     * @return \Mzk\ZiskejApi\ResponseModel\Message
     * @throws \Exception
     */
    public static function fromArray(array $data): Message
    {
        return new self(
            PrimitiveTypes::extractString($data, 'sender'),
            new DateTimeImmutable(PrimitiveTypes::extractString($data, 'created_datetime')),
            !PrimitiveTypes::extractBool($data, 'unread'),
            PrimitiveTypes::extractString($data, 'text')
        );
    }

    public function getSender(): string
    {
        return $this->sender;
    }

    public function setSender(string $sender): void
    {
        $this->sender = $sender;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function isRead(): bool
    {
        return $this->read;
    }

    public function setRead(bool $read): void
    {
        $this->read = $read;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

}
