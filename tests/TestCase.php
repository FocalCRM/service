<?php

declare(strict_types=1);

namespace Focal\Service\Tests;

use Focal\Core\CoreServiceProvider;
use Focal\Service\ServiceHubServiceProvider;
use Focal\Service\Tests\Fixtures\User;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use WithLaravelMigrations;

    /**
     * Boots service with only its required dependency (core).
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
            ServiceHubServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('auth.providers.users.model', User::class);
    }
}
