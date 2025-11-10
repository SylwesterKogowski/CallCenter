<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tickets\Domain;

use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Domain\Exception\InvalidTicketStatusException;
use App\Modules\Tickets\Domain\Exception\TicketAlreadyClosedException;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\Ticket;
use App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity\TicketRegisteredTime;
use PHPUnit\Framework\TestCase;

final class TicketTest extends TestCase
{
    public function testChangeStatusToValidValue(): void
    {
        $ticket = $this->createTicket();

        $ticket->changeStatus('in_progress');

        self::assertSame('in_progress', $ticket->getStatus());
        self::assertTrue($ticket->isInProgress());
    }

    public function testChangingStatusToClosedRequiresCloseMethod(): void
    {
        $ticket = $this->createTicket();

        $this->expectException(TicketAlreadyClosedException::class);

        $ticket->changeStatus('closed');
    }

    public function testCloseTicketSetsStatusAndClosedBy(): void
    {
        $ticket = $this->createTicket();
        $worker = $this->createWorker('worker-1');
        $closedAt = new \DateTimeImmutable('-1 minute');

        $ticket->close($worker, $closedAt);

        self::assertTrue($ticket->isClosed());
        self::assertSame('closed', $ticket->getStatus());
        self::assertSame('worker-1', $ticket->getClosedByWorkerId());
        self::assertSame($closedAt, $ticket->getClosedAt());
    }

    public function testCannotCloseTicketWithActiveRegisteredTime(): void
    {
        $ticket = $this->createTicket();
        $worker = $this->createWorker('worker-1');

        new TicketRegisteredTime(
            'time-1',
            $ticket,
            $worker->getId(),
            new \DateTimeImmutable('-5 minutes'),
        );

        $this->expectException(\LogicException::class);

        $ticket->close($worker);
    }

    public function testCannotChangeStatusAfterClosure(): void
    {
        $ticket = $this->createTicket();
        $worker = $this->createWorker('worker-1');
        $ticket->close($worker);

        $this->expectException(TicketAlreadyClosedException::class);

        $ticket->changeStatus('awaiting_response');
    }

    public function testInvalidStatusThrowsException(): void
    {
        $ticket = $this->createTicket();

        $this->expectException(InvalidTicketStatusException::class);

        $ticket->changeStatus('invalid');
    }

    private function createTicket(): Ticket
    {
        return new Ticket(
            'ticket-1',
            $this->createClient('client-1'),
            $this->createCategory('category-1'),
            Ticket::STATUS_AWAITING_RESPONSE,
            'Title',
            'Description',
            new \DateTimeImmutable(),
        );
    }

    private function createClient(string $id): ClientInterface
    {
        $client = $this->createStub(ClientInterface::class);
        $client->method('getId')->willReturn($id);
        $client->method('getEmail')->willReturn(null);
        $client->method('getPhone')->willReturn(null);
        $client->method('getFirstName')->willReturn(null);
        $client->method('getLastName')->willReturn(null);

        return $client;
    }

    private function createCategory(string $id): TicketCategoryInterface
    {
        $category = $this->createStub(TicketCategoryInterface::class);
        $category->method('getId')->willReturn($id);
        $category->method('getName')->willReturn('Category');
        $category->method('getDescription')->willReturn(null);
        $category->method('getDefaultResolutionTimeMinutes')->willReturn(30);

        return $category;
    }

    private function createWorker(string $id): WorkerInterface
    {
        $worker = $this->createStub(WorkerInterface::class);
        $worker->method('getId')->willReturn($id);

        return $worker;
    }
}
