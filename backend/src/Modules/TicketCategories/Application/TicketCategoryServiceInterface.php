<?php

declare(strict_types=1);

namespace App\Modules\TicketCategories\Application;

use App\Modules\TicketCategories\Domain\TicketCategoryInterface;

interface TicketCategoryServiceInterface
{
    /**
     * @return TicketCategoryInterface[]
     */
    public function getAllCategories(): array;

    /**
     * @param string[] $categoryIds
     *
     * @return TicketCategoryInterface[]
     */
    public function getCategoriesByIds(array $categoryIds): array;
}

