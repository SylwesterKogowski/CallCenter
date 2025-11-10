<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Worker\Planning;

use App\Modules\BackendForFrontend\Worker\Planning\WorkerPlanningController;
use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class WorkerPlanningControllerTest extends BackendForFrontendTestCase
{
    // TODO: assert that backlog endpoint filters tickets by worker categories
    // TODO: assert that backlog endpoint requires worker authorization
    // TODO: assert that weekly schedule endpoint merges availability and assigned tickets
    // TODO: assert that predictions endpoint returns calculated workload forecast
    // TODO: assert that manual assign endpoint validates payload and calls WorkerScheduleService
    // TODO: assert that manual unassign endpoint validates payload and removes ticket
    // TODO: assert that auto-assign endpoint triggers WorkerScheduleService for authenticated worker
    // TODO: assert that all endpoints map domain errors to JSON problem responses
}
