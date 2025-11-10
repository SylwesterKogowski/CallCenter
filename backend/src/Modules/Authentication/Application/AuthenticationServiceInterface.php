<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Application;

use App\Modules\Authentication\Domain\WorkerInterface;

interface AuthenticationServiceInterface
{
    public function registerWorker(string $login, string $password): WorkerInterface;

    public function authenticateWorker(string $login, string $password): ?WorkerInterface;

    public function getWorkerById(string $id): ?WorkerInterface;

    public function getWorkerByLogin(string $login): ?WorkerInterface;

    public function changePassword(WorkerInterface $worker, string $oldPassword, string $newPassword): void;
}

