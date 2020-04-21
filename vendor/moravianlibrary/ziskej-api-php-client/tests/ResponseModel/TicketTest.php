<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\ResponseModel;

use DateTimeImmutable;
use Mzk\ZiskejApi\TestCase;

final class TicketTest extends TestCase
{

    /**
     * @var mixed[]
     */
    private $input = [
        'ticket_id' => 'abc0000000000001',
        'ticket_type' => 'mvs',
        'hid' => 000001,
        'sigla' => 'BOA001',
        'doc_id' => 'mzk.MZK01-000000001',
        'status_reader' => 'created',
        'status_reader_history' => [
            [
                "date" => "2020-03-09",
                "id" => "created",
            ],
            [
                "date" => "2020-03-10",
                "id" => "accepted",
            ],
        ],
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
    ];

    public function testCreateEmptyObject(): void
    {
        $ticket = new Ticket('ticket_1', new DateTimeImmutable('2019-12-31 13:30:00'));

        $this->assertEquals('ticket_1', $ticket->getId());
        $this->assertEquals('2019-12-31 13:30:00', $ticket->getCreatedAt()->format('Y-m-d H:i:s'));

        $this->assertNull($ticket->getType());
        $this->assertNull($ticket->getHid());
        $this->assertNull($ticket->getSigla());
        $this->assertNull($ticket->getDocumentId());
        $this->assertNull($ticket->getStatus());
        $this->assertNull($ticket->isOpen());
        $this->assertNull($ticket->getPaymentId());
        $this->assertNull($ticket->getPaymentUrl());
        $this->assertNull($ticket->getRequestedAt());
        $this->assertNull($ticket->getReturnAt());
        $this->assertEquals(0, $ticket->getCountMessages());
        $this->assertEquals(0, $ticket->getCountMessagesUnread());
    }

    public function testCreateFromArray(): void
    {
        $ticket = Ticket::fromArray($this->input);

        $this->assertEquals($this->input['ticket_id'], $ticket->getId());
        $this->assertEquals($this->input['ticket_type'], $ticket->getType());
        $this->assertEquals($this->input['hid'], $ticket->getHid());
        $this->assertEquals($this->input['sigla'], $ticket->getSigla());
        $this->assertEquals($this->input['doc_id'], $ticket->getDocumentId());
        $this->assertEquals($this->input['status_reader'], $ticket->getStatus());
        //@todo test $ticket->getStatusHistory();
        $this->assertEquals($this->input['is_open'], $ticket->isOpen());
        $this->assertEquals($this->input['payment_id'], $ticket->getPaymentId());
        $this->assertEquals($this->input['payment_url'], $ticket->getPaymentUrl());

        $this->assertEquals($this->input['created_datetime'], $ticket->getCreatedAt()->format("Y-m-d\TH:i:sP"));

        if (!empty($ticket->getUpdatedAt())) {
            $this->assertEquals($this->input['updated_datetime'], $ticket->getUpdatedAt()->format("Y-m-d\TH:i:sP"));
        }

        if (!empty($ticket->getRequestedAt())) {
            $this->assertEquals($this->input['date_requested'], $ticket->getRequestedAt()->format('Y-m-d'));
        }

        if (!empty($ticket->getCreatedAt())) {
            $this->assertEquals($this->input['date_created'], $ticket->getCreatedAt()->format('Y-m-d'));
        }

        if (!empty($ticket->getRequestedAt())) {
            $this->assertEquals($this->input['date_requested'], $ticket->getRequestedAt()->format('Y-m-d'));
        }

        $this->assertEquals($this->input['date_return'], null);

        $this->assertEquals($this->input['count_messages'], $ticket->getCountMessages());
        $this->assertEquals($this->input['count_messages_unread'], $ticket->getCountMessagesUnread());
    }

}
