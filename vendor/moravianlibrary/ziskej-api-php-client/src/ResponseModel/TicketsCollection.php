<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\ResponseModel;

class TicketsCollection
{

    /**
     * @var \Mzk\ZiskejApi\ResponseModel\Ticket[]
     */
    private $items = [];

    /**
     * @param string[][] $array
     * @return \Mzk\ZiskejApi\ResponseModel\TicketsCollection
     */
    public static function fromArray(array $array): TicketsCollection
    {
        $tickets = new self();
        foreach ($array as $subarray) {
            $tickets->addTicket(Ticket::fromArray($subarray));
        }
        return $tickets;
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
