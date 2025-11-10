<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\BackendForFrontend\Worker\Phone;

use App\Modules\BackendForFrontend\Worker\Phone\WorkerPhoneController;
use Tests\Unit\Modules\BackendForFrontend\Shared\BackendForFrontendTestCase;

final class WorkerPhoneControllerTest extends BackendForFrontendTestCase
{
    // TODO: assert that start phone call endpoint requires worker authorization
    // TODO: assert that start phone call endpoint validates payload and delegates to WorkerPhoneService
    // TODO: assert that start phone call endpoint returns call session payload
    // TODO: assert that end phone call endpoint requires worker authorization and validates payload
    // TODO: assert that end phone call endpoint closes call session and returns summary statistics
}
