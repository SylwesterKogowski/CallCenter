<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Public\Tickets\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateTicketClientDto
{
    public function __construct(
        #[Assert\Length(max: 255)]
        #[Assert\Email(mode: Assert\Email::VALIDATION_MODE_LOOSE)]
        public ?string $email = null,
        #[Assert\Length(max: 32)]
        public ?string $phone = null,
        #[Assert\Length(max: 64)]
        public ?string $firstName = null,
        #[Assert\Length(max: 64)]
        public ?string $lastName = null,
    ) {
        $this->email = $email !== null ? trim($email) : null;
        $this->phone = $phone !== null ? trim($phone) : null;
        $this->firstName = $firstName !== null ? trim($firstName) : null;
        $this->lastName = $lastName !== null ? trim($lastName) : null;
    }

    public function hasContactData(): bool
    {
        return '' !== (string) $this->email || '' !== (string) $this->phone;
    }
}

