<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\RequestModel;

class Messages
{

    /**
     * @var bool
     */
    private $read = false;

    public function __construct(bool $read)
    {
        $this->read = $read;
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        return [
            'unread' => !$this->read,   //@todo change api resource param
        ];
    }

}
