<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Planning;

use App\Modules\BackendForFrontend\Shared\AbstractJsonController;
use App\Modules\BackendForFrontend\Shared\Exception\AccessDeniedException;
use App\Modules\BackendForFrontend\Shared\Exception\ValidationException;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use App\Modules\BackendForFrontend\Shared\Security\Attribute\RequiresWorker;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorker;
use App\Modules\BackendForFrontend\Worker\Planning\Dto\AssignTicketRequest;
use App\Modules\BackendForFrontend\Worker\Planning\Dto\AutoAssignRequest;
use App\Modules\Clients\Domain\ClientInterface;
use App\Modules\TicketCategories\Domain\TicketCategoryInterface;
use App\Modules\Tickets\Application\Dto\WorkerBacklogFilters;
use App\Modules\Tickets\Application\Dto\WorkerBacklogResultInterface;
use App\Modules\Tickets\Application\Dto\WorkerBacklogTicketInterface;
use App\Modules\Tickets\Application\TicketBacklogServiceInterface;
use App\Modules\Tickets\Domain\TicketInterface;
use App\Modules\WorkerAvailability\Application\WorkerAvailabilityServiceInterface;
use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityInterface;
use App\Modules\WorkerSchedule\Application\Dto\WorkerScheduleAssignmentInterface;
use App\Modules\WorkerSchedule\Application\Dto\WorkerSchedulePredictionInterface;
use App\Modules\WorkerSchedule\Application\WorkerScheduleServiceInterface;
use DateInterval;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[RequiresWorker]
#[Route(path: '/api/worker', name: 'backend_for_frontend_worker_planning_')]
final class WorkerPlanningController extends AbstractJsonController
{
    public function __construct(
        ValidatorInterface $validator,
        #[Autowire('%kernel.debug%')]
        bool $debug,
        private readonly AuthenticatedWorkerProvider $workerProvider,
        private readonly TicketBacklogServiceInterface $ticketBacklogService,
        private readonly WorkerScheduleServiceInterface $workerScheduleService,
        private readonly WorkerAvailabilityServiceInterface $workerAvailabilityService,
    ) {
        parent::__construct($validator, $debug);
    }

