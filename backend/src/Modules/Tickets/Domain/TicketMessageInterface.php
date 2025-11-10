<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain;

interface TicketMessageInterface
{
    public function getId(): string;

    public function getTicketId(): string;

    public function getSenderType(): string;

    public function getSenderId(): ?string;

    public function getSenderName(): ?string;

    public function getContent(): string;

    public function getCreatedAt(): \DateTimeInterface;

    public function getStatus(): ?string;
}
