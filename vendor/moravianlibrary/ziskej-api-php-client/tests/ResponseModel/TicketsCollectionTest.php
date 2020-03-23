<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\ResponseModel;

use Mzk\ZiskejApi\TestCase;

final class TicketsCollectionTest extends TestCase
{

    /**
     * @var mixed[][]
     */
    private $input = [
        [
            'ticket_id' => 'abc0000000000001',
            'ticket_type' => 'mvs',
            'hid' => 000001,
            'sigla' => 'BOA001',
            'doc_id' => 'mzk.MZK01-000000001',
            'status_reader' => 'created',
            'is_open' => true,
            'payment_id' => '662d6dcc-50bb-43b0-8fb8-a30854737d62',
            'payment_url' => 'https://ziskej-test.techlib.cz/platebator/662d6dcc-50bb-43b0-8fb8-a30854737d62',
            'date_created' => '2020-01-01',
            'date_requested' => '2020-12-31',
            'date_return' => null,
            'count_messages' => 5,
            'count_messages_unread' => 2,
            'created_datetime' => '2020-01-01T12:32:44+01:00',
            'updated_datetime' => '2020-12-31T15:18:20+01:00',
        ],
        [
            'ticket_id' => 'abc0000000000002',
            'ticket_type' => 'mvs',   // test default value
            'hid' => 000002,
            'sigla' => 'BOA001',
            'doc_id' => 'mzk.MZK01-000000002',
            'status_reader' => 'accepted',
            'is_open' => true,
            'payment_id' => 'f628af4b-4be9-4521-9245-69494c0c670b',
            'payment_url' => 'https://ziskej-test.techlib.cz/platebator/f628af4b-4be9-4521-9245-69494c0c670b',
            'date_created' => '2020-01-01',
            'date_requested' => '2020-12-31',
            'date_return' => '2020-02-28',
            'count_messages' => 1,
            'count_messages_unread' => 0,
            'created_datetime' => '2020-01-01T12:32:44+01:00',
            'updated_datetime' => '2020-12-31T15:18:20+01:00',
        ],
        [
        ],
    ];

    public function testCreateEmptyObject(): void
    {
        $ticketsCollection = new TicketsCollection();
        $tickets = $ticketsCollection->getAll();
        $this->assertEquals([], $tickets);
    }

    public function testCreateFromArray(): void
    {
        $ticketsCollection = TicketsCollection::fromArray($this->input);
        $tickets = $ticketsCollection->getAll();

        $this->assertCount(2, $tickets);
        //@todo more tests
    }

}
