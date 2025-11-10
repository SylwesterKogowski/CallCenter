<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Manager\Service;

use App\Modules\BackendForFrontend\Manager\Dto\UpdateAutoAssignmentSettingsInput;

interface ManagerMonitoringServiceInterface
{
    /**
     * @return array{
     *     date: string,
     *     summary: array{
     *         totalTickets: int,
     *         totalWorkers: int,
     *         totalQueues: int,
     *         averageWorkload: float|int,
     *         averageResolutionTime: int,
     *         waitingTicketsTotal: int,
     *         inProgressTicketsTotal: int,
     *         completedTicketsTotal: int
     *     },
     *     workerStats: list<array{
     *         workerId: string,
     *         workerLogin: string,
     *         ticketsCount: int,
     *         timeSpent: int,
     *         timePlanned: int,
     *         workloadLevel: string,
     *         efficiency: float|int,
     *         categories: string[],
     *         completedTickets: int,
     *         inProgressTickets: int,
     *         waitingTickets: int
     *     }>,
     *     queueStats: list<array{
     *         queueId: string,
     *         queueName: string,
     *         waitingTickets: int,
     *         inProgressTickets: int,
     *         completedTickets: int,
     *         totalTickets: int,
     *         averageResolutionTime: int,
     *         assignedWorkers: int
     *     }>,
     *     autoAssignmentSettings: array{
     *         enabled: bool,
     *         lastRun: \DateTimeImmutable|null,
     *         ticketsAssigned: int,
     *         settings: array{
     *             considerEfficiency: bool,
     *             considerAvailability: bool,
     *             maxTicketsPerWorker: int
     *         }
     *     }
     * }
     */
    public function getMonitoringData(string $managerId, \DateTimeImmutable $date): array;

    /**
     * @return array{
     *     autoAssignmentSettings: array{
     *         enabled: bool,
     *         lastRun: \DateTimeImmutable|null,
     *         ticketsAssigned: int,
     *         settings: array{
     *             considerEfficiency: bool,
     *             considerAvailability: bool,
     *             maxTicketsPerWorker: int
     *         }
     *     },
     *     updatedAt: \DateTimeImmutable
     * }
     */
    public function updateAutoAssignmentSettings(
        string $managerId,
        bool $enabled,
        UpdateAutoAssignmentSettingsInput $settings,
    ): array;

    /**
     * @return array{
     *     message: string,
     *     ticketsAssigned: int,
     *     assignedTo: list<array{workerId: string, ticketsCount: int}>,
     *     completedAt: \DateTimeImmutable
     * }
     */
    public function triggerAutoAssignment(string $managerId, \DateTimeImmutable $date): array;

    /**
     * @param callable(string $eventType, array<string, mixed> $payload, \DateTimeImmutable $timestamp): void $emit
     */
    public function streamMonitoringEvents(
        string $managerId,
        \DateTimeImmutable $date,
        callable $emit,
    ): void;
}
