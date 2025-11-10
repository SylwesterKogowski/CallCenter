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
    public const REQUEST_ATTRIBUTE = '_bff_authenticated_worker';

    public function __construct(
        private RequestStack $requestStack,
        private AuthenticationServiceInterface $authenticationService,
        private AuthorizationServiceInterface $authorizationService,
    ) {
    }

    public function getAuthenticatedWorker(): AuthenticatedWorker
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null !== $request) {
            $cachedWorker = $request->attributes->get(self::REQUEST_ATTRIBUTE);

            if ($cachedWorker instanceof AuthenticatedWorker) {
                return $cachedWorker;
            }
        }

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

        $worker = new AuthenticatedWorker(
            id: $worker->getId(),
            login: $worker->getLogin(),
            isManager: $isManager,
            categoryIds: $categories,
        );

        if (null !== $request) {
            $request->attributes->set(self::REQUEST_ATTRIBUTE, $worker);
        }

        return $worker;
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

        if (!$request->hasSession()) {
            throw new AuthenticationException('Sesja nie została zainicjalizowana');
        }

        /** @var SessionInterface $session */
        $session = $request->getSession();

        return $session;
    }
}
