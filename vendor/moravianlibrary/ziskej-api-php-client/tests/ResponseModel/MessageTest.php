<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\ResponseModel;

use DateTimeImmutable;
use Mzk\ZiskejApi\TestCase;

final class MessageTest extends TestCase
{

    /**
     * @var mixed[]
     */
    private $input = [
        "sender" => "reader",
        "created_datetime" => "2020-02-04T12:32:44+01:00",
        "unread" => false,
        "text" => "čistý text bez formátování s novými řádky typu unix",
    ];

    public function testCreateFromArray(): void
    {
        $message = Message::fromArray($this->input);

        $this->assertInstanceOf(Message::class, $message);

        $this->assertEquals($this->input['sender'], $message->getSender());
        $this->assertEquals($this->input['unread'], !$message->isRead());
        $this->assertEquals($this->input['created_datetime'], $message->getCreatedAt()->format('Y-m-d\TH:i:sP'));
        $this->assertInstanceOf(DateTimeImmutable::class, $message->getCreatedAt());
        $this->assertEquals($this->input['text'], $message->getText());
    }

    public function testCreateEmpty(): void
    {
        $this->expectException(\SmartEmailing\Types\InvalidTypeException::class);
        Message::fromArray([]);
    }

}
