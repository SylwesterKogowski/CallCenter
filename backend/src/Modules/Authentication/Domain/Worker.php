<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Domain;

use App\Modules\Authentication\Domain\Exception\InvalidLoginException;
use App\Modules\Authentication\Domain\Exception\InvalidPasswordException;
use App\Modules\Authentication\Domain\Exception\PasswordMismatchException;
use Symfony\Component\Uid\Uuid;

final class Worker implements WorkerInterface
{
    private string $id;

    private string $login;

    private string $passwordHash;

    private bool $manager;

    private \DateTimeImmutable $createdAt;

    private ?\DateTimeImmutable $updatedAt;

    private function __construct(
        string $id,
        string $login,
        string $passwordHash,
        bool $manager,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ) {
        $this->setId($id);
        $this->assertValidLogin($login);
        $this->login = $login;
        $this->setPasswordHash($passwordHash);
        $this->manager = $manager;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function register(
        string $login,
        string $plainPassword,
        bool $manager = false,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        $createdAt ??= new \DateTimeImmutable();

        $worker = new self(
            Uuid::v7()->toRfc4122(),
            $login,
            self::hashPassword($plainPassword),
            $manager,
            $createdAt,
            null,
        );

        return $worker;
    }

    public static function reconstitute(
        string $id,
        string $login,
        string $passwordHash,
        bool $manager,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $login, $passwordHash, $manager, $createdAt, $updatedAt);
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
        $this->assertValidLogin($login);

        if ($this->login === $login) {
            return;
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
        if ($this->manager) {
            return;
        }

        $this->manager = true;
        $this->touch();
    }

    public function demoteToWorker(): void
    {
        if (!$this->manager) {
            return;
        }

        $this->manager = false;
        $this->touch();
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPassword(string $plainPassword): void
    {
        $this->passwordHash = self::hashPassword($plainPassword);
        $this->touch();
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    public function changePassword(string $oldPassword, string $newPassword): void
    {
        if (!$this->verifyPassword($oldPassword)) {
            throw new PasswordMismatchException('Provided password does not match current password.');
        }

        $this->setPassword($newPassword);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function hashPassword(string $plainPassword): string
    {
        self::assertValidPassword($plainPassword);

        $hash = password_hash($plainPassword, PASSWORD_BCRYPT);

        return $hash;
    }

    private function setId(string $id): void
    {
        if ('' === $id) {
            throw new \InvalidArgumentException('Worker id cannot be empty.');
        }

        $this->id = $id;
    }

    private function setPasswordHash(string $passwordHash): void
    {
        if ('' === $passwordHash) {
            throw new InvalidPasswordException('Password hash cannot be empty.');
        }

        $this->passwordHash = $passwordHash;
    }

    private function assertValidLogin(string $login): void
    {
        $length = mb_strlen($login);
        if ($length < 3 || $length > 255) {
            throw new InvalidLoginException('Login must be between 3 and 255 characters.');
        }

        if (!preg_match('/^[A-Za-z0-9._]+$/', $login)) {
            throw new InvalidLoginException('Login can contain only letters, digits, dots and underscores.');
        }
    }

    private static function assertValidPassword(string $password): void
    {
        if (strlen($password) < 8) {
            throw new InvalidPasswordException('Password must be at least 8 characters long.');
        }
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
