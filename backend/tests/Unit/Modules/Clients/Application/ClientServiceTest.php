<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Clients\Application;

use App\Modules\Clients\Application\ClientService;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Clients\Infrastructure\Persistence\Doctrine\Entity\Client;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\Tickets\Domain\TicketRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ClientServiceTest extends TestCase
{
    /** @var ClientRepositoryInterface&MockObject */
    private ClientRepositoryInterface $clientRepository;

    /** @var TicketRepositoryInterface&MockObject */
    private TicketRepositoryInterface $ticketRepository;

    private ClientService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $this->ticketRepository = $this->createMock(TicketRepositoryInterface::class);
        $this->service = new ClientService($this->clientRepository, $this->ticketRepository);
    }

    public function testCreateClientPersistsAndReturnsInstance(): void
    {
        $id = $this->generateId();

        $this->clientRepository
            ->expects(self::once())
            ->method('findById')
            ->with($id)
            ->willReturn(null);

        $this->clientRepository
            ->expects(self::once())
            ->method('findByEmail')
            ->with('john.doe@example.com')
            ->willReturn(null);

        $captured = null;

        $this->clientRepository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (ClientInterface $client) use (&$captured): void {
                $captured = $client;
            });

        $client = $this->service->createClient(
            $id,
            'John.Doe@example.com',
            ' +48 700 800 900 ',
            'john',
            'doe',
        );

        self::assertInstanceOf(Client::class, $client);
        self::assertSame('john.doe@example.com', $client->getEmail());
        self::assertSame('+48700800900', $client->getPhone());
        self::assertSame('John', $client->getFirstName());
        self::assertSame('Doe', $client->getLastName());
        self::assertFalse($client->isAnonymous());
        self::assertSame($client, $captured);
    }

    public function testCreateClientThrowsWhenIdAlreadyExists(): void
    {
        $id = $this->generateId();
        $existing = new Client($id);

        $this->clientRepository
            ->expects(self::once())
            ->method('findById')
            ->with($id)
            ->willReturn($existing);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->createClient($id);
    }

    public function testUpdateClientChangesContactAndPersonalData(): void
    {
        $client = new Client($this->generateId(), 'john.doe@example.com', '+48 111 222 333', 'John', 'Doe');

        $this->clientRepository
            ->expects(self::once())
            ->method('save')
            ->with($client);

        $updated = $this->service->updateClient(
            $client,
            email: ' jane.smith@example.com ',
            phone: ' +48 999 888 777 ',
            firstName: 'jane',
            lastName: 'smith',
        );

        self::assertSame('jane.smith@example.com', $updated->getEmail());
        self::assertSame('+48999888777', $updated->getPhone());
        self::assertSame('Jane', $updated->getFirstName());
        self::assertSame('Smith', $updated->getLastName());
        self::assertFalse($updated->isAnonymous());
    }

    public function testIdentifyClientMarksClientAsNonAnonymous(): void
    {
        $client = new Client($this->generateId(), null, '+48 123 456 789');

        $this->clientRepository
            ->expects(self::once())
            ->method('save')
            ->with($client);

        $identified = $this->service->identifyClient(
            $client,
            'jane.doe@example.com',
            firstName: 'jane',
            lastName: 'doe',
        );

        self::assertFalse($identified->isAnonymous());
        self::assertSame('jane.doe@example.com', $identified->getEmail());
        self::assertSame('Jane Doe', $identified->getFullName());
        self::assertInstanceOf(\DateTimeImmutable::class, $identified->getIdentifiedAt());
    }

    public function testIsClientAnonymousReturnsFlag(): void
    {
        $client = new Client($this->generateId(), null, '+48 555 666 777');

        self::assertTrue($this->service->isClientAnonymous($client));
    }

    public function testGetClientTicketsReturnsRepositoryData(): void
    {
        $client = new Client($this->generateId());
        $ticket = $this->createMock(TicketInterface::class);

        $this->ticketRepository
            ->expects(self::once())
            ->method('findTicketsByClient')
            ->with($client->getId())
            ->willReturn([$ticket]);

        $result = $this->service->getClientTickets($client);

        self::assertSame([$ticket], $result);
    }

    public function testFindClientByEmailNormalizesInput(): void
    {
        $client = new Client($this->generateId(), 'john.doe@example.com');

        $this->clientRepository
            ->expects(self::once())
            ->method('findByEmail')
            ->with('john.doe@example.com')
            ->willReturn($client);

        $result = $this->service->findClientByEmail('  JOHN.DOE@example.com  ');

        self::assertSame($client, $result);
    }

    public function testFindClientByPhoneNormalizesInput(): void
    {
        $client = new Client($this->generateId(), phone: '+48111222333');

        $this->clientRepository
            ->expects(self::once())
            ->method('findByPhone')
            ->with('+48111222333')
            ->willReturn($client);

        $result = $this->service->findClientByPhone(' +48 111 222 333 ');

        self::assertSame($client, $result);
    }

    private function generateId(): string
    {
        return Uuid::v7()->toRfc4122();
    }
}
