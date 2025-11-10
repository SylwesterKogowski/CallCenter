<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Worker\Availability;

use App\Modules\BackendForFrontend\Shared\AbstractJsonController;
use App\Modules\BackendForFrontend\Shared\Exception\ValidationException;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use App\Modules\BackendForFrontend\Shared\Security\Attribute\RequiresWorker;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorker;
use App\Modules\BackendForFrontend\Worker\Availability\Dto\CopyWorkerAvailabilityRequest;
use App\Modules\BackendForFrontend\Worker\Availability\Dto\SaveWorkerAvailabilityRequest;
use App\Modules\BackendForFrontend\Worker\Availability\Dto\TimeSlotPayload;
use App\Modules\BackendForFrontend\Worker\Availability\Dto\UpdateWorkerTimeSlotRequest;
use App\Modules\WorkerAvailability\Application\Dto\CopyAvailabilityResultInterface;
use App\Modules\WorkerAvailability\Application\Dto\DayAvailabilityResultInterface;
use App\Modules\WorkerAvailability\Application\WorkerAvailabilityServiceInterface;
use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityInterface;
use DateInterval;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[RequiresWorker]
#[Route(path: '/api/worker/availability', name: 'backend_for_frontend_worker_availability_')]
final class WorkerAvailabilityController extends AbstractJsonController
{
    public function __construct(
        ValidatorInterface $validator,
        #[Autowire('%kernel.debug%')]
        bool $debug,
        private readonly WorkerAvailabilityServiceInterface $workerAvailabilityService,
        private readonly AuthenticatedWorkerProvider $workerProvider,
    ) {
        parent::__construct($validator, $debug);
    }

