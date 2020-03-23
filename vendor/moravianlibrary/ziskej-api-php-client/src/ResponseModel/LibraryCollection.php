<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\ResponseModel;

use SmartEmailing\Types\PrimitiveTypes;

class LibraryCollection
{

    /**
     * @var \Mzk\ZiskejApi\ResponseModel\Library[]
     */
    private $items = [];

    /**
     * @param string[][] $data
     * @return \Mzk\ZiskejApi\ResponseModel\LibraryCollection
     */
    public static function fromArray(array $data): LibraryCollection
    {
        $self = new self();
        foreach ($data as $item) {
            $sigla = PrimitiveTypes::getStringOrNull($item, true);
            if (!empty($sigla)) {
                $self->addLibrary(new Library($sigla));
            }
        }
        return $self;
    }

    public function addLibrary(Library $library): void
    {
        $this->items[$library->getSigla()] = $library;
    }

    /**
     * @return \Mzk\ZiskejApi\ResponseModel\Library[]
     */
    public function getAll(): array
    {
        return $this->items;
    }

    /**
     * Get library by key
     *
     * @param string $key
     * @return \Mzk\ZiskejApi\ResponseModel\Library|null
     */
    public function get(string $key): ?Library
    {
        return $this->items[$key] ?? null;
    }

}
