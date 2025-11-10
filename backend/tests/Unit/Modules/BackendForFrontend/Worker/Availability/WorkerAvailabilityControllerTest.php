<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Worker\Availability;

use App\Modules\BackendForFrontend\Shared\Exception\AuthenticationException;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use App\Modules\BackendForFrontend\Worker\Availability\WorkerAvailabilityController;
use App\Modules\WorkerAvailability\Application\Dto\CopyAvailabilityResultInterface;
use App\Modules\WorkerAvailability\Application\Dto\DayAvailabilityResultInterface;
use App\Modules\WorkerAvailability\Domain\WorkerAvailabilityInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class WorkerAvailabilityControllerTest extends BackendForFrontendTestCase
{
    public function testGetAvailabilityRequiresAuthenticatedWorker(): void
    {
        $provider = $this->createMock(AuthenticatedWorkerProvider::class);
        $provider
            ->expects(self::once())
            ->method('getAuthenticatedWorker')
            ->willThrowException(new AuthenticationException('Brak aktywnej sesji pracownika'));

        $this->workerAvailabilityService
            ->expects(self::never())
            ->method('getWorkerAvailabilityForWeek');

        $this->createClientWithMocks($provider);

        /** @var WorkerAvailabilityController $controller */
        $controller = static::getContainer()->get(WorkerAvailabilityController::class);

        $response = $controller->getAvailability(Request::create(
            '/api/worker/availability',
            Request::METHOD_GET,
        ));
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('Brak aktywnej sesji pracownika', $data['message'] ?? null);
    }

    public function testGetAvailabilityReturnsNormalizedWeekStructure(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $slotMonday = $this->createConfiguredMock(WorkerAvailabilityInterface::class, [
            'getId' => 'slot-monday',
            'getWorkerId' => 'worker-id',
            'getStartDatetime' => new \DateTimeImmutable('2024-06-10T09:00:00+00:00'),
            'getEndDatetime' => new \DateTimeImmutable('2024-06-10T11:30:00+00:00'),
            'getCreatedAt' => new \DateTimeImmutable('2024-06-09T12:00:00+00:00'),
            'getUpdatedAt' => new \DateTimeImmutable('2024-06-10T12:00:00+00:00'),
        ]);

        $slotWednesday = $this->createConfiguredMock(WorkerAvailabilityInterface::class, [
            'getId' => 'slot-wednesday',
            'getWorkerId' => 'worker-id',
            'getStartDatetime' => new \DateTimeImmutable('2024-06-12T14:00:00+00:00'),
            'getEndDatetime' => new \DateTimeImmutable('2024-06-12T16:00:00+00:00'),
            'getCreatedAt' => new \DateTimeImmutable('2024-06-11T12:00:00+00:00'),
            'getUpdatedAt' => new \DateTimeImmutable('2024-06-12T17:00:00+00:00'),
        ]);

        $this->workerAvailabilityService
            ->expects(self::once())
            ->method('getWorkerAvailabilityForWeek')
            ->with(
                'worker-id',
                self::callback(static fn (\DateTimeImmutable $date): bool => '2024-06-10' === $date->format('Y-m-d')),
            )
            ->willReturn([$slotMonday, $slotWednesday]);

        $this->createClientWithMocks($provider);

        /** @var WorkerAvailabilityController $controller */
        $controller = static::getContainer()->get(WorkerAvailabilityController::class);

        $response = $controller->getAvailability(Request::create(
            '/api/worker/availability',
            Request::METHOD_GET,
            ['startDate' => '2024-06-10'],
        ));

        $data = json_decode((string) $response->getContent(), true);
        $availability = $data['availability'] ?? null;

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertIsArray($availability);
        self::assertCount(7, $availability);

        self::assertSame([
            'date' => '2024-06-10',
            'timeSlots' => [
                [
                    'id' => 'slot-monday',
                    'startTime' => '09:00',
                    'endTime' => '11:30',
                ],
            ],
            'totalHours' => 2.5,
        ], $availability[0]);

        self::assertSame([
            'date' => '2024-06-11',
            'timeSlots' => [],
            'totalHours' => 0,
        ], $availability[1]);

        self::assertSame([
            'date' => '2024-06-12',
            'timeSlots' => [
                [
                    'id' => 'slot-wednesday',
                    'startTime' => '14:00',
                    'endTime' => '16:00',
                ],
            ],
            'totalHours' => 2,
        ], $availability[2]);
    }

    public function testSaveAvailabilityValidatesTimeSlots(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $this->workerAvailabilityService
            ->expects(self::never())
            ->method('replaceWorkerAvailabilityForDay');

        $this->createClientWithMocks($provider);

        /** @var WorkerAvailabilityController $controller */
        $controller = static::getContainer()->get(WorkerAvailabilityController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/availability/2024-06-12',
            [
                'timeSlots' => [
                    [
                        'startTime' => '09:00',
                        'endTime' => '08:30',
                    ],
                ],
            ],
        );

        $response = $controller->saveAvailabilityForDay('2024-06-12', $request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Nieprawidłowe przedziały czasowe', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('timeSlots[0].endTime', $data['errors']);
        self::assertSame(
            'Godzina zakończenia musi być późniejsza niż godzina rozpoczęcia',
            $data['errors']['timeSlots[0].endTime'][0] ?? null,
        );
    }

    public function testSaveAvailabilityPersistsAvailabilityAndReturnsUpdatedSlots(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $updatedSlotMorning = $this->createConfiguredMock(WorkerAvailabilityInterface::class, [
            'getId' => 'slot-1',
            'getWorkerId' => 'worker-id',
            'getStartDatetime' => new \DateTimeImmutable('2024-06-12T09:00:00+00:00'),
            'getEndDatetime' => new \DateTimeImmutable('2024-06-12T11:30:00+00:00'),
            'getCreatedAt' => new \DateTimeImmutable('2024-06-12T07:00:00+00:00'),
            'getUpdatedAt' => new \DateTimeImmutable('2024-06-12T15:00:00+00:00'),
        ]);

        $updatedSlotAfternoon = $this->createConfiguredMock(WorkerAvailabilityInterface::class, [
            'getId' => 'slot-2',
            'getWorkerId' => 'worker-id',
            'getStartDatetime' => new \DateTimeImmutable('2024-06-12T13:00:00+00:00'),
            'getEndDatetime' => new \DateTimeImmutable('2024-06-12T15:00:00+00:00'),
            'getCreatedAt' => new \DateTimeImmutable('2024-06-12T12:00:00+00:00'),
            'getUpdatedAt' => new \DateTimeImmutable('2024-06-12T16:00:00+00:00'),
        ]);

        $dayResult = $this->createMock(DayAvailabilityResultInterface::class);
        $dayResult
            ->method('getDate')
            ->willReturn(new \DateTimeImmutable('2024-06-12'));
        $dayResult
            ->method('getTimeSlots')
            ->willReturn([$updatedSlotMorning, $updatedSlotAfternoon]);
        $dayResult
            ->method('getUpdatedAt')
            ->willReturn(new \DateTimeImmutable('2024-06-12T18:45:00+00:00'));

        $this->workerAvailabilityService
            ->expects(self::once())
            ->method('replaceWorkerAvailabilityForDay')
            ->with(
                'worker-id',
                self::callback(static fn (\DateTimeImmutable $date): bool => '2024-06-12' === $date->format('Y-m-d')),
                self::callback(static function (iterable $timeSlots): bool {
                    $slots = is_array($timeSlots) ? $timeSlots : iterator_to_array($timeSlots);
                    self::assertCount(2, $slots);
                    self::assertSame('2024-06-12T09:00:00+00:00', $slots[0]['start']->format(DATE_ATOM));
                    self::assertSame('2024-06-12T11:30:00+00:00', $slots[0]['end']->format(DATE_ATOM));
                    self::assertSame('2024-06-12T13:00:00+00:00', $slots[1]['start']->format(DATE_ATOM));
                    self::assertSame('2024-06-12T15:00:00+00:00', $slots[1]['end']->format(DATE_ATOM));

                    return true;
                }),
            )
            ->willReturn($dayResult);

        $this->createClientWithMocks($provider);

        /** @var WorkerAvailabilityController $controller */
        $controller = static::getContainer()->get(WorkerAvailabilityController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/availability/2024-06-12',
            [
                'timeSlots' => [
                    ['startTime' => '09:00', 'endTime' => '11:30'],
                    ['startTime' => '13:00', 'endTime' => '15:00'],
                ],
            ],
        );

        $response = $controller->saveAvailabilityForDay('2024-06-12', $request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            'date' => '2024-06-12',
            'timeSlots' => [
                [
                    'id' => 'slot-1',
                    'startTime' => '09:00',
                    'endTime' => '11:30',
                ],
                [
                    'id' => 'slot-2',
                    'startTime' => '13:00',
                    'endTime' => '15:00',
                ],
            ],
            'totalHours' => 4.5,
            'updatedAt' => '2024-06-12T18:45:00+00:00',
        ], $data);
    }

    public function testUpdateTimeSlotValidatesPayload(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $this->workerAvailabilityService
            ->expects(self::never())
            ->method('updateWorkerAvailabilitySlot');

        $this->createClientWithMocks($provider);

        /** @var WorkerAvailabilityController $controller */
        $controller = static::getContainer()->get(WorkerAvailabilityController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_PUT,
            '/api/worker/availability/2024-06-13/time-slots/slot-123',
            [
                'startTime' => '',
                'endTime' => '10:00',
            ],
        );

        $response = $controller->updateTimeSlot('2024-06-13', 'slot-123', $request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Błędne dane wejściowe', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertSame(
            'Godzina rozpoczęcia jest wymagana',
            $data['errors']['startTime'][0] ?? null,
        );
    }

    public function testUpdateTimeSlotReturnsUpdatedSlot(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $updatedSlot = $this->createConfiguredMock(WorkerAvailabilityInterface::class, [
            'getId' => 'slot-123',
            'getWorkerId' => 'worker-id',
            'getStartDatetime' => new \DateTimeImmutable('2024-06-13T08:00:00+00:00'),
            'getEndDatetime' => new \DateTimeImmutable('2024-06-13T10:30:00+00:00'),
            'getCreatedAt' => new \DateTimeImmutable('2024-06-12T09:00:00+00:00'),
            'getUpdatedAt' => new \DateTimeImmutable('2024-06-13T11:00:00+00:00'),
        ]);

        $this->workerAvailabilityService
            ->expects(self::once())
            ->method('updateWorkerAvailabilitySlot')
            ->with(
                'worker-id',
                'slot-123',
                self::callback(static fn (\DateTimeImmutable $start): bool => '2024-06-13T08:00:00+00:00' === $start->format(DATE_ATOM)),
                self::callback(static fn (\DateTimeImmutable $end): bool => '2024-06-13T10:30:00+00:00' === $end->format(DATE_ATOM)),
            )
            ->willReturn($updatedSlot);

        $this->createClientWithMocks($provider);

        /** @var WorkerAvailabilityController $controller */
        $controller = static::getContainer()->get(WorkerAvailabilityController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_PUT,
            '/api/worker/availability/2024-06-13/time-slots/slot-123',
            [
                'startTime' => '08:00',
                'endTime' => '10:30',
            ],
        );

        $response = $controller->updateTimeSlot('2024-06-13', 'slot-123', $request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            'timeSlot' => [
                'id' => 'slot-123',
                'startTime' => '08:00',
                'endTime' => '10:30',
            ],
            'updatedAt' => '2024-06-13T11:00:00+00:00',
        ], $data);
    }

    public function testDeleteTimeSlotRemovesSlot(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $this->workerAvailabilityService
            ->expects(self::once())
            ->method('removeWorkerAvailabilitySlot')
            ->with('worker-id', 'slot-999')
            ->willReturn(new \DateTimeImmutable('2024-06-14T08:00:00+00:00'));

        $this->createClientWithMocks($provider);

        /** @var WorkerAvailabilityController $controller */
        $controller = static::getContainer()->get(WorkerAvailabilityController::class);

        $response = $controller->deleteTimeSlot('2024-06-14', 'slot-999');
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Przedział czasowy został usunięty', $data['message'] ?? null);
        self::assertSame('2024-06-14T08:00:00+00:00', $data['deletedAt'] ?? null);
    }

    public function testCopyAvailabilityValidatesTargetDates(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $this->workerAvailabilityService
            ->expects(self::never())
            ->method('copyWorkerAvailability');

        $this->createClientWithMocks($provider);

        /** @var WorkerAvailabilityController $controller */
        $controller = static::getContainer()->get(WorkerAvailabilityController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/availability/copy',
            [
                'sourceDate' => '2024-06-10',
                'targetDates' => [],
                'overwrite' => true,
            ],
        );

        $response = $controller->copyAvailability($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Błędne dane wejściowe', $data['message'] ?? null);
        self::assertArrayHasKey('errors', $data);
        self::assertSame(
            'Podaj co najmniej jedną datę docelową',
            $data['errors']['targetDates'][0] ?? null,
        );
    }

    public function testCopyAvailabilityRespectsOverwriteAndReturnsResult(): void
    {
        $provider = $this->stubAuthenticatedWorkerProvider(
            $this->createAuthenticatedWorkerFixture(false),
        );

        $copiedDayOneSlot = $this->createConfiguredMock(WorkerAvailabilityInterface::class, [
            'getId' => 'day1-slot1',
            'getWorkerId' => 'worker-id',
            'getStartDatetime' => new \DateTimeImmutable('2024-06-11T09:00:00+00:00'),
            'getEndDatetime' => new \DateTimeImmutable('2024-06-11T12:00:00+00:00'),
            'getCreatedAt' => new \DateTimeImmutable('2024-06-11T07:00:00+00:00'),
            'getUpdatedAt' => new \DateTimeImmutable('2024-06-11T13:00:00+00:00'),
        ]);

        $copiedDayTwoMorning = $this->createConfiguredMock(WorkerAvailabilityInterface::class, [
            'getId' => 'day2-slot1',
            'getWorkerId' => 'worker-id',
            'getStartDatetime' => new \DateTimeImmutable('2024-06-12T08:00:00+00:00'),
            'getEndDatetime' => new \DateTimeImmutable('2024-06-12T10:00:00+00:00'),
            'getCreatedAt' => new \DateTimeImmutable('2024-06-12T06:30:00+00:00'),
            'getUpdatedAt' => new \DateTimeImmutable('2024-06-12T11:00:00+00:00'),
        ]);

        $copiedDayTwoAfternoon = $this->createConfiguredMock(WorkerAvailabilityInterface::class, [
            'getId' => 'day2-slot2',
            'getWorkerId' => 'worker-id',
            'getStartDatetime' => new \DateTimeImmutable('2024-06-12T13:30:00+00:00'),
            'getEndDatetime' => new \DateTimeImmutable('2024-06-12T15:00:00+00:00'),
            'getCreatedAt' => new \DateTimeImmutable('2024-06-12T12:45:00+00:00'),
            'getUpdatedAt' => new \DateTimeImmutable('2024-06-12T15:30:00+00:00'),
        ]);

        $dayOneResult = $this->createMock(DayAvailabilityResultInterface::class);
        $dayOneResult
            ->method('getDate')
            ->willReturn(new \DateTimeImmutable('2024-06-11'));
        $dayOneResult
            ->method('getTimeSlots')
            ->willReturn([$copiedDayOneSlot]);
        $dayOneResult
            ->method('getUpdatedAt')
            ->willReturn(new \DateTimeImmutable('2024-06-11T18:00:00+00:00'));

        $dayTwoResult = $this->createMock(DayAvailabilityResultInterface::class);
        $dayTwoResult
            ->method('getDate')
            ->willReturn(new \DateTimeImmutable('2024-06-12'));
        $dayTwoResult
            ->method('getTimeSlots')
            ->willReturn([$copiedDayTwoMorning, $copiedDayTwoAfternoon]);
        $dayTwoResult
            ->method('getUpdatedAt')
            ->willReturn(new \DateTimeImmutable('2024-06-12T19:15:00+00:00'));

        $copyResult = $this->createMock(CopyAvailabilityResultInterface::class);
        $copyResult
            ->method('getCopied')
            ->willReturn([$dayOneResult, $dayTwoResult]);
        $copyResult
            ->method('getSkippedDates')
            ->willReturn([new \DateTimeImmutable('2024-06-13')]);

        $this->workerAvailabilityService
            ->expects(self::once())
            ->method('copyWorkerAvailability')
            ->with(
                'worker-id',
                self::callback(static fn (\DateTimeImmutable $source): bool => '2024-06-10' === $source->format('Y-m-d')),
                self::callback(static function (array $targets): bool {
                    self::assertCount(2, $targets);
                    self::assertSame('2024-06-11', $targets[0]->format('Y-m-d'));
                    self::assertSame('2024-06-12', $targets[1]->format('Y-m-d'));

                    return true;
                }),
                true,
            )
            ->willReturn($copyResult);

        $this->createClientWithMocks($provider);

        /** @var WorkerAvailabilityController $controller */
        $controller = static::getContainer()->get(WorkerAvailabilityController::class);

        $request = $this->createJsonRequest(
            Request::METHOD_POST,
            '/api/worker/availability/copy',
            [
                'sourceDate' => '2024-06-10',
                'targetDates' => [
                    '2024-06-10',
                    '2024-06-11',
                    '2024-06-11',
                    '2024-06-12',
                ],
                'overwrite' => true,
            ],
        );

        $response = $controller->copyAvailability($request);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            'copied' => [
                [
                    'date' => '2024-06-11',
                    'timeSlots' => [
                        [
                            'id' => 'day1-slot1',
                            'startTime' => '09:00',
                            'endTime' => '12:00',
                        ],
                    ],
                    'totalHours' => 3,
                    'updatedAt' => '2024-06-11T18:00:00+00:00',
                ],
                [
                    'date' => '2024-06-12',
                    'timeSlots' => [
                        [
                            'id' => 'day2-slot1',
                            'startTime' => '08:00',
                            'endTime' => '10:00',
                        ],
                        [
                            'id' => 'day2-slot2',
                            'startTime' => '13:30',
                            'endTime' => '15:00',
                        ],
                    ],
                    'totalHours' => 3.5,
                    'updatedAt' => '2024-06-12T19:15:00+00:00',
                ],
            ],
            'skipped' => ['2024-06-13'],
        ], $data);
    }
}
