<?php

declare(strict_types=1);

namespace App\Modules\TicketCategories\Domain;

interface TicketCategoryInterface
{
    public function getId(): string;

    public function getName(): string;

    public function getDescription(): ?string;

    public function getDefaultResolutionTimeMinutes(): int;
}
