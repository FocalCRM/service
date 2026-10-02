<?php

declare(strict_types=1);

namespace Focal\Service\Tests;

use Illuminate\Contracts\Http\Kernel;

/**
 * Laravel skips CSRF checks while running tests, so request-based tests cannot catch a
 * route whose CSRF exemption names the wrong middleware class. Check the route
 * definitions against the CSRF middleware the "web" group actually runs instead.
 */
class CsrfExemptRoutesTest extends TestCase
{
    public function test_cross_site_and_webhook_routes_exclude_the_web_groups_csrf_middleware(): void
    {
        $csrf = array_values(array_filter(
            app(Kernel::class)->getMiddlewareGroups()['web'] ?? [],
            fn (mixed $middleware): bool => is_string($middleware) && preg_match('/Csrf|Forgery/', $middleware) === 1,
        ));

        $this->assertNotEmpty($csrf, 'The web middleware group should contain CSRF protection.');

        foreach ([
            'focal.service.inbound-email',
            'focal.service.knowledge.deflect',
            'focal.service.chat.start',
            'focal.service.chat.message',
        ] as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route [{$name}] is not registered.");

            foreach ($csrf as $middleware) {
                $this->assertContains($middleware, $route->excludedMiddleware(), "Route [{$name}] still runs {$middleware}.");
            }
        }
    }
}
