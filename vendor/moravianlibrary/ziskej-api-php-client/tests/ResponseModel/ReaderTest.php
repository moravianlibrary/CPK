<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\ResponseModel;

use Mzk\ZiskejApi\TestCase;

final class ReaderTest extends TestCase
{

    public function testCreate(): void
    {
        $reader = new Reader('ID12345', true, true, true);
        $this->assertSame('ID12345', $reader->getReaderId());
        $this->assertSame(true, $reader->isActive());
        $this->assertSame(true, $reader->isGdprReg());
        $this->assertSame(true, $reader->isGdprData());
    }

    public function testCreateFromArrayMin(): void
    {
        $array = [
            'reader_id' => 'ID12345',
            'is_active' => true,
            'is_gdpr_reg' => true,
            'is_gdpr_data' => true,
        ];

        $reader = Reader::fromArray($array);

        $this->assertInstanceOf(Reader::class, $reader);

        $this->assertSame($array['reader_id'], $reader->getReaderId());
        $this->assertSame($array['is_active'], $reader->isActive());
        $this->assertSame($array['is_gdpr_reg'], $reader->isGdprReg());
        $this->assertSame($array['is_gdpr_data'], $reader->isGdprData());
    }

    public function testCreateFromArrayFull(): void
    {
        $array = [
            'reader_id' => 'ID12345',
            'is_active' => true,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'notification_enabled' => true,
            'sigla' => 'AAA123',
            'is_gdpr_reg' => true,
            'is_gdpr_data' => true,
            'count_tickets' => 10,
            'count_tickets_open' => 6,
            'count_messages' => 20,
            'count_messages_unread' => 12,
        ];

        $reader = Reader::fromArray($array);

        $this->assertInstanceOf(Reader::class, $reader);

        $this->assertSame($array['reader_id'], $reader->getReaderId());
        $this->assertSame($array['is_active'], $reader->isActive());
        $this->assertSame($array['first_name'], $reader->getFirstName());
        $this->assertSame($array['last_name'], $reader->getLastName());
        $this->assertSame($array['email'], $reader->getEmail());
        $this->assertSame($array['notification_enabled'], $reader->isNotificationEnabled());
        $this->assertSame($array['sigla'], $reader->getSigla());
        $this->assertSame($array['sigla'], $reader->getSigla());
        $this->assertSame($array['is_gdpr_reg'], $reader->isGdprReg());
        $this->assertSame($array['is_gdpr_data'], $reader->isGdprData());
        $this->assertSame($array['count_tickets'], $reader->getCountTickets());
        $this->assertSame($array['count_tickets_open'], $reader->getCountTicketsOpen());
        $this->assertSame($array['count_messages'], $reader->getCountMessages());
        $this->assertSame($array['count_messages_unread'], $reader->getCountMessagesUnread());
    }

}
