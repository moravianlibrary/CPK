<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\RequestModel;

use DateTimeImmutable;

class Ticket
{

    /**
     * Document ID
     * @var string
     */
    private $documentId;

    /**
     * Requested date
     * @var \DateTimeImmutable|null
     */
    private $dateRequested = null;

    /**
     * Alternative document IDs
     * @var string[]
     */
    private $documentAltIds = [];

    /**
     * Reader's note
     * @var string|null
     */
    private $note = null;

    /**
     * Ticket constructor.
     *
     * @param string $documentId
     */
    public function __construct(string $documentId)
    {
        //@todo accept $dateRequested min today + 3 working days
        $this->documentId = $documentId;
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        $return = [
            'ticket_type' => 'mvs',
            'doc_id' => $this->documentId,
        ];

        if (!empty($this->dateRequested)) {
            $return['date_requested'] = $this->dateRequested->format('Y-m-d');
        }

        if (!empty($this->documentAltIds)) {
            $return['doc_alt_ids'] = $this->documentAltIds;
        }

        if (!empty($this->note)) {
            $return['reader_note'] = $this->note;
        }

        return $return;
    }

    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    public function getDateRequested(): ?DateTimeImmutable
    {
        return $this->dateRequested;
    }

    public function setDateRequested(?DateTimeImmutable $dateRequested): void
    {
        $this->dateRequested = $dateRequested;
    }

    /**
     * @return string[]
     */
    public function getDocumentAltIds(): array
    {
        return $this->documentAltIds;
    }

    /**
     * @param string[] $documentAltIds
     */
    public function setDocumentAltIds(array $documentAltIds): void
    {
        $this->documentAltIds = $documentAltIds;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

}
