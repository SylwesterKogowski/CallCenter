<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Worker\Schedule;

use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class WorkerScheduleControllerTest extends BackendForFrontendTestCase
{
    // TODO: assert that get schedule endpoint requires worker authorization
    // TODO: assert that get schedule endpoint returns active ticket and recent assignments
    // TODO: assert that work status endpoint aggregates schedule statistics and ticket metrics
    // TODO: assert that update ticket status endpoint validates status transitions and worker permissions
    // TODO: assert that add ticket time endpoint validates positive durations and updates totals
    // TODO: assert that add ticket notes endpoint validates message length and persists note
    // TODO: assert that controller maps missing ticket to 404 response
}
