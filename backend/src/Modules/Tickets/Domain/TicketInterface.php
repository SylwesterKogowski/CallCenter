<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain;

use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;

interface TicketInterface
{
    public function getId(): string;

    public function getClient(): ClientInterface;

    public function getCategory(): TicketCategoryInterface;

    public function getTitle(): ?string;

    public function getDescription(): ?string;

    public function getStatus(): string;

    public function getCreatedAt(): \DateTimeInterface;

    public function getUpdatedAt(): ?\DateTimeInterface;
}

