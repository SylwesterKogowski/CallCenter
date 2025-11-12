<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Schedule\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AddTicketMessageRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Treść wiadomości jest wymagana')]
        #[Assert\Length(
            min: 1,
            max: 5000,
            minMessage: 'Wiadomość musi zawierać co najmniej 1 znak',
            maxMessage: 'Wiadomość może mieć maksymalnie 5000 znaków',
        )]
        public readonly string $content,
    ) {
    }
}
