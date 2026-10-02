<?php

declare(strict_types=1);

namespace Odden\Service\Tests;

use Odden\Core\CoreServiceProvider;
use Odden\Service\ServiceHubServiceProvider;
use Odden\Service\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

use function Orchestra\Testbench\after_resolving;
use function Orchestra\Testbench\default_migration_path;

abstract class TestCase extends Orchestra
{
    public const API_TOKEN = 'test-api-token';

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
        $app['config']->set('odden-service.api.token', self::API_TOKEN);
    }

    /**
     * Every request carries the API token, so server-to-server endpoints can be exercised
     * directly. Security tests call flushHeaders() to test missing or wrong tokens.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('X-Odden-Token', self::API_TOKEN);
    }

    /**
     * Laravel's own migrations (users, cache, jobs). Registered on the migrator rather than
     * run and rolled back per test: RefreshDatabase owns the schema, and rolling back
     * users fails on databases that enforce foreign keys (PostgreSQL, MySQL).
     */
    protected function defineDatabaseMigrations(): void
    {
        after_resolving($this->app, 'migrator', static function ($migrator): void {
            $migrator->path(default_migration_path());
        });
    }
}
