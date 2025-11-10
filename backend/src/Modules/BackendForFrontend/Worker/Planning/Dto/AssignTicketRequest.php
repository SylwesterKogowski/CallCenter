<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Planning\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AssignTicketRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Identyfikator ticketa jest wymagany')]
        #[Assert\Uuid(message: 'Identyfikator ticketa musi być w formacie UUID')]
        public readonly string $ticketId,
        #[Assert\NotBlank(message: 'Data jest wymagana')]
        #[Assert\Regex(
            pattern: '/^\d{4}-\d{2}-\d{2}$/',
            message: 'Data musi być w formacie YYYY-MM-DD',
        )]
        public readonly string $date,
    ) {
    }
}


