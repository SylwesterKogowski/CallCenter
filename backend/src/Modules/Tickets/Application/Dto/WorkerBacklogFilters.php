<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Dto;

final class WorkerBacklogFilters
{
    /**
     * @param string[] $categories
     * @param string[] $statuses
     * @param string[] $priorities
     */
    public function __construct(
        private readonly array $categories = [],
        private readonly array $statuses = [],
        private readonly array $priorities = [],
        private readonly ?string $search = null,
        private readonly ?string $sort = null,
    ) {
    }

    /**
     * @return string[]
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @return string[]
     */
    public function getStatuses(): array
    {
        return $this->statuses;
    }

    /**
     * @return string[]
     */
    public function getPriorities(): array
    {
        return $this->priorities;
    }

    public function getSearch(): ?string
    {
        return $this->search;
    }

    public function getSort(): ?string
    {
        return $this->sort;
    }
}


