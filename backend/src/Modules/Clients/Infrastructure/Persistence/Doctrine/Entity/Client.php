<?php

declare(strict_types=1);

namespace App\Modules\Clients\Infrastructure\Persistence\Doctrine\Entity;

use App\Modules\Clients\Domain\ClientInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'clients')]
#[ORM\UniqueConstraint(name: 'unique_client_email', columns: ['email'])]
#[ORM\Index(name: 'idx_client_email', columns: ['email'])]
#[ORM\Index(name: 'idx_client_phone', columns: ['phone'])]
#[ORM\Index(name: 'idx_client_is_anonymous', columns: ['is_anonymous'])]
#[ORM\Index(name: 'idx_client_created_at', columns: ['created_at'])]
class Client implements ClientInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'string', length: 100, name: 'first_name', nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(type: 'string', length: 100, name: 'last_name', nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(type: 'boolean', name: 'is_anonymous')]
    private bool $isAnonymous = true;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'identified_at', nullable: true)]
    private ?\DateTimeImmutable $identifiedAt = null;

    public function __construct(
        string $id,
        ?string $email = null,
        ?string $phone = null,
        ?string $firstName = null,
        ?string $lastName = null,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        if ('' === $id) {
            throw new \InvalidArgumentException('Client id cannot be empty.');
        }

        $this->id = $id;
        $this->email = $this->normalizeEmail($email);
        $this->phone = $this->normalizePhone($phone);
        $this->firstName = $this->normalizeName($firstName);
        $this->lastName = $this->normalizeName($lastName);
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->isAnonymous = $this->shouldBeAnonymous();

        if (!$this->isAnonymous) {
            $this->identifiedAt = new \DateTimeImmutable();
        }
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
        return $this->isAnonymous;
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
        $this->email = $this->normalizeEmail($email);
        $this->phone = $this->normalizePhone($phone);
        $this->firstName = $this->normalizeName($firstName);
        $this->lastName = $this->normalizeName($lastName);
        $this->isAnonymous = $this->shouldBeAnonymous();
        $this->identifiedAt = $this->isAnonymous
            ? $this->identifiedAt
            : ($identifiedAt ?? new \DateTimeImmutable());

        $this->touch();
    }

    public function updateContact(?string $email = null, ?string $phone = null): void
    {
        $this->email = $this->normalizeEmail($email);
        $this->phone = $this->normalizePhone($phone);
        $this->isAnonymous = $this->shouldBeAnonymous();
        $this->identifiedAt = $this->isAnonymous ? $this->identifiedAt : ($this->identifiedAt ?? new \DateTimeImmutable());

        $this->touch();
    }

    public function updatePersonalData(?string $firstName = null, ?string $lastName = null): void
    {
        $this->firstName = $this->normalizeName($firstName);
        $this->lastName = $this->normalizeName($lastName);
        $this->isAnonymous = $this->shouldBeAnonymous();
        $this->identifiedAt = $this->isAnonymous ? $this->identifiedAt : ($this->identifiedAt ?? new \DateTimeImmutable());

        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function shouldBeAnonymous(): bool
    {
        $hasFullName = null !== $this->firstName && null !== $this->lastName;

        return !$this->hasContactData() || !$hasFullName;
    }

    private function normalizeString(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }

    private function normalizeEmail(?string $value): ?string
    {
        $normalized = $this->normalizeString($value);

        if (null === $normalized) {
            return null;
        }

        $normalized = mb_strtolower($normalized);

        if (false === filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(sprintf('Invalid email address "%s".', $value));
        }

        return $normalized;
    }

    private function normalizePhone(?string $value): ?string
    {
        $normalized = $this->normalizeString($value);

        if (null === $normalized) {
            return null;
        }

        $digitsOnly = preg_replace('/\s+/', '', $normalized);

        if (!preg_match('/^\+?[0-9]{7,15}$/', $digitsOnly)) {
            throw new \InvalidArgumentException(sprintf('Invalid phone number "%s".', $value));
        }

        return $digitsOnly;
    }

    private function normalizeName(?string $value): ?string
    {
        $normalized = $this->normalizeString($value);

        if (null === $normalized) {
            return null;
        }

        $length = mb_strlen($normalized);

        if ($length < 2 || $length > 100) {
            throw new \InvalidArgumentException('Client name parts must be between 2 and 100 characters.');
        }

        if (!preg_match('/^[\p{L}\s\'-]+$/u', $normalized)) {
            throw new \InvalidArgumentException('Client name can only contain letters, spaces, apostrophes and hyphens.');
        }

        return mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
    }
}
