<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Event;

interface TicketEventInterface
{
    public function getType(): string;

    public function getTicketId(): string;

    /**
     * Arbitrary payload specific to the event type.
     *
     * @return array<string, mixed>
     */
    public function getData(): array;

    public function getTimestamp(): \DateTimeImmutable;

    public function getWorkerId(): ?string;

    /**
     * @return list<string>
     */
    public function getTopics(): array;
}
