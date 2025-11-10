<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Availability\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class TimeSlotPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'Godzina rozpoczęcia jest wymagana')]
        #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'Godzina rozpoczęcia musi być w formacie HH:mm')]
        public readonly string $startTime,
        #[Assert\NotBlank(message: 'Godzina zakończenia jest wymagana')]
        #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'Godzina zakończenia musi być w formacie HH:mm')]
        public readonly string $endTime,
    ) {
    }
}


