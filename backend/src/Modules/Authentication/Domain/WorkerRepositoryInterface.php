<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Domain;

interface WorkerRepositoryInterface
{
    public function findById(string $id): ?WorkerInterface;

    public function findByLogin(string $login): ?WorkerInterface;

    public function save(WorkerInterface $worker): void;

    public function update(WorkerInterface $worker): void;
}

