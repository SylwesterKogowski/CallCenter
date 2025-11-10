<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Manager;

use App\Modules\BackendForFrontend\Manager\ManagerMonitoringEventsController;
use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class ManagerMonitoringEventsControllerTest extends BackendForFrontendTestCase
{
    // TODO: assert that monitoring SSE endpoint forbids access for non-manager users
    // TODO: assert that monitoring SSE endpoint rejects mismatched managerId with 403 response
    // TODO: assert that monitoring SSE endpoint validates date query parameter
    // TODO: assert that monitoring SSE endpoint streams events with required headers on success
    // TODO: assert that JSON encoding errors fallback gracefully when debug mode disabled
}
