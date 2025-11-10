<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Infrastructure\Persistence\Doctrine\Entity;

use App\Modules\Clients\Domain\ClientInterface;

/**
 * Lightweight immutable client representation stored together with the ticket.
 */
final class TicketClientSnapshot implements ClientInterface
{
    private readonly \DateTimeImmutable $createdAt;

    private readonly ?\DateTimeImmutable $updatedAt;

    private readonly ?\DateTimeImmutable $identifiedAt;

    public function __construct(
        private readonly string $id,
        private readonly ?string $email = null,
        private readonly ?string $phone = null,
        private readonly ?string $firstName = null,
        private readonly ?string $lastName = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $identifiedAt = null,
    ) {
        $this->createdAt = $createdAt ?? new \DateTimeImmutable('@0');
        $this->updatedAt = $updatedAt;
        $this->identifiedAt = $identifiedAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getFullName(): ?string
    {
        $parts = array_filter([$this->firstName, $this->lastName]);

        if ([] === $parts) {
            return null;
        }

        return implode(' ', $parts);
    }

    public function hasContactData(): bool
    {
        return null !== $this->email || null !== $this->phone;
    }

    public function isAnonymous(): bool
    {
        $hasFullName = null !== $this->firstName && null !== $this->lastName;

        return !$this->hasContactData() || !$hasFullName;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getIdentifiedAt(): ?\DateTimeImmutable
    {
        return $this->identifiedAt;
    }

    public function identify(
        string $email,
        ?string $phone = null,
        ?string $firstName = null,
        ?string $lastName = null,
        ?\DateTimeImmutable $identifiedAt = null,
    ): void {
        throw new \BadMethodCallException('Ticket client snapshot is immutable.');
    }

    public function updateContact(
        ?string $email = null,
        ?string $phone = null,
    ): void {
        throw new \BadMethodCallException('Ticket client snapshot is immutable.');
    }

    public function updatePersonalData(
        ?string $firstName = null,
        ?string $lastName = null,
    ): void {
        throw new \BadMethodCallException('Ticket client snapshot is immutable.');
    }
}
