<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Interface\Persistence\Doctrine\Entity;

use App\Modules\Authentication\Domain\WorkerInterface;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

#[ORM\Entity]
#[ORM\Table(name: 'workers')]
#[ORM\UniqueConstraint(name: 'unique_login', columns: ['login'])]
#[ORM\Index(name: 'idx_created_at', columns: ['created_at'])]
class Worker implements WorkerInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $login;

    #[ORM\Column(type: 'string', length: 255, name: 'password_hash')]
    private string $passwordHash;

    #[ORM\Column(type: 'boolean', name: 'is_manager')]
    private bool $manager;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at', nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct(
        string $id,
        string $login,
        string $passwordHash,
        bool $manager = false,
        ?DateTimeImmutable $createdAt = null,
    ) {
        if ('' === $id) {
            throw new InvalidArgumentException('Worker id cannot be empty.');
        }

        if ('' === $login) {
            throw new InvalidArgumentException('Worker login cannot be empty.');
        }

        $this->id = $id;
        $this->login = $login;
        $this->passwordHash = $passwordHash;
        $this->manager = $manager;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function setLogin(string $login): void
    {
        if ('' === $login) {
            throw new InvalidArgumentException('Worker login cannot be empty.');
        }

        $this->login = $login;
        $this->touch();
    }

    public function isManager(): bool
    {
        return $this->manager;
    }

    public function promoteToManager(): void
    {
        $this->manager = true;
        $this->touch();
    }

    public function demoteToWorker(): void
    {
        $this->manager = false;
        $this->touch();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function changePasswordHash(string $hash): void
    {
        if ('' === $hash) {
            throw new InvalidArgumentException('Password hash cannot be empty.');
        }

        $this->passwordHash = $hash;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}

