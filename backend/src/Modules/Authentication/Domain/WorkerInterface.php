<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Domain;

interface WorkerInterface
{
    public function getId(): string;

    public function getLogin(): string;

    public function setLogin(string $login): void;

    public function isManager(): bool;

    public function promoteToManager(): void;

    public function demoteToWorker(): void;

    public function getPasswordHash(): string;

    public function setPassword(string $plainPassword): void;

    public function verifyPassword(string $plainPassword): bool;

    public function changePassword(string $oldPassword, string $newPassword): void;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getUpdatedAt(): ?\DateTimeImmutable;
}
