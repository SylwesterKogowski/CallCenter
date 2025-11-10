<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Manager;

use App\Modules\BackendForFrontend\Manager\Dto\MonitoringQuery;
use App\Modules\BackendForFrontend\Manager\Dto\TriggerAutoAssignmentRequest;
use App\Modules\BackendForFrontend\Manager\Dto\UpdateAutoAssignmentRequest;
use App\Modules\BackendForFrontend\Manager\Dto\UpdateAutoAssignmentSettingsInput;
use App\Modules\BackendForFrontend\Manager\Service\ManagerMonitoringServiceInterface;
use App\Modules\BackendForFrontend\Shared\AbstractJsonController;
use App\Modules\BackendForFrontend\Shared\Exception\ValidationException;
use App\Modules\BackendForFrontend\Shared\Security\Attribute\RequiresManager;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[RequiresManager]
#[Route(path: '/api/manager', name: 'backend_for_frontend_manager_')]
final class ManagerMonitoringController extends AbstractJsonController
{
    use ManagerControllerTrait;

    public function __construct(
        ValidatorInterface $validator,
        #[Autowire('%kernel.debug%')]
        bool $debug,
        private readonly AuthenticatedWorkerProvider $workerProvider,
        private readonly ManagerMonitoringServiceInterface $monitoringService,
    ) {
        parent::__construct($validator, $debug);
    }

