<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\ResponseModel;

use Mzk\ZiskejApi\TestCase;

final class MessagesCollectionTest extends TestCase
{

    /**
     * @var mixed[][]
     */
    private $input = [
        [
            "sender" => "reader",
            "created_datetime" => "2020-02-04T12:32:44+01:00",
            "unread" => false,
            "text" => "čistý text bez formátování s novými řádky typu unix",
        ],
        [
            "sender" => "library_zk",
            "created_datetime" => "2020-02-04T12:32:44+01:00",
            "unread" => true,
            "text" => "Lorem ipsum",
        ],
    ];

    public function testCreateEmptyObject(): void
    {
        $messageCollection = new MessageCollection();
        $message = $messageCollection->getAll();
        $this->assertEquals([], $message);
    }

    public function testCreateFromArray(): void
    {
        $messageCollection = MessageCollection::fromArray($this->input);
        $messages = $messageCollection->getAll();

        $this->assertCount(2, $messages);
        //@todo more tests
    }

}
