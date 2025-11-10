<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Schedule\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateTicketStatusRequest
{
    public const ALLOWED_STATUSES = [
        'waiting',
        'in_progress',
        'completed',
        'awaiting_response',
        'awaiting_client',
        'closed',
    ];

    public function __construct(
        #[Assert\NotBlank(message: 'Status jest wymagany')]
        #[Assert\Choice(choices: self::ALLOWED_STATUSES, message: 'Nieprawidłowy status ticketa')]
        public readonly string $status,
    ) {
    }
}
