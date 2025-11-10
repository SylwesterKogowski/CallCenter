<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Phone\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class EndPhoneCallRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Identyfikator połączenia jest wymagany')]
        #[Assert\Length(max: 255, maxMessage: 'Identyfikator połączenia jest zbyt długi')]
        public readonly string $callId,
        public readonly ?string $ticketId,
        #[Assert\NotNull(message: 'Czas trwania jest wymagany')]
        #[Assert\Type(type: 'integer', message: 'Czas trwania musi być liczbą całkowitą')]
        #[Assert\GreaterThanOrEqual(value: 0, message: 'Czas trwania nie może być ujemny')]
        public readonly int $duration,
        #[Assert\NotNull]
        #[Assert\Type('string')]
        #[Assert\Length(max: 5000, maxMessage: 'Notatka jest zbyt długa')]
        public readonly string $notes,
        #[Assert\NotBlank(message: 'Czas rozpoczęcia jest wymagany')]
        #[Assert\DateTime(
            format: DATE_ATOM,
            message: 'Oczekiwany format ISO 8601 (DATE_ATOM) dla czasu rozpoczęcia',
        )]
        public readonly string $startTime,
        #[Assert\NotBlank(message: 'Czas zakończenia jest wymagany')]
        #[Assert\DateTime(
            format: DATE_ATOM,
            message: 'Oczekiwany format ISO 8601 (DATE_ATOM) dla czasu zakończenia',
        )]
        public readonly string $endTime,
    ) {
    }
}
