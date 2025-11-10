<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Manager;

use App\Modules\BackendForFrontend\Shared\Exception\AccessDeniedException;
use App\Modules\BackendForFrontend\Shared\Exception\ValidationException;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorker;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use DateTimeImmutable;

/**
 * @property AuthenticatedWorkerProvider $workerProvider
 */
trait ManagerControllerTrait
{
    protected function requireManager(): AuthenticatedWorker
    {
        $worker = $this->workerProvider->getAuthenticatedWorker();

        if (!$worker->isManager()) {
            throw new AccessDeniedException();
        }

        return $worker;
    }

    protected function parseDate(string $value, string $field): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (false === $date || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
            throw new ValidationException(
                sprintf('Nieprawidłowy format daty w polu "%s"', $field),
                [
                    'errors' => [
                        $field => ['Oczekiwano formatu YYYY-MM-DD'],
                    ],
                ],
            );
        }

        return $date;
    }

    protected function flushOutputBuffers(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }

        @ob_flush();
        flush();
    }
}


