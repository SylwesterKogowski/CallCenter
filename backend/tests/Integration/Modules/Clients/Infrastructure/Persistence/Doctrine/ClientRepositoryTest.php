<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Clients\Infrastructure\Persistence\Doctrine;

use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Clients\Infrastructure\Persistence\Doctrine\Entity\Client;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class ClientRepositoryTest extends KernelTestCase
{
    private ClientRepositoryInterface $repository;

    private EntityManagerInterface $entityManager;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $container = static::getContainer();

        $this->repository = $container->get(ClientRepositoryInterface::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();

        $this->purgeTables();
    }

    public function testSavePersistsClientAndAllowsFetchById(): void
    {
        $client = $this->createClient(
            'john.doe@example.com',
            '+48 100 200 300',
            'John',
            'Doe',
        );

        $this->repository->save($client);
        $this->entityManager->clear();

        $loaded = $this->repository->findById($client->getId());

        self::assertNotNull($loaded);
        self::assertSame($client->getId(), $loaded->getId());
        self::assertSame('john.doe@example.com', $loaded->getEmail());
        self::assertSame('+48100200300', $loaded->getPhone());
        self::assertFalse($loaded->isAnonymous());
        self::assertNotNull($loaded->getIdentifiedAt());
    }

    public function testFindByEmailAndPhoneApplyNormalization(): void
    {
        $client = $this->createClient(
            'jane.smith@example.com',
            '  +48 555 666 777  ',
            'Jane',
            'Smith',
        );

        $this->repository->save($client);
        $this->entityManager->clear();

        $byEmail = $this->repository->findByEmail('JANE.SMITH@EXAMPLE.COM');
        $byPhone = $this->repository->findByPhone('+48 555 666 777');

        self::assertNotNull($byEmail);
        self::assertSame($client->getId(), $byEmail->getId());

        self::assertNotNull($byPhone);
        self::assertSame($client->getId(), $byPhone->getId());
    }

    public function testSearchReturnsLimitedResultsWithMatchScores(): void
    {
        $john = $this->createClient(
            'johnny@example.com',
            '+48 700 800 900',
            'Johnny',
            'Customer',
        );
        $jane = $this->createClient(
            'jane@example.com',
            '+48 600 700 800',
            'Jane',
            'Helper',
        );
        $anonymous = $this->createClient(null, '+48 500 600 700');

        $this->repository->save($john);
        $this->repository->save($jane);
        $this->repository->save($anonymous);
        $this->entityManager->clear();

        $results = $this->repository->search('john', 5);

        self::assertNotEmpty($results);
        self::assertSame($john->getId(), $results[0]['client']->getId());
        self::assertSame(0.8, $results[0]['matchScore']);

        $limited = $this->repository->search('example', 2);
        self::assertCount(2, $limited);

        $noQuery = $this->repository->search('', 10);
        self::assertCount(3, $noQuery);
        self::assertNull($noQuery[0]['matchScore']);
        self::assertInstanceOf(Client::class, $noQuery[0]['client']);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }

        parent::tearDown();

        static::ensureKernelShutdown();
    }

    private function purgeTables(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $this->connection->executeStatement('DELETE FROM tickets');
        $this->connection->executeStatement('DELETE FROM clients');
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        $this->entityManager->clear();
    }

    private function createClient(
        ?string $email,
        ?string $phone,
        ?string $firstName = null,
        ?string $lastName = null,
    ): Client {
        return new Client(
            Uuid::v7()->toRfc4122(),
            $email,
            $phone,
            $firstName,
            $lastName,
        );
    }
}
