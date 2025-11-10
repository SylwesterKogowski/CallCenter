<?php

declare(strict_types=1);

namespace App\Modules\BackendForFrontend\Shared\Security\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class RequiresWorker
{
}
