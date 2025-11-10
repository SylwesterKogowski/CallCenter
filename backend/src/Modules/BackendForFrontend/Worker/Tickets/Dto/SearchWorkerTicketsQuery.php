<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Tickets\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class SearchWorkerTicketsQuery
{
    public function __construct(
        #[Assert\Length(
            max: 255,
            maxMessage: 'Fraza wyszukiwania jest zbyt długa',
        )]
        public readonly ?string $query,
        #[Assert\Length(
            max: 255,
            maxMessage: 'Identyfikator kategorii jest zbyt długi',
        )]
        public readonly ?string $categoryId,
        #[Assert\Length(
            max: 50,
            maxMessage: 'Status ticketa jest zbyt długi',
        )]
        public readonly ?string $status,
        #[Assert\Positive(message: 'Limit wyników musi być dodatni')]
        #[Assert\LessThanOrEqual(
            value: 100,
            message: 'Limit wyników nie może przekraczać {{ compared_value }}',
        )]
        public readonly int $limit,
    ) {
    }
}


