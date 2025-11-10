<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Schedule\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AddTicketTimeRequest
{
    public const ALLOWED_TYPES = ['work', 'phone_call'];

    public function __construct(
        #[Assert\NotBlank(message: 'Liczba minut jest wymagana')]
        #[Assert\Type(type: 'integer', message: 'Liczba minut musi być liczbą całkowitą')]
        #[Assert\Positive(message: 'Liczba minut musi być dodatnia')]
        public readonly int $minutes,
        #[Assert\NotBlank(message: 'Typ czasu jest wymagany')]
        #[Assert\Choice(choices: self::ALLOWED_TYPES, message: 'Nieprawidłowy typ czasu')]
        public readonly string $type,
    ) {
    }
}
