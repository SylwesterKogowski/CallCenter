<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Shared\Support;

/**
 * Helper trait for placeholder domain facades that are not implemented yet.
 * All methods throw a LogicException to signal missing integration.
 */
trait NotImplementedDomainServiceTrait
{
    final protected function notImplemented(string $method): never
    {
        throw new \LogicException(sprintf('Domain service method "%s" is not implemented yet.', $method));
    }
}


