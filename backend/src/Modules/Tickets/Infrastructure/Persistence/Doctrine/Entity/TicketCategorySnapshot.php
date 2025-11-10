<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity;

use App\Modules\TicketCategories\Domain\TicketCategoryInterface;

/**
 * Immutable category snapshot bound to a ticket record.
 */
final class TicketCategorySnapshot implements TicketCategoryInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly ?string $description,
        private readonly int $defaultResolutionTimeMinutes,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDefaultResolutionTimeMinutes(): int
    {
        return $this->defaultResolutionTimeMinutes;
    }
}