    #[Route(path: '/monitoring', name: 'monitoring', methods: [Request::METHOD_GET])]
    public function monitoring(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $manager = $this->requireManager();

            $query = new MonitoringQuery(
                date: (string) $request->query->get('date', ''),
            );
            $this->validateDto($query);
            $date = $this->parseDate($query->date, 'date');

            $data = $this->monitoringService->getMonitoringData($manager->getId(), $date);

            return $this->normalizeMonitoringPayload($data);
        });
    }

    #[Route(path: '/auto-assignment', name: 'auto_assignment_update', methods: [Request::METHOD_PUT])]
    public function updateAutoAssignment(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $manager = $this->requireManager();
            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateUpdateAutoAssignmentRequest($payload);
            $this->validateDto($dto);

            $result = $this->monitoringService->updateAutoAssignmentSettings(
                $manager->getId(),
                $dto->enabled,
                $dto->settings,
            );

            return [
                'autoAssignmentSettings' => $this->normalizeAutoAssignmentSettings($result['autoAssignmentSettings']),
                'updatedAt' => $result['updatedAt']->format(DATE_ATOM),
            ];
        });
    }

    #[Route(
        path: '/auto-assignment/trigger',
        name: 'auto_assignment_trigger',
        methods: [Request::METHOD_POST],
    )]
    public function triggerAutoAssignment(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $manager = $this->requireManager();
            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateTriggerAutoAssignmentRequest($payload);
            $this->validateDto($dto);
            $date = $this->parseDate($dto->date, 'date');

            $result = $this->monitoringService->triggerAutoAssignment($manager->getId(), $date);

            return [
                'message' => (string) $result['message'],
                'ticketsAssigned' => (int) $result['ticketsAssigned'],
                'assignedTo' => array_map(
                    static fn (array $assignment): array => [
                        'workerId' => (string) $assignment['workerId'],
                        'ticketsCount' => (int) $assignment['ticketsCount'],
                    ],
                    $result['assignedTo'],
                ),
                'completedAt' => $result['completedAt']->format(DATE_ATOM),
            ];
        }, Response::HTTP_ACCEPTED);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateUpdateAutoAssignmentRequest(array $payload): UpdateAutoAssignmentRequest
    {
        if (!array_key_exists('enabled', $payload)) {
            throw new ValidationException('Pole "enabled" jest wymagane', ['errors' => ['enabled' => ['To pole jest wymagane']]]);
        }

        if (!is_bool($payload['enabled'])) {
            throw new ValidationException('Pole "enabled" musi być typu bool', ['errors' => ['enabled' => ['Oczekiwano wartości logicznej']]]);
        }

        if (!array_key_exists('settings', $payload) || !is_array($payload['settings'])) {
            throw new ValidationException('Pole "settings" jest wymagane', ['errors' => ['settings' => ['Oczekiwano obiektu ustawień']]]);
        }

        /** @var array<string, mixed> $settingsPayload */
        $settingsPayload = $payload['settings'];

        $settings = new UpdateAutoAssignmentSettingsInput(
            considerEfficiency: $this->expectBool($settingsPayload, 'considerEfficiency'),
            considerAvailability: $this->expectBool($settingsPayload, 'considerAvailability'),
            maxTicketsPerWorker: $this->expectInt($settingsPayload, 'maxTicketsPerWorker'),
        );

        return new UpdateAutoAssignmentRequest(
            enabled: $payload['enabled'],
            settings: $settings,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateTriggerAutoAssignmentRequest(array $payload): TriggerAutoAssignmentRequest
    {
        return new TriggerAutoAssignmentRequest(
            date: (string) ($payload['date'] ?? ''),
        );
    }

    /**
     * @param array{
     *     date: string,
     *     summary: array<string, int|float>,
     *     workerStats: list<array<string, mixed>>,
     *     queueStats: list<array<string, mixed>>,
     *     autoAssignmentSettings: array<string, mixed>
     * } $data
     *
     * @return array<string, mixed>
     */
    private function normalizeMonitoringPayload(array $data): array
    {
        return [
            'date' => (string) $data['date'],
            'summary' => [
                'totalTickets' => (int) $data['summary']['totalTickets'],
                'totalWorkers' => (int) $data['summary']['totalWorkers'],
                'totalQueues' => (int) $data['summary']['totalQueues'],
                'averageWorkload' => (float) $data['summary']['averageWorkload'],
                'averageResolutionTime' => (int) $data['summary']['averageResolutionTime'],
                'waitingTicketsTotal' => (int) $data['summary']['waitingTicketsTotal'],
                'inProgressTicketsTotal' => (int) $data['summary']['inProgressTicketsTotal'],
                'completedTicketsTotal' => (int) $data['summary']['completedTicketsTotal'],
            ],
            'workerStats' => array_map(
                static fn (array $stats): array => [
                    'workerId' => (string) $stats['workerId'],
                    'workerLogin' => (string) $stats['workerLogin'],
                    'ticketsCount' => (int) $stats['ticketsCount'],
                    'timeSpent' => (int) $stats['timeSpent'],
                    'timePlanned' => (int) $stats['timePlanned'],
                    'workloadLevel' => (string) $stats['workloadLevel'],
                    'efficiency' => (float) $stats['efficiency'],
                    'categories' => array_map('strval', $stats['categories'] ?? []),
                    'completedTickets' => (int) $stats['completedTickets'],
                    'inProgressTickets' => (int) $stats['inProgressTickets'],
                    'waitingTickets' => (int) $stats['waitingTickets'],
                ],
                $data['workerStats'],
            ),
            'queueStats' => array_map(
                static fn (array $stats): array => [
                    'queueId' => (string) $stats['queueId'],
                    'queueName' => (string) $stats['queueName'],
                    'waitingTickets' => (int) $stats['waitingTickets'],
                    'inProgressTickets' => (int) $stats['inProgressTickets'],
                    'completedTickets' => (int) $stats['completedTickets'],
                    'totalTickets' => (int) $stats['totalTickets'],
                    'averageResolutionTime' => (int) $stats['averageResolutionTime'],
                    'assignedWorkers' => (int) $stats['assignedWorkers'],
                ],
                $data['queueStats'],
            ),
            'autoAssignmentSettings' => $this->normalizeAutoAssignmentSettings($data['autoAssignmentSettings']),
        ];
    }

    /**
     * @param array{
     *     enabled: bool,
     *     lastRun: \DateTimeImmutable|null,
     *     ticketsAssigned: int,
     *     settings: array{
     *         considerEfficiency: bool,
     *         considerAvailability: bool,
     *         maxTicketsPerWorker: int
     *     }
     * } $settings
     *
     * @return array<string, mixed>
     */
    private function normalizeAutoAssignmentSettings(array $settings): array
    {
        return [
            'enabled' => (bool) $settings['enabled'],
            'lastRun' => $settings['lastRun'] instanceof \DateTimeImmutable
                ? $settings['lastRun']->format(DATE_ATOM)
                : null,
            'ticketsAssigned' => (int) $settings['ticketsAssigned'],
            'settings' => [
                'considerEfficiency' => (bool) $settings['settings']['considerEfficiency'],
                'considerAvailability' => (bool) $settings['settings']['considerAvailability'],
                'maxTicketsPerWorker' => (int) $settings['settings']['maxTicketsPerWorker'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function expectBool(array $payload, string $field): bool
    {
        if (!array_key_exists($field, $payload)) {
            throw new ValidationException(sprintf('Pole "%s" jest wymagane', $field), ['errors' => [$field => ['To pole jest wymagane']]]);
        }

        if (!is_bool($payload[$field])) {
            throw new ValidationException(sprintf('Pole "%s" musi być typu bool', $field), ['errors' => [$field => ['Oczekiwano wartości logicznej']]]);
        }

        return $payload[$field];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function expectInt(array $payload, string $field): int
    {
        if (!array_key_exists($field, $payload)) {
            throw new ValidationException(sprintf('Pole "%s" jest wymagane', $field), ['errors' => [$field => ['To pole jest wymagane']]]);
        }

        if (!is_int($payload[$field])) {
            throw new ValidationException(sprintf('Pole "%s" musi być liczbą całkowitą', $field), ['errors' => [$field => ['Oczekiwano liczby całkowitej']]]);
        }

        return $payload[$field];
    }
}
