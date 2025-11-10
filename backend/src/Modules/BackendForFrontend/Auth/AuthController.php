<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Auth;

use App\Modules\Authentication\Application\AuthenticationServiceInterface;
use App\Modules\Authorization\Application\AuthorizationServiceInterface;
use App\Modules\BackendForFrontend\Auth\Dto\LoginRequest;
use App\Modules\BackendForFrontend\Auth\Dto\RegisterWorkerRequest;
use App\Modules\BackendForFrontend\Shared\AbstractJsonController;
use App\Modules\BackendForFrontend\Shared\Exception\AccessDeniedException;
use App\Modules\BackendForFrontend\Shared\Exception\AuthenticationException;
use App\Modules\BackendForFrontend\Shared\Exception\ValidationException;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use App\Modules\TicketCategories\Application\TicketCategoryServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(path: '/api/auth', name: 'backend_for_frontend_auth_')]
class AuthController extends AbstractJsonController
{
    private const SESSION_TOKEN_KEY = 'worker_session_token';

    public function __construct(
        ValidatorInterface $validator,
        #[Autowire('%kernel.debug%')]
        bool $debug,
        private AuthenticationServiceInterface $authenticationService,
        private AuthorizationServiceInterface $authorizationService,
        private TicketCategoryServiceInterface $ticketCategoryService,
        private AuthenticatedWorkerProvider $workerProvider,
    ) {
        parent::__construct($validator, $debug);
    }

    #[Route(path: '/register', name: 'register', methods: [Request::METHOD_POST])]
    public function register(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $payload = $this->getJsonBody($request);
            $isManagerError = $this->validateIsManagerFlag($payload);
            $dto = $this->hydrateRegisterRequest($payload);

            try {
                $this->validateDto($dto);
            } catch (ValidationException $exception) {
                $errors = $exception->getErrors();

                if (null !== $isManagerError) {
                    $errors['isManager'][] = $isManagerError;
                }

                throw new ValidationException(errors: $errors);
            }

            if (null !== $isManagerError) {
                throw new ValidationException(errors: ['isManager' => [$isManagerError]]);
            }

            if ($dto->isManager) {
                $worker = $this->workerProvider->getAuthenticatedWorker();

                if (!$worker->isManager()) {
                    throw new AccessDeniedException();
                }
            }

            $categories = $this->ticketCategoryService->getCategoriesByIds($dto->categoryIds);

            if (count($categories) !== count(array_unique($dto->categoryIds))) {
                throw new ValidationException('Niektóre kategorie nie istnieją', ['categoryIds' => ['Część podanych kategorii nie została odnaleziona']]);
            }

            $worker = $this->authenticationService->registerWorker(
                $dto->login,
                $dto->password,
            );

            $this->authorizationService->assignCategoriesToWorker($worker->getId(), $dto->categoryIds);

            if ($dto->isManager) {
                $this->authorizationService->setManagerRole($worker->getId());
            }

            $categoryPayload = array_map(
                static fn ($category): array => [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                ],
                $categories,
            );

            return [
                'worker' => [
                    'id' => $worker->getId(),
                    'login' => $worker->getLogin(),
                    'isManager' => $dto->isManager,
                    'createdAt' => $worker->getCreatedAt()->format(DATE_ATOM),
                ],
                'categories' => $categoryPayload,
            ];
        }, Response::HTTP_CREATED);
    }

    #[Route(path: '/login', name: 'login', methods: [Request::METHOD_POST])]
    public function login(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateLoginRequest($payload);
            $this->validateDto($dto);

            $worker = $this->authenticationService->authenticateWorker(
                $dto->login,
                $dto->password,
            );

            if (null === $worker) {
                throw new AuthenticationException('Nieprawidłowy login lub hasło');
            }

            $expiresAt = new \DateTimeImmutable('+1 hour');
            $token = bin2hex(random_bytes(32));

            if (!$request->hasSession()) {
                throw new AuthenticationException('Sesja jest niedostępna');
            }

            $session = $request->getSession();

            if (!$session->isStarted()) {
                $session->start();
            }

            $session->set(self::SESSION_TOKEN_KEY, [
                'token' => $token,
                'expires_at' => $expiresAt,
            ]);

            $this->workerProvider->startSession($worker->getId());

            return [
                'worker' => [
                    'id' => $worker->getId(),
                    'login' => $worker->getLogin(),
                    'createdAt' => $worker->getCreatedAt()->format(DATE_ATOM),
                ],
                'session' => [
                    'token' => $token,
                    'expiresAt' => $expiresAt->format(DATE_ATOM),
                ],
            ];
        });
    }

    #[Route(path: '/logout', name: 'logout', methods: [Request::METHOD_POST])]
    public function logout(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            if (!$request->hasSession()) {
                return [
                    'message' => 'Sesja została już zakończona',
                ];
            }

            $session = $request->getSession();

            if (!$session->isStarted()) {
                $session->start();
            }

            $session->remove(self::SESSION_TOKEN_KEY);
            $this->workerProvider->clearSession();
            $session->invalidate();

            return [
                'message' => 'Wylogowano pomyślnie',
            ];
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateRegisterRequest(array $payload): RegisterWorkerRequest
    {
        $categoryIds = [];

        if (isset($payload['categoryIds']) && is_array($payload['categoryIds'])) {
            $categoryIds = array_map('strval', $payload['categoryIds']);
        }

        return new RegisterWorkerRequest(
            login: (string) ($payload['login'] ?? ''),
            password: (string) ($payload['password'] ?? ''),
            categoryIds: $categoryIds,
            isManager: (bool) ($payload['isManager'] ?? false),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateLoginRequest(array $payload): LoginRequest
    {
        return new LoginRequest(
            login: (string) ($payload['login'] ?? ''),
            password: (string) ($payload['password'] ?? ''),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateIsManagerFlag(array $payload): ?string
    {
        if (!array_key_exists('isManager', $payload)) {
            return null;
        }

        if (!is_bool($payload['isManager'])) {
            return 'Pole isManager musi być wartością logiczną';
        }

        return null;
    }
}
