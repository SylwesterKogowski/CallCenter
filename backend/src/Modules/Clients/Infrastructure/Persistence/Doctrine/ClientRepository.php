<?php

declare(strict_types=1);

namespace App\Modules\Clients\Infrastructure\Persistence\Doctrine;

use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Clients\Infrastructure\Persistence\Doctrine\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;

final class ClientRepository implements ClientRepositoryInterface
{
    private const MAX_SEARCH_LIMIT = 100;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findById(string $id): ?ClientInterface
    {
        $client = $this->entityManager->find(Client::class, $id);

        if (!$client instanceof Client) {
            return null;
        }

        return $client;
    }

    public function findByEmail(string $email): ?ClientInterface
    {
        $client = $this->entityManager
            ->getRepository(Client::class)
            ->findOneBy(['email' => $this->normalizeEmail($email)]);

        if (!$client instanceof Client) {
            return null;
        }

        return $client;
    }

    public function findByPhone(string $phone): ?ClientInterface
    {
        $client = $this->entityManager
            ->getRepository(Client::class)
            ->findOneBy(['phone' => $this->normalizePhone($phone)]);

        if (!$client instanceof Client) {
            return null;
        }

        return $client;
    }

    public function save(ClientInterface $client): void
    {
        $entity = $this->assertClientEntity($client);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function search(string $query, int $limit): array
    {
        $limit = max(1, min(self::MAX_SEARCH_LIMIT, $limit));
        $normalizedQuery = $this->normalizeSearchQuery($query);

        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('c')
            ->from(Client::class, 'c')
            ->setMaxResults($limit)
            ->orderBy('c.updatedAt', 'DESC')
            ->addOrderBy('c.createdAt', 'DESC');

        if ('' !== $normalizedQuery) {
            $likePattern = sprintf('%%%s%%', $normalizedQuery);
            $qb
                ->where(
                    $qb->expr()->orX(
                        $qb->expr()->like('LOWER(c.email)', ':query'),
                        $qb->expr()->like('LOWER(c.phone)', ':query'),
                        $qb->expr()->like('LOWER(c.firstName)', ':query'),
                        $qb->expr()->like('LOWER(c.lastName)', ':query'),
                    ),
                )
                ->setParameter('query', $likePattern);
        }

        /** @var Client[] $results */
        $results = $qb->getQuery()->getResult();

        return array_map(
            fn (Client $client): array => [
                'client' => $client,
                'matchScore' => $this->calculateMatchScore($client, $normalizedQuery),
            ],
            $results,
        );
    }

    private function assertClientEntity(ClientInterface $client): Client
    {
        if (!$client instanceof Client) {
            throw new \InvalidArgumentException(sprintf('Expected instance of %s, got %s instead.', Client::class, $client::class));
        }

        return $client;
    }

    private function calculateMatchScore(Client $client, string $normalizedQuery): ?float
    {
        if ('' === $normalizedQuery) {
            return null;
        }

        $score = 0.0;

        $score = max(
            $score,
            $this->matchScalar($client->getEmail(), $normalizedQuery, 1.0),
            $this->matchScalar($client->getPhone(), $normalizedQuery, 0.9),
        );

        $fullName = trim(sprintf(
            '%s %s',
            (string) $client->getFirstName(),
            (string) $client->getLastName(),
        ));

        if ('' !== $fullName) {
            $score = max($score, $this->matchScalar($fullName, $normalizedQuery, 0.8));
        }

        $score = max(
            $score,
            $this->matchScalar($client->getFirstName(), $normalizedQuery, 0.6),
            $this->matchScalar($client->getLastName(), $normalizedQuery, 0.6),
        );

        if (0.0 === $score) {
            return 0.1;
        }

        return round(min($score, 1.0), 2);
    }

    private function matchScalar(?string $value, string $needle, float $weight): float
    {
        if (null === $value) {
            return 0.0;
        }

        $haystack = mb_strtolower(trim($value));

        if ($haystack === $needle) {
            return $weight;
        }

        if (str_contains($haystack, $needle)) {
            return max($weight - 0.2, 0.2);
        }

        return 0.0;
    }

    private function normalizeEmail(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function normalizeSearchQuery(string $query): string
    {
        return trim(mb_strtolower($query));
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/\s+/', '', trim($value));
    }
}
