<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Manager;

use App\Modules\BackendForFrontend\Manager\Dto\MonitoringQuery;
use App\Modules\BackendForFrontend\Manager\Service\ManagerMonitoringServiceInterface;
use App\Modules\BackendForFrontend\Shared\AbstractJsonController;
use App\Modules\BackendForFrontend\Shared\Exception\AccessDeniedException;
use App\Modules\BackendForFrontend\Shared\Exception\HttpAwareExceptionInterface;
use App\Modules\BackendForFrontend\Shared\Security\AuthenticatedWorkerProvider;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(name: 'backend_for_frontend_manager_events_')]
final class ManagerMonitoringEventsController extends AbstractJsonController
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

    #[Route(
        path: '/events/manager/monitoring/{managerId}',
        name: 'monitoring',
        methods: [Request::METHOD_GET],
    )]
    public function monitoringEvents(Request $request, string $managerId): Response
    {
        try {
            $manager = $this->requireManager();

            if ($manager->getId() !== $managerId) {
                throw new AccessDeniedException('Brak uprawnień do dostępu do strumienia monitoringu');
            }

            $query = new MonitoringQuery(
                date: (string) $request->query->get('date', ''),
            );
            $this->validateDto($query);
            $date = $this->parseDate($query->date, 'date');

            $response = new StreamedResponse(function () use ($manager, $date) {
                echo ": stream-start\n\n";
                $this->flushOutputBuffers();

                $this->monitoringService->streamMonitoringEvents(
                    $manager->getId(),
                    $date,
                    function (string $eventType, array $payload, DateTimeImmutable $timestamp): void {
                        $payload['timestamp'] = $timestamp->format(DATE_ATOM);

                        try {
                            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
                        } catch (\JsonException $exception) {
                            if ($this->isDebug()) {
                                throw $exception;
                            }

                            return;
                        }

                        echo sprintf("event: %s\n", $eventType);
                        echo sprintf("data: %s\n\n", $encoded);
                        $this->flushOutputBuffers();
                    },
                );
            });

            $response->headers->set('Content-Type', 'text/event-stream');
            $response->headers->set('Cache-Control', 'no-cache');
            $response->headers->set('Connection', 'keep-alive');
            $response->headers->set('X-Accel-Buffering', 'no');

            return $response;
        } catch (HttpAwareExceptionInterface $exception) {
            return $this->jsonError(
                $exception->getPublicMessage(),
                $exception->getStatusCode(),
                $exception->getContext(),
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            if ($this->isDebug()) {
                throw $exception;
            }

            return $this->jsonError('Wewnętrzny błąd serwera', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}


