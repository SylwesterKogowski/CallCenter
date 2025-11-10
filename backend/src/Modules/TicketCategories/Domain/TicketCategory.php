<?php

declare(strict_types=1);

namespace App\Modules\TicketCategories\Domain;

final class TicketCategory implements TicketCategoryInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly ?string $description,
        private readonly int $defaultResolutionTimeMinutes,
    ) {
        if ('' === $this->id) {
            throw new \InvalidArgumentException('Ticket category id cannot be empty.');
        }

        if ('' === $this->name) {
            throw new \InvalidArgumentException('Ticket category name cannot be empty.');
        }

        if ($this->defaultResolutionTimeMinutes <= 0) {
            throw new \InvalidArgumentException('Ticket category default resolution time must be greater than zero.');
        }
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
