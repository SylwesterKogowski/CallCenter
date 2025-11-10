<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Authentication\Application;

use App\Modules\Authentication\Application\AuthenticationService;
use App\Modules\Authentication\Domain\Exception\WorkerAlreadyExistsException;
use App\Modules\Authentication\Domain\Worker;
use App\Modules\Authentication\Domain\WorkerInterface;
use App\Modules\Authentication\Domain\WorkerRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AuthenticationServiceTest extends TestCase
{
    /** @var WorkerRepositoryInterface&MockObject */
    private WorkerRepositoryInterface $repository;

    private AuthenticationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(WorkerRepositoryInterface::class);
        $this->service = new AuthenticationService($this->repository);
    }

    public function testRegisterWorkerPersistsNewWorkerAndReturnsHashedEntity(): void
    {
        $captured = null;
        $this->repository
            ->expects(self::once())
            ->method('findByLogin')
            ->with('john.doe')
            ->willReturn(null);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (WorkerInterface $worker) use (&$captured): bool {
                $captured = $worker;

                return true;
            }));

        $worker = $this->service->registerWorker('  john.doe  ', 'Secret123!');

        self::assertSame($captured, $worker);
        self::assertNotEmpty($worker->getId());
        self::assertSame('john.doe', $worker->getLogin());
        self::assertTrue($worker->verifyPassword('Secret123!'));
    }

    public function testRegisterWorkerThrowsWhenLoginAlreadyExists(): void
    {
        $existing = Worker::register('john.doe', 'Secret123!');

        $this->repository
            ->expects(self::once())
            ->method('findByLogin')
            ->with('john.doe')
            ->willReturn($existing);

        $this->repository->expects(self::never())->method('save');

        $this->expectException(WorkerAlreadyExistsException::class);

        $this->service->registerWorker('john.doe', 'AnotherSecret!');
    }

    public function testAuthenticateWorkerReturnsWorkerWhenCredentialsMatchAndRehashesIfNecessary(): void
    {
        $weakHash = password_hash('Secret123!', PASSWORD_BCRYPT, ['cost' => 4]);
        $worker = Worker::reconstitute(
            '11111111-1111-4111-8111-111111111111',
            'john.doe',
            $weakHash,
            false,
            new \DateTimeImmutable('-1 day'),
            null,
        );

        $this->repository
            ->expects(self::once())
            ->method('findByLogin')
            ->with('john.doe')
            ->willReturn($worker);

        $this->repository
            ->expects(self::once())
            ->method('update')
            ->with($worker);

        $authenticated = $this->service->authenticateWorker('john.doe', 'Secret123!');

        self::assertSame($worker, $authenticated);
        self::assertTrue($worker->verifyPassword('Secret123!'));
    }

    public function testAuthenticateWorkerReturnsNullWhenPasswordDoesNotMatch(): void
    {
        $worker = Worker::register('john.doe', 'Secret123!');

        $this->repository
            ->expects(self::once())
            ->method('findByLogin')
            ->with('john.doe')
            ->willReturn($worker);

        $this->repository->expects(self::never())->method('update');

        $result = $this->service->authenticateWorker('john.doe', 'WrongPassword!');

        self::assertNull($result);
    }

    public function testChangePasswordVerifiesOldPasswordAndPersistsNewHash(): void
    {
        $worker = Worker::register('john.doe', 'Secret123!');

        $this->repository
            ->expects(self::once())
            ->method('update')
            ->with($worker);

        $this->service->changePassword($worker, 'Secret123!', 'NewPassword$1');

        self::assertTrue($worker->verifyPassword('NewPassword$1'));
    }
}
