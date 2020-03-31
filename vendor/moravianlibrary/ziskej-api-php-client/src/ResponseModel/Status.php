<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\ResponseModel;

use DateTimeImmutable;
use SmartEmailing\Types\PrimitiveTypes;

class Status
{

    /**
     * Status created datetime
     *
     * @var \DateTimeImmutable
     */
    private $createdAt;

    /**
     * Status name
     *
     * @var string
     */
    private $name;

    public function __construct(DateTimeImmutable $createdAt, string $name)
    {
        $this->createdAt = $createdAt;
        $this->name = $name;
    }

    /**
     * @param string[] $data
     * @return \Mzk\ZiskejApi\ResponseModel\Status
     *
     * @throws \Exception
     */
    public static function fromArray(array $data): Status
    {
        return new self(
            new DateTimeImmutable(PrimitiveTypes::extractString($data, 'date')),
            PrimitiveTypes::extractString($data, 'id')
        );
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getName(): string
    {
        return $this->name;
    }

}
