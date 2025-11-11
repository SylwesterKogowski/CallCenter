<?php

declare(strict_types=1);

namespace App\Modules\Clients\Application;

use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Clients\Infrastructure\Persistence\Doctrine\Entity\Client;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\Tickets\Domain\TicketRepositoryInterface;

final class ClientService implements ClientServiceInterface
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly TicketRepositoryInterface $ticketRepository,
    ) {
    }

    public function getClientById(string $id): ?ClientInterface
    {
        $normalizedId = $this->normalizeId($id);

        return $this->clientRepository->findById($normalizedId);
    }

    public function findClientByEmail(string $email): ?ClientInterface
    {
        $normalizedEmail = $this->normalizeEmail($email);

        return $this->clientRepository->findByEmail($normalizedEmail);
    }

    public function findClientByPhone(string $phone): ?ClientInterface
    {
        $normalizedPhone = $this->normalizePhone($phone);

        return $this->clientRepository->findByPhone($normalizedPhone);
    }

    public function createClient(
        string $id,
        ?string $email = null,
        ?string $phone = null,
        ?string $firstName = null,
        ?string $lastName = null,
    ): ClientInterface {
        $normalizedId = $this->normalizeId($id);

        if (null !== $this->clientRepository->findById($normalizedId)) {
            throw new \InvalidArgumentException(sprintf('Client with id "%s" already exists.', $normalizedId));
        }

        $normalizedEmail = $this->normalizeNullableEmail($email);
        $normalizedPhone = $this->normalizeNullablePhone($phone);
        $normalizedFirstName = $this->normalizeNullableName($firstName);
        $normalizedLastName = $this->normalizeNullableName($lastName);

        if (null !== $normalizedEmail && null !== $this->clientRepository->findByEmail($normalizedEmail)) {
            throw new \InvalidArgumentException(sprintf('Client with email "%s" already exists.', $normalizedEmail));
        }

        $client = new Client(
            $normalizedId,
            $normalizedEmail,
            $normalizedPhone,
            $normalizedFirstName,
            $normalizedLastName,
            $this->now(),
        );

        $this->clientRepository->save($client);

        return $client;
    }

    public function updateClient(
        ClientInterface $client,
        ?string $email = null,
        ?string $phone = null,
        ?string $firstName = null,
        ?string $lastName = null,
    ): ClientInterface {
        $entity = $this->assertClientEntity($client);

        if (null !== $email || null !== $phone) {
            $entity->updateContact(
                $this->normalizeNullableEmail($email) ?? $entity->getEmail(),
                $this->normalizeNullablePhone($phone) ?? $entity->getPhone(),
            );
        }

        if (null !== $firstName || null !== $lastName) {
            $entity->updatePersonalData(
                $this->normalizeNullableName($firstName) ?? $entity->getFirstName(),
                $this->normalizeNullableName($lastName) ?? $entity->getLastName(),
            );
        }

        $this->clientRepository->save($entity);

        return $entity;
    }

    public function identifyClient(
        ClientInterface $client,
        string $email,
        ?string $phone = null,
        ?string $firstName = null,
        ?string $lastName = null,
    ): ClientInterface {
        $entity = $this->assertClientEntity($client);

        $entity->identify(
            $this->normalizeEmail($email),
            $this->normalizeNullablePhone($phone) ?? $entity->getPhone(),
            $this->normalizeNullableName($firstName) ?? $entity->getFirstName(),
            $this->normalizeNullableName($lastName) ?? $entity->getLastName(),
            $this->now(),
        );

        $this->clientRepository->save($entity);

        return $entity;
    }

    public function isClientAnonymous(ClientInterface $client): bool
    {
        return $client->isAnonymous();
    }

    /**
     * @return TicketInterface[]
     */
    public function getClientTickets(ClientInterface $client): array
    {
        return $this->ticketRepository->findTicketsByClient($client->getId());
    }

    private function assertClientEntity(ClientInterface $client): Client
    {
        if ($client instanceof Client) {
            return $client;
        }

        $managed = $this->clientRepository->findById($client->getId());

        if ($managed instanceof Client) {
            return $managed;
        }

        throw new \InvalidArgumentException(sprintf('Client "%s" is not managed by Doctrine context.', $client->getId()));
    }

    private function normalizeId(string $id): string
    {
        $normalized = trim($id);

        if ('' === $normalized) {
            throw new \InvalidArgumentException('Client id cannot be empty.');
        }

        return $normalized;
    }

    private function normalizeEmail(string $email): string
    {
        $normalized = $this->normalizeNullableEmail($email);

        if (null === $normalized) {
            throw new \InvalidArgumentException('Email cannot be empty.');
        }

        return $normalized;
    }

    private function normalizeNullableEmail(?string $email): ?string
    {
        if (null === $email) {
            return null;
        }

        $trimmed = trim($email);

        return '' === $trimmed ? null : mb_strtolower($trimmed);
    }

    private function normalizePhone(string $phone): string
    {
        $normalized = $this->normalizeNullablePhone($phone);

        if (null === $normalized) {
            throw new \InvalidArgumentException('Phone number cannot be empty.');
        }

        return $normalized;
    }

    private function normalizeNullablePhone(?string $phone): ?string
    {
        if (null === $phone) {
            return null;
        }

        $trimmed = preg_replace('/\s+/', '', trim($phone));

        return '' === $trimmed ? null : $trimmed;
    }

    private function normalizeNullableName(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
