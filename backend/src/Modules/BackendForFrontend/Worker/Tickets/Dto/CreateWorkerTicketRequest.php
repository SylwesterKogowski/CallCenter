<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Tickets\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateWorkerTicketRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Kategoria jest wymagana')]
        #[Assert\Length(
            max: 255,
            maxMessage: 'Identyfikator kategorii jest zbyt długi',
        )]
        public readonly string $categoryId,
        #[Assert\Length(
            max: 255,
            maxMessage: 'Tytuł ticketa nie może być dłuższy niż {{ limit }} znaków',
        )]
        public readonly ?string $title,
        #[Assert\Length(
            max: 255,
            maxMessage: 'Identyfikator klienta jest zbyt długi',
        )]
        public readonly ?string $clientId,
        public readonly ?CreateWorkerTicketClientDto $clientData,
    ) {
    }

    public function hasClientReference(): bool
    {
        return null !== $this->clientId || null !== $this->clientData;
    }
}


