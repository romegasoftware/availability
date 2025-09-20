<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use RomegaSoftware\Availability\Providers\AvailabilityServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            AvailabilityServiceProvider::class,
        ];
    }
}
