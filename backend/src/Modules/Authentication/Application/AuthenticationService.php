<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Application;

use App\Modules\Authentication\Domain\Exception\WorkerAlreadyExistsException;
use App\Modules\Authentication\Domain\Worker;
use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Authentication\Domain\WorkerRepositoryInterface;

final class AuthenticationService implements AuthenticationServiceInterface
{
    public function __construct(
        private readonly WorkerRepositoryInterface $workerRepository,
    ) {
    }

    public function registerWorker(string $login, string $password): WorkerInterface
    {
        $normalizedLogin = $this->normalizeLogin($login);

        if (null !== $this->workerRepository->findByLogin($normalizedLogin)) {
            throw new WorkerAlreadyExistsException(sprintf('Worker with login "%s" already exists.', $normalizedLogin));
        }

        $worker = Worker::register($normalizedLogin, $password);
        $this->workerRepository->save($worker);

        return $worker;
    }

    public function authenticateWorker(string $login, string $password): ?WorkerInterface
    {
        $worker = $this->workerRepository->findByLogin($this->normalizeLogin($login));

        if (null === $worker) {
            return null;
        }

        if (!$worker->verifyPassword($password)) {
            return null;
        }

        if (password_needs_rehash($worker->getPasswordHash(), PASSWORD_BCRYPT)) {
            $worker->setPassword($password);
            $this->workerRepository->update($worker);
        }

        return $worker;
    }

    public function getWorkerById(string $id): ?WorkerInterface
    {
        return $this->workerRepository->findById($id);
    }

    public function getWorkerByLogin(string $login): ?WorkerInterface
    {
        return $this->workerRepository->findByLogin($this->normalizeLogin($login));
    }

    public function changePassword(WorkerInterface $worker, string $oldPassword, string $newPassword): void
    {
        $worker->changePassword($oldPassword, $newPassword);
        $this->workerRepository->update($worker);
    }

    private function normalizeLogin(string $login): string
    {
        return trim($login);
    }
}
