<?php

declare(strict_types=1);

namespace App\Modules\TicketCategories\Application\Stub;

use App\Modules\BackendForFrontend\Shared\Support\NotImplementedDomainServiceTrait;
use App\Modules\TicketCategories\Application\TicketCategoryServiceInterface;

final class TicketCategoryService implements TicketCategoryServiceInterface
{
    use NotImplementedDomainServiceTrait;

    public function getAllCategories(): array
    {
        return $this->notImplemented(__METHOD__);
    }

    public function getCategoriesByIds(array $categoryIds): array
    {
        return $this->notImplemented(__METHOD__);
    }
}
