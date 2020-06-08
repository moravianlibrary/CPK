<?php declare(strict_types = 1);

namespace Mzk\ZiskejApi\RequestModel;

class Reader
{

    /**
     * Reader first name
     * @var string
     */
    private $firstName;

    /**
     * Reader last name
     * @var string
     */
    private $lastName;

    /**
     * Reader email address
     * @var string //@todo refactor to email object
     */
    private $email;

    /**
     * Library sigla
     * @var string
     */
    private $sigla;

    /**
     * Send notifications
     * @var bool
     */
    private $isNotificationEnabled = true;  // always true

    /**
     * @var bool
     */
    private $isGdprReg;

    /**
     * @var bool
     */
    private $isGdprData;

    /**
     * @var string|null
     */
    private $readerLibraryId;

    /**
     * Reader constructor.
     *
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $sigla
     * @param bool $isGdprReg
     * @param bool $isGdprData
     * @param string|null $readerLibraryId
     *
     * @throws \Mzk\ZiskejApi\Exception\ApiInputException
     */
    public function __construct(
        string $firstName,
        string $lastName,
        string $email,
        string $sigla,
        bool $isGdprReg,
        bool $isGdprData,
        ?string $readerLibraryId = null
    ) {

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Mzk\ZiskejApi\Exception\ApiInputException('Invalid email format');
        }

        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->sigla = $sigla;
        $this->isGdprReg = $isGdprReg;
        $this->isGdprData = $isGdprData;
        $this->readerLibraryId = $readerLibraryId;
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'sigla' => $this->sigla,
            'notification_enabled' => $this->isNotificationEnabled,
            'is_gdpr_reg' => $this->isGdprReg,
            'is_gdpr_data' => $this->isGdprData,
            'reader_library_id' => $this->readerLibraryId,
        ];
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getSigla(): string
    {
        return $this->sigla;
    }

    public function isNotificationEnabled(): bool
    {
        return $this->isNotificationEnabled;
    }

    public function isGdprReg(): bool
    {
        return $this->isGdprReg;
    }

    public function isGdprData(): bool
    {
        return $this->isGdprData;
    }

    public function getReaderLibraryId(): ?string
    {
        return $this->readerLibraryId;
    }

}
