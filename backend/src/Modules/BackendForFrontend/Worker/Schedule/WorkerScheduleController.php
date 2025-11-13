<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Schedule;

use App\Modules\Authentication\Application\AuthenticationServiceInterface;
use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\BackendForFrontend\Shared\AbstractJsonController;
use App\Modules\BackendForFrontend\Shared\Exception\AccessDeniedException;
use App\Modules\BackendForFrontend\Shared\Exception\AuthenticationException;
use App\Modules\BackendForFrontend\Shared\Exception\ResourceNotFoundException;
use App\Modules\BackendForFrontend\Shared\Security\Attribute\RequiresWorker;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorker;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use App\Modules\BackendForFrontend\Worker\Schedule\Dto\AddTicketMessageRequest;
use App\Modules\BackendForFrontend\Worker\Schedule\Dto\AddTicketNoteRequest;
use App\Modules\BackendForFrontend\Worker\Schedule\Dto\AddTicketTimeRequest;
use App\Modules\BackendForFrontend\Worker\Schedule\Dto\UpdateTicketStatusRequest;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Application\TicketServiceInterface;
use App\Modules\Tickets\Domain\Exception\TicketWorkNotFoundException;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\Tickets\Domain\TicketMessageInterface;
use App\Modules\Tickets\Domain\TicketNoteInterface;
use App\Modules\WorkerAvailability\Application\WorkerAvailabilityServiceInterface;
use App\Modules\WorkerSchedule\Application\Dto\WorkerScheduleAssignmentInterface;
use App\Modules\WorkerSchedule\Application\WorkerScheduleServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Testy w {@see \Tests\Unit\Modules\BackendForFrontend\Worker\Schedule\WorkerScheduleControllerTest}.
 */
#[RequiresWorker]
#[Route(path: '/api/worker', name: 'backend_for_frontend_worker_schedule_')]
final class WorkerScheduleController extends AbstractJsonController
{
    public function __construct(
        ValidatorInterface $validator,
        #[Autowire('%kernel.debug%')]
        bool $debug,
        private readonly AuthenticatedWorkerProvider $workerProvider,
        private readonly WorkerScheduleServiceInterface $workerScheduleService,
        private readonly TicketServiceInterface $ticketService,
        private readonly AuthenticationServiceInterface $authenticationService,
        private readonly WorkerAvailabilityServiceInterface $workerAvailabilityService,
    ) {
        parent::__construct($validator, $debug);
    }

    #[Route(path: '/schedule', name: 'schedule_current', methods: [Request::METHOD_GET])]
    public function getSchedule(): JsonResponse
    {
        return $this->execute(function () {
            $worker = $this->requireWorker();
            $workerEntity = $this->getWorkerEntity($worker);
            $period = $this->resolveSchedulePeriod();

            $assignments = $this->iterableToArray(
                $this->workerScheduleService->getWorkerScheduleForPeriod(
                    $worker->getId(),
                    $period['start'],
                    $period['end'],
                ),
            );

            $grouped = $this->groupAssignmentsByDate($assignments);
            $schedule = [];
            $activeTicketPayload = null;

            foreach ($grouped as $date => $dayAssignments) {
                $formattedTickets = [];
                $dateImmutable = new \DateTimeImmutable($date);

                foreach ($dayAssignments as $assignment) {
                    $ticketPayload = $this->formatScheduleTicket($assignment, $workerEntity);
                    $formattedTickets[] = $ticketPayload;

                    if (null === $activeTicketPayload && ($ticketPayload['isActive'] ?? false)) {
                        $activeTicketPayload = $this->enrichTicketWithDetails(
                            $ticketPayload,
                            $assignment,
                            $workerEntity,
                        );
                    }
                }

                $totalTimePlanned = array_sum(array_map(
                    static fn (array $ticket): int => $ticket['estimatedTime'],
                    $formattedTickets,
                ));
                $totalAvailableTime = $this->workerAvailabilityService->getAvailableTimeForDate(
                    $worker->getId(),
                    $dateImmutable,
                );

                $schedule[] = [
                    'date' => $date,
                    'tickets' => $formattedTickets,
                    'totalTimeSpent' => array_sum(array_map(
                        static fn (array $ticket): int => $ticket['timeSpent'],
                        $formattedTickets,
                    )),
                    'totalTimePlanned' => $totalTimePlanned,
                    'totalAvailableTime' => $totalAvailableTime,
                ];
            }

            usort(
                $schedule,
                static fn (array $left, array $right): int => strcmp($left['date'], $right['date']),
            );

            if (null === $activeTicketPayload) {
                $activeTicketPayload = $this->determineActiveTicketFallback($assignments, $workerEntity);
            }

            return [
                'schedule' => $schedule,
                'activeTicket' => $activeTicketPayload,
            ];
        });
    }

