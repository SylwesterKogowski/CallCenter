<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain;

use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;

interface TicketInterface
{
    public const STATUS_CLOSED = 'closed';
    public const STATUS_AWAITING_RESPONSE = 'awaiting_response';
    public const STATUS_AWAITING_CUSTOMER = 'awaiting_customer';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_WAITING = 'waiting';

    public function getId(): string;

    public function getClient(): ClientInterface;

    public function getCategory(): TicketCategoryInterface;

    public function getTitle(): ?string;

    public function getDescription(): ?string;

    public function getStatus(): string;

    public function getCreatedAt(): \DateTimeInterface;

    public function getUpdatedAt(): ?\DateTimeInterface;

    public function getClosedAt(): ?\DateTimeInterface;

    public function getClosedByWorkerId(): ?string;

    public function changeStatus(string $status): void;

    public function close(WorkerInterface $worker, ?\DateTimeImmutable $closedAt = null): void;

    public function isClosed(): bool;

    public function isInProgress(): bool;

    public function isAwaitingResponse(): bool;

    public function isAwaitingCustomer(): bool;

    public function updateDescription(?string $description): void;

    public function updateTitle(?string $title): void;
}
