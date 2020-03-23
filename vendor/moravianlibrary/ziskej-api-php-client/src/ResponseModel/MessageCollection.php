<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\ResponseModel;

use SmartEmailing\Types\Arrays;

class MessageCollection
{

    /**
     * @var \Mzk\ZiskejApi\ResponseModel\Message[]
     */
    private $items = [];

    /**
     * @param string[][] $data
     * @return \Mzk\ZiskejApi\ResponseModel\MessageCollection
     */
    public static function fromArray(array $data): MessageCollection
    {
        $self = new self();
        foreach ($data as $subarray) {
            if (Arrays::getArrayOrNull($subarray, true)) {
                $self->addMessage(Message::fromArray($subarray));
            }
        }
        return $self;
    }

    public function addMessage(Message $message): void
    {
        $this->items[] = $message;
    }

    /**
     * @return \Mzk\ZiskejApi\ResponseModel\Message[]
     */
    public function getAll(): array
    {
        return $this->items;
    }

}
