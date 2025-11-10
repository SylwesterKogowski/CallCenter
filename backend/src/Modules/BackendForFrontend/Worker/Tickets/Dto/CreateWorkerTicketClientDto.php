<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Tickets\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateWorkerTicketClientDto
{
    public function __construct(
        #[Assert\Length(
            max: 255,
            maxMessage: 'Imię klienta nie może być dłuższe niż {{ limit }} znaków',
        )]
        public readonly ?string $firstName,
        #[Assert\Length(
            max: 255,
            maxMessage: 'Nazwisko klienta nie może być dłuższe niż {{ limit }} znaków',
        )]
        public readonly ?string $lastName,
        #[Assert\Email(message: 'Podaj poprawny adres e-mail klienta')]
        #[Assert\Length(
            max: 255,
            maxMessage: 'Adres e-mail klienta jest zbyt długi',
        )]
        public readonly ?string $email,
        #[Assert\Length(
            max: 50,
            maxMessage: 'Numer telefonu klienta jest zbyt długi',
        )]
        public readonly ?string $phone,
    ) {
    }

    public function hasContactData(): bool
    {
        return null !== $this->email || null !== $this->phone;
    }
}


