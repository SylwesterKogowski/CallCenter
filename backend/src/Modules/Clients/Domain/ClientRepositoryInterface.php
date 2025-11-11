<?php

declare(strict_types=1);

namespace App\Modules\Clients\Domain;

/**
 * Storage abstraction for the client aggregate.
 *
 * Provides persistence capabilities required by the client facade services:
 * {@see \App\Modules\Clients\Application\ClientServiceInterface} and
 * {@see \App\Modules\Clients\Application\ClientSearchServiceInterface}.
 */
interface ClientRepositoryInterface
{
    public function findById(string $id): ?ClientInterface;

    public function findByEmail(string $email): ?ClientInterface;

    public function findByPhone(string $phone): ?ClientInterface;

    public function save(ClientInterface $client): void;

    /**
     * @return list<array{client: ClientInterface, matchScore: float|null}>
     */
    public function search(string $query, int $limit): array;
}
