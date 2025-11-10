<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Authorization\Domain;

use App\Modules\Authorization\Domain\WorkerCategoryAssignment;
use App\Modules\Authorization\Domain\WorkerPermissions;
use PHPUnit\Framework\TestCase;

final class WorkerPermissionsTest extends TestCase
{
    public function testSynchronizeCategoriesKeepsExistingAssignmentsAndAddsNewOnes(): void
    {
        $existingAssignment = WorkerCategoryAssignment::reconstitute(
            'worker-1',
            'category-a',
            new \DateTimeImmutable('2025-01-01 08:00:00'),
        );

        $permissions = WorkerPermissions::reconstitute('worker-1', [$existingAssignment]);

        $assignedAt = new \DateTimeImmutable('2025-01-02 10:00:00');
        $permissions->synchronizeCategories(
            ['category-a', 'category-b', 'category-b'],
            'manager-1',
            $assignedAt,
        );

        $assignments = $permissions->getAssignments();
        self::assertCount(2, $assignments);

        $indexed = $this->indexAssignments($assignments);

        self::assertSame($existingAssignment, $indexed['category-a']);
        self::assertArrayHasKey('category-b', $indexed);
        self::assertEquals($assignedAt, $indexed['category-b']->getAssignedAt());
        self::assertSame('manager-1', $indexed['category-b']->getAssignedById());
    }

    public function testSynchronizeCategoriesRevokesRemovedAssignments(): void
    {
        $assignmentA = WorkerCategoryAssignment::assign('worker-1', 'category-a');
        $assignmentB = WorkerCategoryAssignment::assign('worker-1', 'category-b');
        $permissions = WorkerPermissions::reconstitute('worker-1', [$assignmentA, $assignmentB]);

        $permissions->synchronizeCategories(['category-b']);

        self::assertCount(1, $permissions->getAssignments());
        self::assertTrue($assignmentA->isRevoked());
        self::assertFalse($assignmentB->isRevoked());
    }

    public function testSetManagerRoleCreatesAndUpdatesRole(): void
    {
        $permissions = WorkerPermissions::createEmpty('worker-1');

        $permissions->setManagerRole(true);
        self::assertTrue($permissions->isManager());
        $role = $permissions->getRole();
        self::assertNotNull($role);
        self::assertTrue($role->isManager());

        $permissions->setManagerRole(false);
        self::assertFalse($permissions->isManager());
        self::assertFalse($role->isManager());
    }

    public function testRemoveCategoryMarksAssignmentAsRevoked(): void
    {
        $assignment = WorkerCategoryAssignment::assign('worker-1', 'category-a');
        $permissions = WorkerPermissions::reconstitute('worker-1', [$assignment]);

        $permissions->removeCategory('category-a');

        self::assertSame([], $permissions->getCategoryIds());
        self::assertTrue($assignment->isRevoked());
    }

    /**
     * @param WorkerCategoryAssignment[] $assignments
     *
     * @return array<string, WorkerCategoryAssignment>
     */
    private function indexAssignments(array $assignments): array
    {
        $indexed = [];

        foreach ($assignments as $assignment) {
            $indexed[$assignment->getCategoryId()] = $assignment;
        }

        return $indexed;
    }
}
