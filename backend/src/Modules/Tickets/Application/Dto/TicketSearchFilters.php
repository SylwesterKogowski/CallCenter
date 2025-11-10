<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Dto;

final class TicketSearchFilters
{
    public function __construct(
        public readonly ?string $query,
        public readonly ?string $categoryId,
        public readonly ?string $status,
        public readonly int $limit,
    ) {
    }
}