    #[Route(path: '/tickets/backlog', name: 'tickets_backlog', methods: [Request::METHOD_GET])]
    public function getBacklog(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $worker = $this->requireWorker();
            $filters = $this->buildBacklogFilters($request, $worker);

            $result = $this->ticketBacklogService->getWorkerBacklog(
                $worker->getId(),
                $filters,
            );

            return $this->formatBacklogResult($result);
        });
    }

    #[Route(path: '/schedule/week', name: 'schedule_week', methods: [Request::METHOD_GET])]
    public function getWeekSchedule(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $worker = $this->requireWorker();
            $weekStart = $this->resolveWeekStartDate($request->query->get('startDate'));

            $assignments = $this->workerScheduleService
                ->getWorkerScheduleForWeek($worker->getId(), $weekStart);
            $availability = $this->workerAvailabilityService
                ->getWorkerAvailabilityForWeek($worker->getId(), $weekStart);

            return [
                'schedule' => $this->buildWeekSchedulePayload($assignments, $availability, $weekStart),
            ];
        });
    }

    #[Route(path: '/schedule/predictions', name: 'schedule_predictions', methods: [Request::METHOD_GET])]
    public function getPredictions(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $worker = $this->requireWorker();
            $weekStart = $this->resolveWeekStartDate($request->query->get('startDate'));

            $predictions = $this->workerScheduleService->getPredictionsForWeek(
                $worker->getId(),
                $weekStart,
            );

            return [
                'predictions' => $this->formatPredictions($predictions),
            ];
        });
    }

    #[Route(
        path: '/schedule/assign',
        name: 'schedule_assign',
        methods: [Request::METHOD_POST],
    )]
    public function assignTicket(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $worker = $this->requireWorker();
            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateAssignTicketRequest($payload);
            $this->validateDto($dto);

            $scheduledDate = $this->parseDate($dto->date, 'date');

            $assignment = $this->workerScheduleService->assignTicketToWorker(
                $dto->ticketId,
                $worker->getId(),
                $scheduledDate,
                $worker->getId(),
            );

            return [
                'assignment' => $this->formatAssignmentSummary($assignment),
            ];
        }, Response::HTTP_CREATED);
    }

    #[Route(
        path: '/schedule/assign',
        name: 'schedule_unassign',
        methods: [Request::METHOD_DELETE],
    )]
    public function unassignTicket(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $worker = $this->requireWorker();
            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateAssignTicketRequest($payload);
            $this->validateDto($dto);

            $scheduledDate = $this->parseDate($dto->date, 'date');

            $this->workerScheduleService->removeTicketFromSchedule(
                $dto->ticketId,
                $worker->getId(),
                $scheduledDate,
            );

            return [
                'success' => true,
            ];
        });
    }

    #[Route(
        path: '/schedule/auto-assign',
        name: 'schedule_auto_assign',
        methods: [Request::METHOD_POST],
    )]
    public function autoAssign(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $worker = $this->requireWorker();
            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateAutoAssignRequest($payload);
            $this->validateDto($dto);

            $weekStart = $this->parseDate($dto->weekStartDate, 'weekStartDate');
            $categories = $this->normalizeCategories($dto->categories);

            if ([] !== $categories) {
                $this->assertWorkerHasAccessToCategories($worker, $categories);
            }

            $assignments = $this->workerScheduleService->autoAssignTicketsForWorker(
                $worker->getId(),
                $weekStart,
                [] === $categories ? null : $categories,
            );

            $assignmentList = $this->iterableToArray($assignments);

            $formattedAssignments = array_map(
                fn (WorkerScheduleAssignmentInterface $assignment): array => $this->formatAssignmentSummary($assignment),
                $assignmentList,
            );

            return [
                'assignments' => array_map(
                    static fn (array $summary): array => [
                        'ticketId' => $summary['ticketId'],
                        'date' => $summary['date'],
                    ],
                    $formattedAssignments,
                ),
                'totalAssigned' => count($formattedAssignments),
            ];
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateAssignTicketRequest(array $payload): AssignTicketRequest
    {
        return new AssignTicketRequest(
            ticketId: (string) ($payload['ticketId'] ?? ''),
            date: (string) ($payload['date'] ?? ''),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateAutoAssignRequest(array $payload): AutoAssignRequest
    {
        $categories = [];

        if (isset($payload['categories']) && is_array($payload['categories'])) {
            $categories = array_filter(
                array_map(static fn ($value): string => (string) $value, $payload['categories']),
                static fn (string $value): bool => '' !== trim($value),
            );
        }

        return new AutoAssignRequest(
            weekStartDate: (string) ($payload['weekStartDate'] ?? ''),
            categories: $categories,
        );
    }

    private function requireWorker(): AuthenticatedWorker
    {
        return $this->workerProvider->getAuthenticatedWorker();
    }

    private function resolveWeekStartDate(mixed $value): DateTimeImmutable
    {
        if (is_string($value) && '' !== $value) {
            return $this->parseDate($value, 'startDate');
        }

        return new DateTimeImmutable('today');
    }

    private function parseDate(string $value, string $field): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        $hasErrors = false !== $errors && (
            ($errors['warning_count'] ?? 0) > 0
            || ($errors['error_count'] ?? 0) > 0
        );

        if (false === $date || $hasErrors) {
            throw new ValidationException('Nieprawidłowa data', [
                $field => ['Data musi być w formacie YYYY-MM-DD'],
            ]);
        }

        return $date;
    }

    private function buildBacklogFilters(Request $request, AuthenticatedWorker $worker): WorkerBacklogFilters
    {
        $allowedCategories = $worker->getCategoryIds();
        $categoryFilter = $this->extractCsvValues($request->query->get('categories'));
        $statuses = $this->extractCsvValues($request->query->get('statuses'));
        $priorities = $this->extractCsvValues($request->query->get('priorities'));
        $search = $this->extractScalar($request->query->get('search'));
        $sort = $this->extractScalar($request->query->get('sort'));

        if ([] !== $categoryFilter) {
            $this->assertWorkerHasAccessToCategories($worker, $categoryFilter);
        }

        if ([] === $categoryFilter) {
            $categoryFilter = $allowedCategories;
        }

        return new WorkerBacklogFilters(
            categories: $categoryFilter,
            statuses: $statuses,
            priorities: $priorities,
            search: $search,
            sort: $sort,
        );
    }

    /**
     * @param iterable<WorkerScheduleAssignmentInterface> $assignments
     * @param iterable<WorkerAvailabilityInterface> $availability
     * @return array<int, array{
     *     date: string,
     *     isAvailable: bool,
     *     availabilityHours: array<int, array{startTime: string, endTime: string}>,
     *     tickets: array<int, array{
     *         id: string,
     *         title: string,
     *         category: array{id: string, name: string, defaultResolutionTimeMinutes: int, defaultResolutionTime: int},
     *         estimatedTime: int,
     *         priority: string
     *     }>,
     *     totalEstimatedTime: int
     * }>
     */
    private function buildWeekSchedulePayload(
        iterable $assignments,
        iterable $availability,
        DateTimeImmutable $weekStart,
    ): array {
        $groupedAssignments = $this->groupAssignmentsByDate($assignments);
        $availabilityByDate = $this->groupAvailabilityByDate($availability);
        $days = [];
        $current = $weekStart;

        for ($i = 0; $i < 7; ++$i) {
            $dateKey = $current->format('Y-m-d');
            $slots = $availabilityByDate[$dateKey] ?? [];
            $dayAssignments = $groupedAssignments[$dateKey] ?? [];
            $tickets = array_map(
                fn (WorkerScheduleAssignmentInterface $assignment): array => $this->formatScheduledTicket($assignment),
                $dayAssignments,
            );

            $totalEstimated = array_sum(array_map(
                static fn (array $ticket): int => $ticket['estimatedTime'],
                $tickets,
            ));

            $days[] = [
                'date' => $dateKey,
                'isAvailable' => [] !== $slots,
                'availabilityHours' => array_map(
                    static fn (WorkerAvailabilityInterface $slot): array => [
                        'startTime' => $slot->getStartDatetime()->format('H:i'),
                        'endTime' => $slot->getEndDatetime()->format('H:i'),
                    ],
                    $slots,
                ),
                'tickets' => $tickets,
                'totalEstimatedTime' => $totalEstimated,
            ];

            $current = $current->add(new DateInterval('P1D'));
        }

        return $days;
    }

    /**
     * @param iterable<WorkerScheduleAssignmentInterface> $assignments
     * @return array<string, WorkerScheduleAssignmentInterface[]>
     */
    private function groupAssignmentsByDate(iterable $assignments): array
    {
        $grouped = [];

        foreach ($assignments as $assignment) {
            $dateKey = $assignment->getScheduledDate()->format('Y-m-d');
            $grouped[$dateKey][] = $assignment;
        }

        return $grouped;
    }

    /**
     * @param iterable<WorkerAvailabilityInterface> $availability
     * @return array<string, WorkerAvailabilityInterface[]>
     */
    private function groupAvailabilityByDate(iterable $availability): array
    {
        $grouped = [];

        foreach ($availability as $slot) {
            $dateKey = $slot->getStartDatetime()->format('Y-m-d');
            $grouped[$dateKey][] = $slot;
        }

        return $grouped;
    }

    /**
     * @param iterable<WorkerSchedulePredictionInterface> $predictions
     * @return array<int, array{
     *     date: string,
     *     predictedTicketCount: int,
     *     availableTime: int,
     *     efficiency: float
     * }>
     */
    private function formatPredictions(iterable $predictions): array
    {
        $payload = [];

        foreach ($predictions as $prediction) {
            $payload[] = [
                'date' => $prediction->getDate()->format('Y-m-d'),
                'predictedTicketCount' => $prediction->getPredictedTicketCount(),
                'availableTime' => $prediction->getAvailableTimeMinutes(),
                'efficiency' => $prediction->getEfficiency(),
            ];
        }

        return $payload;
    }

    private function formatAssignmentSummary(WorkerScheduleAssignmentInterface $assignment): array
    {
        return [
            'ticketId' => $assignment->getTicket()->getId(),
            'date' => $assignment->getScheduledDate()->format('Y-m-d'),
            'assignedAt' => $assignment->getAssignedAt()->format(DATE_ATOM),
        ];
    }

    private function formatScheduledTicket(WorkerScheduleAssignmentInterface $assignment): array
    {
        $ticket = $assignment->getTicket();
        $category = $ticket->getCategory();

        return [
            'id' => $ticket->getId(),
            'title' => $this->normalizeTicketTitle($ticket),
            'category' => $this->formatCategory($category),
            'estimatedTime' => $assignment->getEstimatedTimeMinutes(),
            'priority' => $assignment->getPriority() ?? 'normal',
        ];
    }

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

    private function normalizeTicketTitle(TicketInterface $ticket): string
    {
        $title = $ticket->getTitle();

        if (null === $title || '' === trim($title)) {
            return sprintf('Ticket %s', $ticket->getId());
        }

        return $title;
    }

    private function formatBacklogResult(WorkerBacklogResultInterface $result): array
    {
        $tickets = [];

        foreach ($result->getTickets() as $item) {
            $tickets[] = $this->formatBacklogTicket($item);
        }

        return [
            'tickets' => $tickets,
            'total' => $result->getTotal(),
        ];
    }

    private function formatBacklogTicket(WorkerBacklogTicketInterface $item): array
    {
        $ticket = $item->getTicket();
        $client = $item->getClient();
        $category = $item->getCategory();

        return [
            'id' => $ticket->getId(),
            'title' => $this->normalizeTicketTitle($ticket),
            'category' => $this->formatCategory($category),
            'status' => $ticket->getStatus(),
            'priority' => $item->getPriority(),
            'client' => [
                'id' => $client->getId(),
                'name' => $this->formatClientName($client),
                'email' => $client->getEmail(),
                'phone' => $client->getPhone(),
            ],
            'estimatedTime' => $item->getEstimatedTimeMinutes(),
            'createdAt' => $item->getCreatedAt()->format(DATE_ATOM),
            'scheduledDate' => $item->getScheduledDate()?->format('Y-m-d'),
        ];
    }

    private function formatClientName(ClientInterface $client): string
    {
        $parts = array_filter([$client->getFirstName(), $client->getLastName()]);

        if ([] !== $parts) {
            return implode(' ', $parts);
        }

        return $client->getEmail()
            ?? $client->getPhone()
            ?? 'Klient';
    }

    /**
     * @param string[] $categories
     */
    private function assertWorkerHasAccessToCategories(AuthenticatedWorker $worker, array $categories): void
    {
        if ($worker->isManager()) {
            return;
        }

        $available = $worker->getCategoryIds();
        $missing = array_diff($categories, $available);

        if ([] !== $missing) {
            throw new AccessDeniedException('Brak uprawnień do wybranych kategorii', [
                'categories' => array_values($missing),
            ]);
        }
    }

    private function extractScalar(mixed $value): ?string
    {
        if (is_string($value) && '' !== trim($value)) {
            return trim($value);
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function extractCsvValues(mixed $value): array
    {
        if (!is_string($value) || '' === trim($value)) {
            return [];
        }

        $segments = array_map(
            static fn (string $segment): string => trim($segment),
            explode(',', $value),
        );

        $segments = array_filter($segments, static fn (string $segment): bool => '' !== $segment);

        return array_values(array_unique($segments));
    }

    /**
     * @param string[] $categories
     * @return string[]
     */
    private function normalizeCategories(array $categories): array
    {
        $normalized = array_map(static fn (string $category): string => trim($category), $categories);
        $normalized = array_filter($normalized, static fn (string $category): bool => '' !== $category);

        return array_values(array_unique($normalized));
    }

    /**
     * @template TValue
     * @param iterable<TValue> $items
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


