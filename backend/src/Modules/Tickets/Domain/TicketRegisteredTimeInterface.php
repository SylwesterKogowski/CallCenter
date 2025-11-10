<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain;

interface TicketRegisteredTimeInterface
{
    public function getId(): string;

    public function getTicketId(): string;

    public function getWorkerId(): string;

    public function getStartedAt(): \DateTimeInterface;

    public function getEndedAt(): ?\DateTimeInterface;

    public function getDurationMinutes(): ?int;

    public function isPhoneCall(): bool;

    public function markAsPhoneCall(): void;

    public function end(\DateTimeImmutable $endedAt, ?int $durationMinutes = null): void;

    public function isActive(): bool;
}
