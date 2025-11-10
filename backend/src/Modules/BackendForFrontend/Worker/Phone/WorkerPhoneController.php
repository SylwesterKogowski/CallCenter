<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Phone;

use App\Modules\BackendForFrontend\Shared\AbstractJsonController;
use App\Modules\BackendForFrontend\Shared\Exception\AccessDeniedException;
use App\Modules\BackendForFrontend\Shared\Exception\ValidationException;
use App\Modules\BackendForFrontend\Shared\Security\Attribute\RequiresWorker;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorker;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use App\Modules\BackendForFrontend\Worker\Phone\Dto\EndPhoneCallRequest;
use App\Modules\BackendForFrontend\Worker\Phone\Dto\StartPhoneCallRequest;
use App\Modules\BackendForFrontend\Worker\Phone\Service\WorkerPhoneServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[RequiresWorker]
#[Route(path: '/api/worker/phone', name: 'backend_for_frontend_worker_phone_')]
final class WorkerPhoneController extends AbstractJsonController
{
    public function __construct(
        ValidatorInterface $validator,
        #[Autowire('%kernel.debug%')]
        bool $debug,
        private readonly AuthenticatedWorkerProvider $workerProvider,
        private readonly WorkerPhoneServiceInterface $phoneService,
    ) {
        parent::__construct($validator, $debug);
    }

