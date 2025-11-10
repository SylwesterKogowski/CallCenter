<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Interface\Persistence\Stub;

use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Authentication\Domain\WorkerRepositoryInterface;
use App\Modules\BackendForFrontend\Shared\Support\NotImplementedDomainServiceTrait;

final class WorkerRepository implements WorkerRepositoryInterface
{
    use NotImplementedDomainServiceTrait;

    public function findById(string $id): ?WorkerInterface
    {
        return $this->notImplemented(__METHOD__);
    }

    public function findByLogin(string $login): ?WorkerInterface
    {
        return $this->notImplemented(__METHOD__);
    }

    public function save(WorkerInterface $worker): void
    {
        $this->notImplemented(__METHOD__);
    }

    public function update(WorkerInterface $worker): void
    {
        $this->notImplemented(__METHOD__);
    }
}

