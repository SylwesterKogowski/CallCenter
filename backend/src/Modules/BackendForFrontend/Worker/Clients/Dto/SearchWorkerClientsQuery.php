<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Clients\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class SearchWorkerClientsQuery
{
    public function __construct(
        #[Assert\Length(
            max: 255,
            maxMessage: 'Fraza wyszukiwania jest zbyt długa',
        )]
        public readonly ?string $query,
        #[Assert\Positive(message: 'Limit wyników musi być dodatni')]
        #[Assert\LessThanOrEqual(
            value: 100,
            message: 'Limit wyników nie może przekraczać {{ compared_value }}',
        )]
        public readonly int $limit,
    ) {
    }
}
