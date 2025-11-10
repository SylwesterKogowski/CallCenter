<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Authorization\Application;

use App\Modules\Authorization\Application\AuthorizationService;
use App\Modules\Authorization\Application\AuthorizationServiceInterface;
use App\Modules\Authorization\Domain\AuthorizationRepositoryInterface;
use App\Modules\Authorization\Domain\WorkerCategoryAssignment;
use App\Modules\Authorization\Domain\WorkerPermissions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AuthorizationServiceTest extends TestCase
{
    private AuthorizationRepositoryInterface&MockObject $repository;

    private AuthorizationServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(AuthorizationRepositoryInterface::class);
        $this->service = new AuthorizationService($this->repository);
    }

    public function testAssignCategoriesToWorkerDelegatesToRepository(): void
    {
        $permissions = WorkerPermissions::createEmpty('worker-1');

        $this->repository
            ->expects(self::once())
            ->method('loadPermissions')
            ->with('worker-1')
            ->willReturn($permissions);

        $this->repository
            ->expects(self::once())
            ->method('savePermissions')
            ->with(self::callback(function (WorkerPermissions $saved) use ($permissions): bool {
                self::assertSame($permissions, $saved);
                self::assertSame(['category-a', 'category-b'], $saved->getCategoryIds());

                return true;
            }));

        $this->service->assignCategoriesToWorker(
            ' worker-1 ',
            ['category-a', 'category-b', 'category-a', ''],
            ' manager-1 ',
            new \DateTimeImmutable('2025-01-05 12:00:00'),
        );
    }

    public function testRemoveCategoryFromWorkerPersistsChanges(): void
    {
        $assignment = WorkerCategoryAssignment::assign('worker-1', 'category-a');
        $permissions = WorkerPermissions::reconstitute('worker-1', [$assignment]);

        $this->repository
            ->expects(self::once())
            ->method('loadPermissions')
            ->with('worker-1')
            ->willReturn($permissions);

        $this->repository
            ->expects(self::once())
            ->method('savePermissions')
            ->with(self::callback(function (WorkerPermissions $saved) use ($permissions): bool {
                self::assertSame($permissions, $saved);
                self::assertSame([], $saved->getCategoryIds());

                return true;
            }));

        $this->service->removeCategoryFromWorker('worker-1', 'category-a');
    }

    public function testSetManagerRolePersistsFlag(): void
    {
        $permissions = WorkerPermissions::createEmpty('worker-1');

        $this->repository
            ->expects(self::once())
            ->method('loadPermissions')
            ->with('worker-1')
            ->willReturn($permissions);

        $this->repository
            ->expects(self::once())
            ->method('savePermissions')
            ->with(self::callback(function (WorkerPermissions $saved) use ($permissions): bool {
                self::assertSame($permissions, $saved);
                self::assertTrue($saved->isManager());

                return true;
            }));

        $this->service->setManagerRole('worker-1', true);
    }

    public function testGetWorkerPermissionsReturnsStructuredArray(): void
    {
        $permissions = WorkerPermissions::reconstitute(
            'worker-1',
            [
                WorkerCategoryAssignment::assign('worker-1', 'category-a'),
                WorkerCategoryAssignment::assign('worker-1', 'category-b'),
            ],
        );
        $permissions->setManagerRole(true);

        $this->repository
            ->expects(self::once())
            ->method('loadPermissions')
            ->with('worker-1')
            ->willReturn($permissions);

        $result = $this->service->getWorkerPermissions('worker-1');

        self::assertSame('worker-1', $result['workerId']);
        self::assertEqualsCanonicalizing(['category-a', 'category-b'], $result['categoryIds']);
        self::assertTrue($result['isManager']);
    }

    public function testGetAssignedCategoryIdsReturnsNormalizedArray(): void
    {
        $permissions = WorkerPermissions::reconstitute(
            'worker-1',
            [
                WorkerCategoryAssignment::assign('worker-1', 'category-a'),
                WorkerCategoryAssignment::assign('worker-1', 'category-b'),
            ],
        );

        $this->repository
            ->expects(self::once())
            ->method('loadPermissions')
            ->with('worker-1')
            ->willReturn($permissions);

        $result = $this->service->getAssignedCategoryIds('worker-1');

        self::assertEqualsCanonicalizing(['category-a', 'category-b'], $result);
    }

    public function testCanWorkerAccessCategoryEvaluatesAssignmentsAndManagerRole(): void
    {
        $firstPermissions = WorkerPermissions::reconstitute(
            'worker-1',
            [WorkerCategoryAssignment::assign('worker-1', 'category-a')],
        );

        $secondPermissions = WorkerPermissions::createEmpty('worker-1');

        $thirdPermissions = WorkerPermissions::createEmpty('worker-1');
        $thirdPermissions->setManagerRole(true);

        $this->repository
            ->expects(self::exactly(3))
            ->method('loadPermissions')
            ->with('worker-1')
            ->willReturnOnConsecutiveCalls($firstPermissions, $secondPermissions, $thirdPermissions);

        self::assertTrue($this->service->canWorkerAccessCategory('worker-1', 'category-a'));
        self::assertFalse($this->service->canWorkerAccessCategory('worker-1', 'category-b'));
        self::assertTrue($this->service->canWorkerAccessCategory('worker-1', 'category-x'));
    }
}