    #[Route(path: '/work-status', name: 'work_status', methods: [Request::METHOD_GET])]
    public function getWorkStatus(): JsonResponse
    {
        return $this->execute(function () {
            $worker = $this->requireWorker();
            $workerEntity = $this->getWorkerEntity($worker);
            $today = new \DateTimeImmutable('today');

            $stats = $this->workerScheduleService->getWorkerScheduleStatistics($worker->getId(), $today);
            $todayAssignments = $this->iterableToArray(
                $this->workerScheduleService->getWorkerScheduleForPeriod(
                    $worker->getId(),
                    $today,
                    $today,
                ),
            );

            $timeSpent = 0;

            foreach ($todayAssignments as $assignment) {
                $timeSpent += $this->ticketService->getWorkerTimeSpentOnTicket(
                    $assignment->getTicket(),
                    $workerEntity,
                );
            }

            return [
                'status' => $this->buildWorkStatusPayload($stats, $timeSpent, $worker->getId(), $today),
                'todayStats' => $this->buildTodayStatsPayload($stats, $today, $timeSpent),
            ];
        });
    }

    #[Route(
        path: '/tickets/{ticketId}/status',
        name: 'tickets_update_status',
        methods: [Request::METHOD_POST],
        requirements: ['ticketId' => '[A-Za-z0-9\-]+'],
    )]
    public function updateTicketStatus(string $ticketId, Request $request): JsonResponse
    {
        return $this->execute(function () use ($ticketId, $request) {
            $worker = $this->requireWorker();
            $ticket = $this->findTicketOrThrow($ticketId);

            $this->assertWorkerHasAccessToTicket($worker, $ticket);

            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateUpdateStatusRequest($payload);
            $this->validateDto($dto);

            $updatedTicket = $this->ticketService->updateTicketStatus($ticket, $dto->status);
            $updatedAt = $updatedTicket->getUpdatedAt() ?? new \DateTimeImmutable();

            return [
                'ticket' => [
                    'id' => $updatedTicket->getId(),
                    'status' => $updatedTicket->getStatus(),
                    'updatedAt' => $updatedAt->format(DATE_ATOM),
                ],
            ];
        });
    }

    #[Route(
        path: '/tickets/{ticketId}/time',
        name: 'tickets_add_time',
        methods: [Request::METHOD_POST],
        requirements: ['ticketId' => '[A-Za-z0-9\-]+'],
    )]
    public function addTicketTime(string $ticketId, Request $request): JsonResponse
    {
        return $this->execute(function () use ($ticketId, $request) {
            $worker = $this->requireWorker();
            $workerEntity = $this->getWorkerEntity($worker);
            $ticket = $this->findTicketOrThrow($ticketId);

            $this->assertWorkerHasAccessToTicket($worker, $ticket);

            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateAddTimeRequest($payload);
            $this->validateDto($dto);

            $this->ticketService->registerManualTimeEntry(
                $ticket,
                $workerEntity,
                $dto->minutes,
                'phone_call' === $dto->type,
            );

            $timeSpent = $this->ticketService->getWorkerTimeSpentOnTicket($ticket, $workerEntity);
            $refreshedTicket = $this->ticketService->getTicketById($ticket->getId()) ?? $ticket;
            $updatedAt = $refreshedTicket->getUpdatedAt() ?? new \DateTimeImmutable();

            return [
                'ticket' => [
                    'id' => $ticket->getId(),
                    'timeSpent' => $timeSpent,
                    'updatedAt' => $updatedAt->format(DATE_ATOM),
                ],
            ];
        });
    }

    #[Route(
        path: '/tickets/{ticketId}/notes',
        name: 'tickets_add_note',
        methods: [Request::METHOD_POST],
        requirements: ['ticketId' => '[A-Za-z0-9\-]+'],
    )]
    public function addTicketNote(string $ticketId, Request $request): JsonResponse
    {
        return $this->execute(function () use ($ticketId, $request) {
            $worker = $this->requireWorker();
            $workerEntity = $this->getWorkerEntity($worker);
            $ticket = $this->findTicketOrThrow($ticketId);

            $this->assertWorkerHasAccessToTicket($worker, $ticket);

            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateAddNoteRequest($payload);
            $this->validateDto($dto);

            $note = $this->ticketService->addTicketNote(
                $ticket,
                $workerEntity,
                $dto->content,
            );

            return [
                'note' => $this->formatTicketNote($note),
            ];
        }, Response::HTTP_CREATED);
    }

    #[Route(
        path: '/tickets/{ticketId}/messages',
        name: 'tickets_add_message',
        methods: [Request::METHOD_POST],
        requirements: ['ticketId' => '[A-Za-z0-9\-]+'],
    )]
    public function addTicketMessage(string $ticketId, Request $request): JsonResponse
    {
        return $this->execute(function () use ($ticketId, $request) {
            $worker = $this->requireWorker();
            $workerEntity = $this->getWorkerEntity($worker);
            $ticket = $this->findTicketOrThrow($ticketId);

            $this->assertWorkerHasAccessToTicket($worker, $ticket);

            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateAddMessageRequest($payload);
            $this->validateDto($dto);

            $message = $this->ticketService->addMessageToTicket(
                $ticket,
                $dto->content,
                'worker',
                $workerEntity->getId(),
                $workerEntity->getLogin(),
            );

            return [
                'message' => $this->formatTicketMessage($message),
            ];
        }, Response::HTTP_CREATED);
    }

    #[Route(
        path: '/tickets/{ticketId}/close',
        name: 'tickets_close',
        methods: [Request::METHOD_POST],
        requirements: ['ticketId' => '[A-Za-z0-9\-]+'],
    )]
    public function closeTicket(string $ticketId): JsonResponse
    {
        return $this->execute(function () use ($ticketId) {
            $worker = $this->requireWorker();
            $workerEntity = $this->getWorkerEntity($worker);
            $ticket = $this->findTicketOrThrow($ticketId);

            $this->assertWorkerHasAccessToTicket($worker, $ticket);

            try {
                $this->ticketService->stopTicketWork($ticket, $workerEntity);
            } catch (TicketWorkNotFoundException) {
                // No active work to stop, continue with closing
            }

            $closedTicket = $this->ticketService->closeTicket($ticket, $workerEntity);
            $closedAt = $closedTicket->getClosedAt() ?? new \DateTimeImmutable();
            $updatedAt = $closedTicket->getUpdatedAt() ?? new \DateTimeImmutable();

            return [
                'ticket' => [
                    'id' => $closedTicket->getId(),
                    'status' => $closedTicket->getStatus(),
                    'closedAt' => $closedAt->format(DATE_ATOM),
                    'updatedAt' => $updatedAt->format(DATE_ATOM),
                ],
            ];
        });
    }

    /**
     * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}
     */
    private function resolveSchedulePeriod(): array
    {
        $today = new \DateTimeImmutable('today');

        return [
            'start' => $today->sub(new \DateInterval('P1D')),
            'end' => $today->add(new \DateInterval('P6D')),
        ];
    }

    /**
     * @param WorkerScheduleAssignmentInterface[] $assignments
     *
     * @return array<string, WorkerScheduleAssignmentInterface[]>
     */
    private function groupAssignmentsByDate(array $assignments): array
    {
        $grouped = [];

        foreach ($assignments as $assignment) {
            $dateKey = $assignment->getScheduledDate()->format('Y-m-d');
            $grouped[$dateKey][] = $assignment;
        }

        foreach ($grouped as &$dayAssignments) {
            usort(
                $dayAssignments,
                static fn (
                    WorkerScheduleAssignmentInterface $left,
                    WorkerScheduleAssignmentInterface $right,
                ): int => $left->getAssignedAt() <=> $right->getAssignedAt(),
            );
        }
        unset($dayAssignments);

        return $grouped;
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     category: array{id: string, name: string, defaultResolutionTimeMinutes: int, defaultResolutionTime: int},
     *     status: string,
     *     timeSpent: int,
     *     estimatedTime: int,
     *     scheduledDate: string,
     *     client: array{id: string, name: string, email: ?string, phone: ?string},
     *     isActive?: true
     * }
     */
    private function formatScheduleTicket(
        WorkerScheduleAssignmentInterface $assignment,
        WorkerInterface $worker,
    ): array {
        $ticket = $assignment->getTicket();
        $category = $ticket->getCategory();
        $client = $ticket->getClient();
        $timeSpent = $this->ticketService->getWorkerTimeSpentOnTicket($ticket, $worker);

        $payload = [
            'id' => $ticket->getId(),
            'title' => $this->normalizeTicketTitle($ticket),
            'category' => $this->formatCategory($category),
            'status' => $ticket->getStatus(),
            'timeSpent' => $timeSpent,
            'estimatedTime' => $assignment->getEstimatedTimeMinutes(),
            'scheduledDate' => $assignment->getScheduledDate()->format('Y-m-d'),
            'client' => $this->formatClient($client),
        ];

        if ('in_progress' === $ticket->getStatus()) {
            $payload['isActive'] = true;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $basePayload
     *
     * @return array<string, mixed>
     */
    private function enrichTicketWithDetails(
        array $basePayload,
        WorkerScheduleAssignmentInterface $assignment,
        WorkerInterface $worker,
    ): array {
        $ticket = $assignment->getTicket();
        $notes = $this->ticketService->getTicketNotes($ticket);
        $messages = $this->ticketService->getTicketMessages($ticket);

        $basePayload['notes'] = array_map(
            fn (TicketNoteInterface $note): array => $this->formatTicketNote($note),
            $notes,
        );

        $basePayload['messages'] = array_map(
            fn (TicketMessageInterface $message): array => $this->formatTicketMessage($message),
            $messages,
        );

        if (!isset($basePayload['client'])) {
            $basePayload['client'] = $this->formatClient($ticket->getClient());
        }

        if (!isset($basePayload['timeSpent'])) {
            $basePayload['timeSpent'] = $this->ticketService->getWorkerTimeSpentOnTicket($ticket, $worker);
        }

        return $basePayload;
    }

    /**
     * @param WorkerScheduleAssignmentInterface[] $assignments
     *
     * @return array<string, mixed>|null
     */
    private function determineActiveTicketFallback(array $assignments, WorkerInterface $worker): ?array
    {
        foreach ($assignments as $assignment) {
            if ('in_progress' !== $assignment->getTicket()->getStatus()) {
                continue;
            }

            $basePayload = $this->formatScheduleTicket($assignment, $worker);

            return $this->enrichTicketWithDetails($basePayload, $assignment, $worker);
        }

        return null;
    }

    /**
     * @param array<string, int> $stats
     *
     * @return array{
     *     level: string,
     *     message: string,
     *     ticketsCount: int,
     *     timeSpent: int,
     *     timePlanned: int
     * }
     */
    private function buildWorkStatusPayload(array $stats, int $timeSpent, string $workerId, \DateTimeImmutable $date): array
    {
        $ticketsCount = max(0, $stats['ticketsCount'] ?? 0);
        $timePlanned = max(0, $stats['timePlanned'] ?? 0);

        if (0 === $timePlanned && 0 === $ticketsCount) {
            return [
                'level' => 'low',
                'message' => 'Brak zaplanowanych ticketów',
                'ticketsCount' => 0,
                'timeSpent' => $timeSpent,
                'timePlanned' => 0,
            ];
        }

        $availableTime = $this->workerAvailabilityService->getAvailableTimeForDate($workerId, $date);
        $ratio = $availableTime > 0 ? $timePlanned / $availableTime : 0.0;
        $level = 'normal';
        $message = 'Obciążenie w normie';

        if ($ratio < 0.5) {
            $level = 'low';
            $message = 'Masz sporo wolnego czasu – rozważ przejęcie dodatkowych ticketów';
        } elseif ($ratio >= 1.0) {
            $level = 'critical';
            $message = 'Krytyczne obciążenie – porozmawiaj z managerem o wsparciu';
        } elseif ($ratio >= 0.8) {
            $level = 'high';
            $message = 'Duże obciążenie – monitoruj postępy i priorytety';
        }

        return [
            'level' => $level,
            'message' => $message,
            'ticketsCount' => $ticketsCount,
            'timeSpent' => $timeSpent,
            'timePlanned' => $timePlanned,
        ];
    }

    /**
     * @param array<string, int> $stats
     *
     * @return array{
     *     date: string,
     *     ticketsCount: int,
     *     timeSpent: int,
     *     timePlanned: int,
     *     completedTickets: int,
     *     inProgressTickets: int,
     *     waitingTickets: int
     * }
     */
    private function buildTodayStatsPayload(
        array $stats,
        \DateTimeImmutable $date,
        int $timeSpent,
    ): array {
        return [
            'date' => $date->format('Y-m-d'),
            'ticketsCount' => max(0, $stats['ticketsCount'] ?? 0),
            'timeSpent' => $timeSpent,
            'timePlanned' => max(0, $stats['timePlanned'] ?? 0),
            'completedTickets' => max(0, $stats['completedTickets'] ?? 0),
            'inProgressTickets' => max(0, $stats['inProgressTickets'] ?? 0),
            'waitingTickets' => max(0, $stats['waitingTickets'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateUpdateStatusRequest(array $payload): UpdateTicketStatusRequest
    {
        return new UpdateTicketStatusRequest(
            status: (string) ($payload['status'] ?? ''),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateAddTimeRequest(array $payload): AddTicketTimeRequest
    {
        $minutesValue = $payload['minutes'] ?? null;
        $minutes = is_numeric($minutesValue)
            ? (int) round((float) $minutesValue)
            : (int) ($minutesValue ?? 0);

        return new AddTicketTimeRequest(
            minutes: $minutes,
            type: isset($payload['type']) ? (string) $payload['type'] : '',
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateAddNoteRequest(array $payload): AddTicketNoteRequest
    {
        return new AddTicketNoteRequest(
            content: (string) ($payload['content'] ?? ''),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateAddMessageRequest(array $payload): AddTicketMessageRequest
    {
        return new AddTicketMessageRequest(
            content: (string) ($payload['content'] ?? ''),
        );
    }

    private function requireWorker(): AuthenticatedWorker
    {
        return $this->workerProvider->getAuthenticatedWorker();
    }

    private function getWorkerEntity(AuthenticatedWorker $worker): WorkerInterface
    {
        $entity = $this->authenticationService->getWorkerById($worker->getId());

        if (null === $entity) {
            throw new AuthenticationException('Pracownik nie został znaleziony');
        }

        return $entity;
    }

    private function findTicketOrThrow(string $ticketId): TicketInterface
    {
        $ticket = $this->ticketService->getTicketById($ticketId);

        if (null === $ticket) {
            throw new ResourceNotFoundException('Ticket nie został znaleziony');
        }

        return $ticket;
    }

    private function assertWorkerHasAccessToTicket(
        AuthenticatedWorker $worker,
        TicketInterface $ticket,
    ): void {
        if ($worker->isManager()) {
            return;
        }

        $categoryId = $ticket->getCategory()->getId();

        if (!in_array($categoryId, $worker->getCategoryIds(), true)) {
            throw new AccessDeniedException('Brak uprawnień do obsługi ticketa', ['ticketId' => $ticket->getId(), 'categoryId' => $categoryId]);
        }
    }

    /**
     * @return array{id: string, name: string, defaultResolutionTimeMinutes: int, defaultResolutionTime: int}
     */
    private function formatCategory(TicketCategoryInterface $category): array
    {
        $defaultResolutionTime = $category->getDefaultResolutionTimeMinutes();

        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'defaultResolutionTimeMinutes' => $defaultResolutionTime,
            'defaultResolutionTime' => $defaultResolutionTime,
        ];
    }

    /**
     * @return array{id: string, name: string, email: ?string, phone: ?string}
     */
    private function formatClient(ClientInterface $client): array
    {
        $parts = array_filter([$client->getFirstName(), $client->getLastName()]);
        $fullName = $parts ? trim(implode(' ', $parts)) : '';
        $name = '' !== $fullName
            ? $fullName
            : ($client->getEmail() ?? $client->getPhone() ?? 'Klient');

        return [
            'id' => $client->getId(),
            'name' => $name,
            'email' => $client->getEmail(),
            'phone' => $client->getPhone(),
        ];
    }

    private function normalizeTicketTitle(TicketInterface $ticket): string
    {
        $title = $ticket->getTitle();

        if (null === $title || '' === trim($title)) {
            return sprintf('Ticket %s', $ticket->getId());
        }

        return $title;
    }

    /**
     * @return array{id: string, content: string, createdAt: string, createdBy: string}
     */
    private function formatTicketNote(TicketNoteInterface $note): array
    {
        return [
            'id' => $note->getId(),
            'content' => $note->getContent(),
            'createdAt' => $note->getCreatedAt()->format(DATE_ATOM),
            'createdBy' => $note->getWorkerId(),
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     ticketId: string,
     *     senderType: string,
     *     senderId: ?string,
     *     senderName: ?string,
     *     content: string,
     *     createdAt: string,
     *     status: ?string
     * }
     */
    private function formatTicketMessage(TicketMessageInterface $message): array
    {
        return [
            'id' => $message->getId(),
            'ticketId' => $message->getTicketId(),
            'senderType' => $message->getSenderType(),
            'senderId' => $message->getSenderId(),
            'senderName' => $message->getSenderName(),
            'content' => $message->getContent(),
            'createdAt' => $message->getCreatedAt()->format(DATE_ATOM),
            'status' => $message->getStatus(),
        ];
    }

    /**
     * @template TValue
     *
     * @param iterable<TValue> $items
     *
     * @return array<int, TValue>
     */
    private function iterableToArray(iterable $items): array
    {
        if (is_array($items)) {
            return array_values($items);
        }

        return iterator_to_array($items, false);
    }
}
