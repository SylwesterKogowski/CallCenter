<?php

declare(strict_types=1);

namespace App\Modules\Clients\Domain;

interface ClientInterface
{
    public function getId(): string;

    public function getEmail(): ?string;

    public function getPhone(): ?string;

    public function getFirstName(): ?string;

    public function getLastName(): ?string;

    public function getFullName(): ?string;

    public function hasContactData(): bool;

    public function isAnonymous(): bool;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    public function getIdentifiedAt(): ?\DateTimeImmutable;

    public function identify(
        string $email,
        ?string $phone = null,
        ?string $firstName = null,
        ?string $lastName = null,
        ?\DateTimeImmutable $identifiedAt = null,
    ): void;

    public function updateContact(
        ?string $email = null,
        ?string $phone = null,
    ): void;

    public function updatePersonalData(
        ?string $firstName = null,
        ?string $lastName = null,
    ): void;
}
