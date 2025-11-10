<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Shared\Security;

use App\Modules\Authentication\Application\AuthenticationServiceInterface;
use App\Modules\Authorization\Application\AuthorizationServiceInterface;
use App\Modules\BackendForFrontend\Shared\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AuthenticatedWorkerProvider
{
    public const SESSION_KEY = 'worker_id';

    public function __construct(
        private RequestStack $requestStack,
        private AuthenticationServiceInterface $authenticationService,
        private AuthorizationServiceInterface $authorizationService,
    ) {
    }

    public function getAuthenticatedWorker(): AuthenticatedWorker
    {
        $session = $this->getSession();
        $workerId = $session->get(self::SESSION_KEY);

        if (!is_string($workerId) || '' === $workerId) {
            throw new AuthenticationException('Brak aktywnej sesji pracownika');
        }

        $worker = $this->authenticationService->getWorkerById($workerId);

        if (null === $worker) {
            throw new AuthenticationException('Pracownik nie istnieje lub sesja wygasła');
        }

        $categories = $this->authorizationService->getAssignedCategoryIds($workerId);
        $isManager = $this->authorizationService->isManager($workerId);

        return new AuthenticatedWorker(
            id: $worker->getId(),
            login: $worker->getLogin(),
            isManager: $isManager,
            categoryIds: $categories,
        );
    }

    public function startSession(string $workerId): void
    {
        $this->getSession()->set(self::SESSION_KEY, $workerId);
    }

    public function clearSession(): void
    {
        $this->getSession()->remove(self::SESSION_KEY);
    }

    private function getSession(): SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            throw new AuthenticationException('Sesja jest niedostępna');
        }

        $session = $request->getSession();

        if (null === $session) {
            throw new AuthenticationException('Sesja nie została zainicjalizowana');
        }

        return $session;
    }
}

