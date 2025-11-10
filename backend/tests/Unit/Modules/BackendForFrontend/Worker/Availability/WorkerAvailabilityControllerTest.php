<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Worker\Availability;

use App\Modules\BackendForFrontend\Worker\Availability\WorkerAvailabilityController;
use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class WorkerAvailabilityControllerTest extends BackendForFrontendTestCase
{
    // TODO: assert that list availability endpoint requires authenticated worker (401/403)
    // TODO: assert that list availability endpoint returns normalized week availability structure
    // TODO: assert that save availability endpoint validates date and time slot payloads
    // TODO: assert that save availability endpoint persists availability through service and returns updated slots
    // TODO: assert that update time slot endpoint validates payload and permissions
    // TODO: assert that delete time slot endpoint removes slot and returns confirmation
    // TODO: assert that copy availability endpoint respects overwrite flag and returns copied/skipped lists
}