    #[Route(path: '/receive', name: 'receive', methods: [Request::METHOD_POST])]
    public function startCall(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $worker = $this->requireWorker();
            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateStartCallRequest($payload);
            $this->validateDto($dto);

            if ($dto->workerId !== $worker->getId()) {
                throw new AccessDeniedException('Brak uprawnień do rozpoczęcia połączenia', ['workerId' => $dto->workerId]);
            }

            $result = $this->phoneService->startCall($worker->getId());

            return $this->normalizeStartCallResult($result);
        }, Response::HTTP_CREATED);
    }

    #[Route(path: '/end', name: 'end', methods: [Request::METHOD_POST])]
    public function endCall(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $worker = $this->requireWorker();
            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateEndCallRequest($payload);
            $this->validateDto($dto);

            $ticketId = $this->normalizeTicketId($dto->ticketId);
            $startTime = $this->parseDateTime($dto->startTime, 'startTime');
            $endTime = $this->parseDateTime($dto->endTime, 'endTime');

            if ($endTime < $startTime) {
                throw new ValidationException('Czas zakończenia nie może być wcześniejszy niż czas rozpoczęcia', ['endTime' => ['Czas zakończenia nie może być wcześniejszy niż czas rozpoczęcia']]);
            }

            $notes = '' !== trim($dto->notes) ? $dto->notes : null;

            $result = $this->phoneService->endCall(
                $worker->getId(),
                $dto->callId,
                $ticketId,
                $dto->duration,
                $notes,
                $startTime,
                $endTime,
            );

            return $this->normalizeEndCallResult($result);
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateStartCallRequest(array $payload): StartPhoneCallRequest
    {
        return new StartPhoneCallRequest(
            workerId: (string) ($payload['workerId'] ?? ''),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateEndCallRequest(array $payload): EndPhoneCallRequest
    {
        $ticketId = $payload['ticketId'] ?? null;

        return new EndPhoneCallRequest(
            callId: (string) ($payload['callId'] ?? ''),
            ticketId: null === $ticketId ? null : (string) $ticketId,
            duration: $this->normalizeDuration($payload['duration'] ?? null),
            notes: (string) ($payload['notes'] ?? ''),
            startTime: (string) ($payload['startTime'] ?? ''),
            endTime: (string) ($payload['endTime'] ?? ''),
        );
    }

    /**
     * @param array{
     *     callId: string,
     *     startTime: \DateTimeInterface|string,
     *     pausedTickets: iterable<array{
     *         ticketId: string,
     *         previousStatus: string,
     *         newStatus: string
     *     }>
     * } $result
     *
     * @return array{
     *     callId: string,
     *     startTime: string,
     *     pausedTickets: list<array{
     *         ticketId: string,
     *         previousStatus: string,
     *         newStatus: string
     *     }>
     * }
     */
    private function normalizeStartCallResult(array $result): array
    {
        $pausedTickets = [];

        foreach ($result['pausedTickets'] as $ticket) {
            $pausedTickets[] = [
                'ticketId' => (string) $ticket['ticketId'],
                'previousStatus' => (string) $ticket['previousStatus'],
                'newStatus' => (string) $ticket['newStatus'],
            ];
        }

        return [
            'callId' => (string) $result['callId'],
            'startTime' => $this->formatDateTime($result['startTime']),
            'pausedTickets' => $pausedTickets,
        ];
    }

    /**
     * @param array{
     *     call: array{
     *         id: string,
     *         ticketId: string|null,
     *         duration: int,
     *         startTime: \DateTimeInterface|string,
     *         endTime: \DateTimeInterface|string
     *     },
     *     ticket?: array{
     *         id: string,
     *         status: string,
     *         timeSpent: int,
     *         scheduledDate?: \DateTimeInterface|string|null,
     *         updatedAt: \DateTimeInterface|string
     *     },
     *     previousTicket?: array{
     *         id: string,
     *         status: string,
     *         updatedAt: \DateTimeInterface|string
     *     }
     * } $result
     *
     * @return array{
     *     call: array{
     *         id: string,
     *         ticketId: string|null,
     *         duration: int,
     *         startTime: string,
     *         endTime: string
     *     },
     *     ticket?: array{
     *         id: string,
     *         status: string,
     *         timeSpent: int,
     *         updatedAt: string,
     *         scheduledDate?: string
     *     },
     *     previousTicket?: array{
     *         id: string,
     *         status: string,
     *         updatedAt: string
     *     }
     * }
     */
    private function normalizeEndCallResult(array $result): array
    {
        $response = [
            'call' => [
                'id' => (string) $result['call']['id'],
                'ticketId' => null === $result['call']['ticketId']
                    ? null
                    : (string) $result['call']['ticketId'],
                'duration' => (int) $result['call']['duration'],
                'startTime' => $this->formatDateTime($result['call']['startTime']),
                'endTime' => $this->formatDateTime($result['call']['endTime']),
            ],
        ];

        if (isset($result['ticket'])) {
            $ticket = $result['ticket'];

            $response['ticket'] = [
                'id' => (string) $ticket['id'],
                'status' => (string) $ticket['status'],
                'timeSpent' => (int) $ticket['timeSpent'],
                'updatedAt' => $this->formatDateTime($ticket['updatedAt']),
            ];

            if (isset($ticket['scheduledDate'])) {
                $response['ticket']['scheduledDate'] = $this->formatDate(
                    $ticket['scheduledDate'],
                );
            }
        }

        if (isset($result['previousTicket'])) {
            $previous = $result['previousTicket'];

            $response['previousTicket'] = [
                'id' => (string) $previous['id'],
                'status' => (string) $previous['status'],
                'updatedAt' => $this->formatDateTime($previous['updatedAt']),
            ];
        }

        return $response;
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return (string) $value;
    }

    private function formatDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    private function parseDateTime(string $value, string $field): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        $errors = \DateTimeImmutable::getLastErrors();
        $hasErrors = is_array($errors) && (
            $errors['warning_count'] > 0
            || $errors['error_count'] > 0
        );

        if (false === $date || $hasErrors) {
            throw new ValidationException(sprintf('Pole "%s" musi być w formacie ISO 8601 (DATE_ATOM)', $field), [$field => ['Nieprawidłowy format daty']]);
        }

        return $date;
    }

    private function requireWorker(): AuthenticatedWorker
    {
        return $this->workerProvider->getAuthenticatedWorker();
    }

    private function normalizeDuration(mixed $value): int
    {
        if (null === $value) {
            throw new ValidationException('Czas trwania jest wymagany', ['duration' => ['Czas trwania jest wymagany']]);
        }

        if (is_int($value)) {
            return $value;
        }

        if (!is_numeric($value)) {
            throw new ValidationException('Czas trwania musi być liczbą', ['duration' => ['Czas trwania musi być liczbą całkowitą lub zmiennoprzecinkową']]);
        }

        return (int) round((float) $value);
    }

    private function normalizeTicketId(?string $ticketId): ?string
    {
        if (null === $ticketId) {
            return null;
        }

        $trimmed = trim($ticketId);

        if ('' === $trimmed) {
            throw new ValidationException('Identyfikator ticketa nie może być pusty', ['ticketId' => ['Identyfikator ticketa nie może być pusty']]);
        }

        return $trimmed;
    }
}
