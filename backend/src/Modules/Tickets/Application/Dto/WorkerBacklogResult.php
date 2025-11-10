<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Dto;

/**
 * @implements \IteratorAggregate<int, WorkerBacklogTicketInterface>
 */
final class WorkerBacklogResult implements WorkerBacklogResultInterface, \IteratorAggregate
{
    /**
     * @param WorkerBacklogTicketInterface[] $tickets
     */
    public function __construct(
        private readonly array $tickets,
        private readonly int $total,
    ) {
    }

    /**
     * @return iterable<WorkerBacklogTicketInterface>
     */
    public function getTickets(): iterable
    {
        return $this->tickets;
    }

    public function getIterator(): \Traversable
    {
        yield from $this->tickets;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
