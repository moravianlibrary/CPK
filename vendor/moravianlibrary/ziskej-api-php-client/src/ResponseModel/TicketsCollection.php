<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\ResponseModel;

use SmartEmailing\Types\Arrays;

class TicketsCollection
{

    //@todo rename to TicketCollection

    /**
     * @var \Mzk\ZiskejApi\ResponseModel\Ticket[]
     */
    private $items = [];

    /**
     * @param string[][] $data
     * @return \Mzk\ZiskejApi\ResponseModel\TicketsCollection
     * @throws \Exception
     */
    public static function fromArray(array $data): TicketsCollection
    {
        $self = new self();
        foreach ($data as $subarray) {
            if (Arrays::getArrayOrNull($subarray, true)) {
                $self->addTicket(Ticket::fromArray($subarray));
            }
        }
        return $self;
    }

    public function addTicket(Ticket $ticket): void
    {
        $this->items[] = $ticket;
    }

    /**
     * @return \Mzk\ZiskejApi\ResponseModel\Ticket[]
     */
    public function getAll(): array
    {
        return $this->items;
    }

}
