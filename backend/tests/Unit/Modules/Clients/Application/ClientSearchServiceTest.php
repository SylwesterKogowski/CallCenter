<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Clients\Application;

use App\Modules\Clients\Application\ClientSearchService;
use App\Modules\Clients\Application\Dto\ClientSearchItemInterface;
use App\Modules\Clients\Application\Dto\ClientSearchResultInterface;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Clients\Infrastructure\Persistence\Doctrine\Entity\Client;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ClientSearchServiceTest extends TestCase
{
    /** @var ClientRepositoryInterface&MockObject */
    private ClientRepositoryInterface $clientRepository;

    private ClientSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $this->service = new ClientSearchService($this->clientRepository);
    }

    public function testEmptyQueryReturnsEmptyResult(): void
    {
        $result = $this->service->searchClients('   ', 10);

        self::assertInstanceOf(ClientSearchResultInterface::class, $result);
        self::assertSame(0, $result->getTotal());
        self::assertSame([], iterator_to_array($result->getClients()));
    }

    public function testSearchReturnsClientItems(): void
    {
        $client = new Client($this->generateId(), 'john.doe@example.com');

        $this->clientRepository
            ->expects(self::once())
            ->method('search')
            ->with('john', 5)
            ->willReturn([
                ['client' => $client, 'matchScore' => 0.9],
            ]);

        $result = $this->service->searchClients(' john ', 5);
        $items = iterator_to_array($result->getClients());

        self::assertSame(1, $result->getTotal());
        self::assertCount(1, $items);
        self::assertInstanceOf(ClientSearchItemInterface::class, $items[0]);
        self::assertSame($client, $items[0]->getClient());
        self::assertSame(0.9, $items[0]->getMatchScore());
    }

    public function testLimitIsCappedAtMaximum(): void
    {
        $this->clientRepository
            ->expects(self::once())
            ->method('search')
            ->with('doe', 100)
            ->willReturn([]);

        $this->service->searchClients('doe', 250);
    }

    private function generateId(): string
    {
        return Uuid::v7()->toRfc4122();
    }
}
