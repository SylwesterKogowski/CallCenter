<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Shared;

use App\Modules\Authentication\Application\AuthenticationServiceInterface;
use App\Modules\Authorization\Application\AuthorizationServiceInterface;
use App\Modules\BackendForFrontend\Manager\Service\ManagerMonitoringServiceInterface;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorker;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use App\Modules\BackendForFrontend\Worker\Phone\Service\WorkerPhoneServiceInterface;
use App\Modules\Clients\Application\ClientSearchServiceInterface;
use App\Modules\Clients\Application\ClientServiceInterface;
use App\Modules\TicketCategories\Application\TicketCategoryServiceInterface;
use App\Modules\Tickets\Application\TicketBacklogServiceInterface;
use App\Modules\Tickets\Application\TicketSearchServiceInterface;
use App\Modules\Tickets\Application\TicketServiceInterface;
use App\Modules\WorkerAvailability\Application\WorkerAvailabilityServiceInterface;
use App\Modules\WorkerSchedule\Application\WorkerScheduleServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

abstract class BackendForFrontendTestCase extends WebTestCase
{
    /**
     * @var AuthenticationServiceInterface&MockObject
     */
    protected AuthenticationServiceInterface $authenticationService;

    /**
     * @var AuthorizationServiceInterface&MockObject
     */
    protected AuthorizationServiceInterface $authorizationService;

    /**
     * @var TicketCategoryServiceInterface&MockObject
     */
    protected TicketCategoryServiceInterface $ticketCategoryService;

    /**
     * @var TicketServiceInterface&MockObject
     */
    protected TicketServiceInterface $ticketService;

    /**
     * @var TicketBacklogServiceInterface&MockObject
     */
    protected TicketBacklogServiceInterface $ticketBacklogService;

    /**
     * @var TicketSearchServiceInterface&MockObject
     */
    protected TicketSearchServiceInterface $ticketSearchService;

    /**
     * @var WorkerAvailabilityServiceInterface&MockObject
     */
    protected WorkerAvailabilityServiceInterface $workerAvailabilityService;

    /**
     * @var WorkerScheduleServiceInterface&MockObject
     */
    protected WorkerScheduleServiceInterface $workerScheduleService;

    /**
     * @var ClientServiceInterface&MockObject
     */
    protected ClientServiceInterface $clientService;

    /**
     * @var ClientSearchServiceInterface&MockObject
     */
    protected ClientSearchServiceInterface $clientSearchService;

    /**
     * @var ManagerMonitoringServiceInterface&MockObject
     */
    protected ManagerMonitoringServiceInterface $managerMonitoringService;

    /**
     * @var WorkerPhoneServiceInterface&MockObject
     */
    protected WorkerPhoneServiceInterface $workerPhoneService;

    /**
     * @var AuthenticatedWorkerProvider&MockObject
     */
    protected AuthenticatedWorkerProvider $authenticatedWorkerProvider;

    protected function setUp(): void
    {
        parent::setUp();

        static::ensureKernelShutdown();

        $this->authenticationService = $this->createMock(AuthenticationServiceInterface::class);
        $this->authorizationService = $this->createMock(AuthorizationServiceInterface::class);
        $this->ticketCategoryService = $this->createMock(TicketCategoryServiceInterface::class);
        $this->ticketService = $this->createMock(TicketServiceInterface::class);
        $this->ticketBacklogService = $this->createMock(TicketBacklogServiceInterface::class);
        $this->ticketSearchService = $this->createMock(TicketSearchServiceInterface::class);
        $this->workerAvailabilityService = $this->createMock(WorkerAvailabilityServiceInterface::class);
        $this->workerScheduleService = $this->createMock(WorkerScheduleServiceInterface::class);
        $this->clientService = $this->createMock(ClientServiceInterface::class);
        $this->clientSearchService = $this->createMock(ClientSearchServiceInterface::class);
        $this->managerMonitoringService = $this->createMock(ManagerMonitoringServiceInterface::class);
        $this->workerPhoneService = $this->createMock(WorkerPhoneServiceInterface::class);

        $this->authenticatedWorkerProvider = $this->stubAuthenticatedWorkerProvider();
    }

    protected function createJsonRequest(string $method, string $uri, array $payload = []): Request
    {
        return Request::create(
            $uri,
            $method,
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    protected function createAuthenticatedWorkerFixture(
        bool $isManager = false,
        array $categoryIds = ['category-1'],
    ): AuthenticatedWorker {
        return new AuthenticatedWorker('worker-id', 'worker-login', $isManager, $categoryIds);
    }

    protected function createManagerFixture(array $categoryIds = ['category-1']): AuthenticatedWorker
    {
        return $this->createAuthenticatedWorkerFixture(true, $categoryIds);
    }

    protected function stubAuthenticatedWorkerProvider(
        ?AuthenticatedWorker $worker = null,
    ): AuthenticatedWorkerProvider {
        $provider = $this->createMock(AuthenticatedWorkerProvider::class);
        $provider
            ->method('getAuthenticatedWorker')
            ->willReturn($worker ?? $this->createAuthenticatedWorkerFixture());

        return $provider;
    }

    protected function registerMockServices(): void
    {
        $container = static::getContainer();

        $container->set(AuthenticationServiceInterface::class, $this->authenticationService);
        $container->set(AuthorizationServiceInterface::class, $this->authorizationService);
        $container->set(TicketCategoryServiceInterface::class, $this->ticketCategoryService);
        $container->set(TicketServiceInterface::class, $this->ticketService);
        $container->set(TicketBacklogServiceInterface::class, $this->ticketBacklogService);
        $container->set(TicketSearchServiceInterface::class, $this->ticketSearchService);
        $container->set(WorkerAvailabilityServiceInterface::class, $this->workerAvailabilityService);
        $container->set(WorkerScheduleServiceInterface::class, $this->workerScheduleService);
        $container->set(ClientServiceInterface::class, $this->clientService);
        $container->set(ClientSearchServiceInterface::class, $this->clientSearchService);
        $container->set(ManagerMonitoringServiceInterface::class, $this->managerMonitoringService);
        $container->set(WorkerPhoneServiceInterface::class, $this->workerPhoneService);
    }

    protected function replaceAuthenticatedWorkerProvider(AuthenticatedWorkerProvider $provider): void
    {
        $this->authenticatedWorkerProvider = $provider;
        static::getContainer()->set(AuthenticatedWorkerProvider::class, $provider);
    }

    protected function createClientWithMocks(?AuthenticatedWorkerProvider $provider = null): KernelBrowser
    {
        $client = static::createClient();
        $this->registerMockServices();

        if (null !== $provider) {
            $this->replaceAuthenticatedWorkerProvider($provider);
        } else {
            $this->replaceAuthenticatedWorkerProvider($this->authenticatedWorkerProvider);
        }

        return $client;
    }
}