    #[Route(path: '', name: 'list', methods: [Request::METHOD_GET])]
    public function getAvailability(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $worker = $this->requireWorker();
            $startDateParam = $request->query->get('startDate');
            $startDate = is_string($startDateParam) && '' !== $startDateParam
                ? $this->parseDate($startDateParam, 'startDate')
                : $this->today();

            $slots = $this->workerAvailabilityService->getWorkerAvailabilityForWeek(
                $worker->getId(),
                $startDate,
            );

            return [
                'availability' => $this->buildWeeklyAvailabilityPayload($slots, $startDate),
            ];
        });
    }

    #[Route(
        path: '/{date}',
        name: 'save_for_day',
        methods: [Request::METHOD_POST],
        requirements: ['date' => '\d{4}-\d{2}-\d{2}'],
    )]
    public function saveAvailabilityForDay(string $date, Request $request): JsonResponse
    {
        return $this->execute(function () use ($date, $request) {
            $worker = $this->requireWorker();
            $day = $this->parseDate($date, 'date');

            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateSaveRequest($payload);
            $this->validateDto($dto);

            $timeSlots = $this->buildTimeSlots($day, $dto->timeSlots);

            $result = $this->workerAvailabilityService->replaceWorkerAvailabilityForDay(
                $worker->getId(),
                $day,
                $timeSlots,
            );

            return $this->buildDayAvailabilityPayload($result);
        });
    }

    #[Route(
        path: '/{date}/time-slots/{timeSlotId}',
        name: 'update_time_slot',
        methods: [Request::METHOD_PUT],
        requirements: [
            'date' => '\d{4}-\d{2}-\d{2}',
            'timeSlotId' => '[A-Za-z0-9\-]+',
        ],
    )]
    public function updateTimeSlot(string $date, string $timeSlotId, Request $request): JsonResponse
    {
        return $this->execute(function () use ($date, $timeSlotId, $request) {
            $worker = $this->requireWorker();
            $day = $this->parseDate($date, 'date');

            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateUpdateRequest($payload);
            $this->validateDto($dto);

            $timeSlot = $this->buildTimeSlots($day, [$dto])[0] ?? null;

            if (null === $timeSlot) {
                throw new ValidationException('Nieprawidłowe dane przedziału czasowego');
            }

            $updated = $this->workerAvailabilityService->updateWorkerAvailabilitySlot(
                $worker->getId(),
                $timeSlotId,
                $timeSlot['start'],
                $timeSlot['end'],
            );

            $updatedAt = $updated->getUpdatedAt() ?? $updated->getCreatedAt();

            return [
                'timeSlot' => $this->buildTimeSlotPayload($updated),
                'updatedAt' => $updatedAt->format(DATE_ATOM),
            ];
        });
    }

    #[Route(
        path: '/{date}/time-slots/{timeSlotId}',
        name: 'delete_time_slot',
        methods: [Request::METHOD_DELETE],
        requirements: [
            'date' => '\d{4}-\d{2}-\d{2}',
            'timeSlotId' => '[A-Za-z0-9\-]+',
        ],
    )]
    public function deleteTimeSlot(string $date, string $timeSlotId): JsonResponse
    {
        return $this->execute(function () use ($date, $timeSlotId) {
            $this->parseDate($date, 'date');
            $worker = $this->requireWorker();

            $deletedAt = $this->workerAvailabilityService->removeWorkerAvailabilitySlot(
                $worker->getId(),
                $timeSlotId,
            );

            return [
                'message' => 'Przedział czasowy został usunięty',
                'deletedAt' => $deletedAt->format(DATE_ATOM),
            ];
        });
    }

    #[Route(
        path: '/copy',
        name: 'copy',
        methods: [Request::METHOD_POST],
    )]
    public function copyAvailability(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $worker = $this->requireWorker();

            $payload = $this->getJsonBody($request);
            $dto = $this->hydrateCopyRequest($payload);
            $this->validateDto($dto);

            $sourceDate = $this->parseDate($dto->sourceDate, 'sourceDate');
            $targetDates = $this->parseTargetDates($dto->targetDates);
            $sourceDateKey = $sourceDate->format('Y-m-d');
            $targetDates = array_values(
                array_filter(
                    $targetDates,
                    static fn (DateTimeImmutable $date): bool => $date->format('Y-m-d') !== $sourceDateKey,
                ),
            );

            if ([] === $targetDates) {
                throw new ValidationException('Brak prawidłowych dat docelowych', [
                    'targetDates' => ['Wybierz co najmniej jedną datę docelową inną niż data źródłowa'],
                ]);
            }

            $result = $this->workerAvailabilityService->copyWorkerAvailability(
                $worker->getId(),
                $sourceDate,
                $targetDates,
                $dto->overwrite,
            );

            return $this->buildCopyAvailabilityPayload($result);
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateSaveRequest(array $payload): SaveWorkerAvailabilityRequest
    {
        $timeSlots = [];

        if (isset($payload['timeSlots']) && is_array($payload['timeSlots'])) {
            foreach ($payload['timeSlots'] as $slot) {
                if (!is_array($slot)) {
                    continue;
                }

                $timeSlots[] = new TimeSlotPayload(
                    startTime: (string) ($slot['startTime'] ?? ''),
                    endTime: (string) ($slot['endTime'] ?? ''),
                );
            }
        }

        return new SaveWorkerAvailabilityRequest($timeSlots);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateUpdateRequest(array $payload): UpdateWorkerTimeSlotRequest
    {
        return new UpdateWorkerTimeSlotRequest(
            startTime: (string) ($payload['startTime'] ?? ''),
            endTime: (string) ($payload['endTime'] ?? ''),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateCopyRequest(array $payload): CopyWorkerAvailabilityRequest
    {
        $targetDates = [];

        if (isset($payload['targetDates']) && is_array($payload['targetDates'])) {
            foreach ($payload['targetDates'] as $targetDate) {
                $targetDates[] = (string) $targetDate;
            }
        }

        return new CopyWorkerAvailabilityRequest(
            sourceDate: (string) ($payload['sourceDate'] ?? ''),
            targetDates: $targetDates,
            overwrite: (bool) ($payload['overwrite'] ?? false),
        );
    }

    /**
     * @param TimeSlotPayload[]|UpdateWorkerTimeSlotRequest[] $timeSlotDtos
     * @return array<int, array{start: DateTimeImmutable, end: DateTimeImmutable}>
     */
    private function buildTimeSlots(DateTimeImmutable $date, array $timeSlotDtos): array
    {
        $errors = [];
        $parsed = [];
        $dateString = $date->format('Y-m-d');

        foreach ($timeSlotDtos as $index => $timeSlot) {
            $startField = sprintf('timeSlots[%d].startTime', $index);
            $endField = sprintf('timeSlots[%d].endTime', $index);

            $start = $this->parseTimeForDate($dateString, $timeSlot->startTime, $startField, $errors);
            $end = $this->parseTimeForDate($dateString, $timeSlot->endTime, $endField, $errors);

            if (null === $start || null === $end) {
                continue;
            }

            if ($end <= $start) {
                $errors[$endField][] = 'Godzina zakończenia musi być późniejsza niż godzina rozpoczęcia';
            }

            if ($start->format('Y-m-d') !== $dateString || $end->format('Y-m-d') !== $dateString) {
                $errors[$endField][] = 'Przedział czasowy musi mieścić się w jednym dniu';
            }

            $parsed[] = [
                'start' => $start,
                'end' => $end,
                'index' => $index,
            ];
        }

        if ([] !== $errors) {
            throw new ValidationException('Nieprawidłowe przedziały czasowe', $errors);
        }

        usort(
            $parsed,
            static fn (array $left, array $right): int => $left['start'] <=> $right['start'],
        );

        $overlapDetected = false;
        $previousEnd = null;

        foreach ($parsed as $item) {
            if (null !== $previousEnd && $item['start'] < $previousEnd) {
                $overlapDetected = true;
                break;
            }

            $previousEnd = $item['end'];
        }

        if ($overlapDetected) {
            throw new ValidationException(
                'Przedziały czasowe nie mogą się nakładać',
                ['timeSlots' => ['Przedziały czasowe nie mogą się nakładać']],
            );
        }

        return array_map(
            static fn (array $item): array => [
                'start' => $item['start'],
                'end' => $item['end'],
            ],
            $parsed,
        );
    }

    /**
     * @param array<string, array<int, WorkerAvailabilityInterface>> $grouped
     */
    private function buildDayPayload(array $grouped, DateTimeImmutable $date): array
    {
        $dateKey = $date->format('Y-m-d');
        $slots = $grouped[$dateKey] ?? [];

        usort(
            $slots,
            static fn (WorkerAvailabilityInterface $left, WorkerAvailabilityInterface $right): int => $left->getStartDatetime() <=> $right->getStartDatetime(),
        );

        $totalSeconds = 0;
        $timeSlots = array_map(function (WorkerAvailabilityInterface $slot) use (&$totalSeconds): array {
            $start = $slot->getStartDatetime();
            $end = $slot->getEndDatetime();

            $totalSeconds += max(0, $end->getTimestamp() - $start->getTimestamp());

            return [
                'id' => $slot->getId(),
                'startTime' => $start->format('H:i'),
                'endTime' => $end->format('H:i'),
            ];
        }, $slots);

        return [
            'date' => $dateKey,
            'timeSlots' => $timeSlots,
            'totalHours' => round($totalSeconds / 3600, 2),
        ];
    }

    /**
     * @param iterable<WorkerAvailabilityInterface> $slots
     * @return array<int, array{date: string, timeSlots: array<int, array{id: string, startTime: string, endTime: string}>, totalHours: float}>
     */
    private function buildWeeklyAvailabilityPayload(iterable $slots, DateTimeImmutable $startDate): array
    {
        $grouped = [];

        foreach ($slots as $slot) {
            $dateKey = $slot->getStartDatetime()->format('Y-m-d');
            $grouped[$dateKey][] = $slot;
        }

        $days = [];
        $current = $startDate;

        for ($i = 0; $i < 7; ++$i) {
            $days[] = $this->buildDayPayload($grouped, $current);
            $current = $current->add(new DateInterval('P1D'));
        }

        return $days;
    }

    private function buildDayAvailabilityPayload(DayAvailabilityResultInterface $result): array
    {
        $grouped = [
            $result->getDate()->format('Y-m-d') => iterator_to_array($result->getTimeSlots()),
        ];

        $payload = $this->buildDayPayload($grouped, $result->getDate());
        $payload['updatedAt'] = $result->getUpdatedAt()->format(DATE_ATOM);

        return $payload;
    }

    private function buildCopyAvailabilityPayload(CopyAvailabilityResultInterface $result): array
    {
        $copied = [];

        foreach ($result->getCopied() as $dayResult) {
            $copied[] = $this->buildDayAvailabilityPayload($dayResult);
        }

        $skipped = array_map(
            static fn (DateTimeImmutable $date): string => $date->format('Y-m-d'),
            iterator_to_array($result->getSkippedDates()),
        );

        return [
            'copied' => $copied,
            'skipped' => $skipped,
        ];
    }

    private function buildTimeSlotPayload(WorkerAvailabilityInterface $slot): array
    {
        return [
            'id' => $slot->getId(),
            'startTime' => $slot->getStartDatetime()->format('H:i'),
            'endTime' => $slot->getEndDatetime()->format('H:i'),
        ];
    }

    /**
     * @param array<string, string[]> $errors
     */
    private function parseTimeForDate(
        string $date,
        string $time,
        string $field,
        array &$errors,
    ): ?DateTimeImmutable {
        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', sprintf('%s %s', $date, $time));

        $dateTimeErrors = DateTimeImmutable::getLastErrors();
        $hasErrors = false !== $dateTimeErrors
            && (
                ($dateTimeErrors['warning_count'] ?? 0) > 0
                || ($dateTimeErrors['error_count'] ?? 0) > 0
            );

        if (false === $dateTime || $hasErrors) {
            $errors[$field][] = 'Godzina musi być w formacie HH:mm';

            return null;
        }

        return $dateTime;
    }

    /**
     * @param string[] $targetDates
     * @return DateTimeImmutable[]
     */
    private function parseTargetDates(array $targetDates): array
    {
        $uniqueTargets = array_values(array_unique($targetDates));

        return array_map(
            fn (string $target): DateTimeImmutable => $this->parseDate($target, 'targetDates'),
            $uniqueTargets,
        );
    }

    private function parseDate(string $value, string $field): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        $hasErrors = false !== $errors
            && (
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

    private function requireWorker(): AuthenticatedWorker
    {
        return $this->workerProvider->getAuthenticatedWorker();
    }

    private function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today');
    }
}


