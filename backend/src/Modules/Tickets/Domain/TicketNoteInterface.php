<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain;

interface TicketNoteInterface
{
    public function getId(): string;

    public function getTicketId(): string;

    public function getWorkerId(): string;

    public function getContent(): string;

    public function getCreatedAt(): \DateTimeInterface;
}


