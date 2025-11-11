<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Event;

abstract class AbstractTicketEvent implements TicketEventInterface
{
    /**
     * @param array<string, mixed> $data
     * @param list<string>         $topics
     */
    public function __construct(
        private readonly string $ticketId,
        private readonly array $data = [],
        private readonly \DateTimeImmutable $timestamp = new \DateTimeImmutable(),
        private readonly ?string $workerId = null,
        private readonly array $topics = [],
    ) {
    }

    public function getTicketId(): string
    {
        return $this->ticketId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function getWorkerId(): ?string
    {
        return $this->workerId;
    }

    /**
     * @return list<string>
     */
    public function getTopics(): array
    {
        return $this->topics;
    }
}
