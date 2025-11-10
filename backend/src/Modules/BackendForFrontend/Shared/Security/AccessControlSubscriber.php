<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Shared\Security;

use App\Modules\BackendForFrontend\Shared\Exception\AccessDeniedException;
use App\Modules\BackendForFrontend\Shared\Security\Attribute\RequiresManager;
use App\Modules\BackendForFrontend\Shared\Security\Attribute\RequiresWorker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class AccessControlSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuthenticatedWorkerProvider $workerProvider,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['enforceAccessControl', 100],
        ];
    }

    public function enforceAccessControl(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (!is_array($controller)) {
            return;
        }

        [$instance, $method] = $controller;
        $reflectionClass = new \ReflectionClass($instance);

        if (!$reflectionClass->hasMethod($method)) {
            return;
        }

        $reflectionMethod = $reflectionClass->getMethod($method);

        $requiresManager = $this->hasAttribute($reflectionClass, RequiresManager::class)
            || $this->hasAttribute($reflectionMethod, RequiresManager::class);
        $requiresWorker = $requiresManager
            || $this->hasAttribute($reflectionClass, RequiresWorker::class)
            || $this->hasAttribute($reflectionMethod, RequiresWorker::class);

        if (!$requiresWorker) {
            return;
        }

        $worker = $this->workerProvider->getAuthenticatedWorker();
        $this->storeWorkerOnRequest($event->getRequest(), $worker);

        if ($requiresManager && !$worker->isManager()) {
            throw new AccessDeniedException();
        }
    }

    /**
     * @param \ReflectionClass<object>|\ReflectionMethod $reflector
     */
    private function hasAttribute(\ReflectionClass|\ReflectionMethod $reflector, string $attributeClass): bool
    {
        foreach ($reflector->getAttributes($attributeClass) as $attribute) {
            if ($attribute->getName() === $attributeClass) {
                return true;
            }
        }

        return false;
    }

    private function storeWorkerOnRequest(Request $request, AuthenticatedWorker $worker): void
    {
        if ($request->attributes->has(AuthenticatedWorkerProvider::REQUEST_ATTRIBUTE)) {
            return;
        }

        $request->attributes->set(AuthenticatedWorkerProvider::REQUEST_ATTRIBUTE, $worker);
    }
}
