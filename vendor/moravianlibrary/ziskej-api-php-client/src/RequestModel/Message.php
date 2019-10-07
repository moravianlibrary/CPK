<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\RequestModel;

class Message
{

    /**
     * Message text
     * @var string
     */
    private $text;

    public function __construct(string $text)
    {
        $this->text = $text;
    }

    /**
     * @return string[]
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
        ];
    }

    public function getText(): string
    {
        return $this->text;
    }

}
