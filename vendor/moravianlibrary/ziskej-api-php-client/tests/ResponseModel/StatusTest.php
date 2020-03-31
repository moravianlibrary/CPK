<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\ResponseModel;

use Mzk\ZiskejApi\TestCase;

final class StatusTest extends TestCase
{

    /**
     * @var string[]
     */
    private $input = [
        "date" => "2020-03-11",
        "id" => "created",
    ];

    public function testCreateFromArray(): void
    {
        $status = Status::fromArray($this->input);

        $this->assertEquals($this->input['date'], $status->getCreatedAt()->format("Y-m-d"));
        $this->assertEquals($this->input['id'], $status->getName());
    }

}
