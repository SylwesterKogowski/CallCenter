<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Application\Stub;

use App\Modules\Authentication\Application\AuthenticationServiceInterface;
use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\BackendForFrontend\Shared\Support\NotImplementedDomainServiceTrait;

final class AuthenticationService implements AuthenticationServiceInterface
{
    use NotImplementedDomainServiceTrait;

    public function registerWorker(string $login, string $password): WorkerInterface
    {
        return $this->notImplemented(__METHOD__);
    }

    public function authenticateWorker(string $login, string $password): ?WorkerInterface
    {
        return $this->notImplemented(__METHOD__);
    }

    public function getWorkerById(string $id): ?WorkerInterface
    {
        return $this->notImplemented(__METHOD__);
    }

    public function getWorkerByLogin(string $login): ?WorkerInterface
    {
        return $this->notImplemented(__METHOD__);
    }

    public function changePassword(WorkerInterface $worker, string $oldPassword, string $newPassword): void
    {
        $this->notImplemented(__METHOD__);
    }
}


