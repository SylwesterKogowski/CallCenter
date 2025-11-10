<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Manager;

use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class ManagerMonitoringControllerTest extends BackendForFrontendTestCase
{
    // TODO: assert that monitoring endpoint forbids access for non-manager users (403)
    // TODO: assert that monitoring endpoint validates date query parameter
    // TODO: assert that monitoring endpoint returns normalized monitoring payload on success
    // TODO: assert that update auto-assignment endpoint validates enabled flag and settings
    // TODO: assert that update auto-assignment endpoint forbids access without manager role
    // TODO: assert that update auto-assignment endpoint returns updated settings with timestamp
    // TODO: assert that trigger auto-assignment endpoint validates date payload and returns accepted response
    // TODO: assert that trigger auto-assignment endpoint forbids access without manager role
}
