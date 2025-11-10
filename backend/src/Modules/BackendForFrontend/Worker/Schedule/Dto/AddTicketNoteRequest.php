<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Schedule\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AddTicketNoteRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Treść notatki jest wymagana')]
        #[Assert\Length(
            min: 3,
            max: 5000,
            minMessage: 'Notatka musi zawierać co najmniej 3 znaki',
            maxMessage: 'Notatka może mieć maksymalnie 5000 znaków',
        )]
        public readonly string $content,
    ) {
    }
}


