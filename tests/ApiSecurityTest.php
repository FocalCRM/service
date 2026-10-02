<?php

declare(strict_types=1);

namespace Odden\Service\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Odden\Service\Models\SlaPolicy;
use Odden\Service\Models\Ticket;

class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SlaPolicy::create(SlaPolicy::defaultPreset());
    }

    public function test_inbound_email_webhook_rejects_requests_without_the_token_and_creates_no_ticket(): void
    {
        $payload = ['from' => 'Mallory <mallory@example.com>', 'subject' => 'Fake ticket', 'body' => 'Spam'];

        $this->flushHeaders()->postJson('/api/service/inbound-email', $payload)->assertUnauthorized();
        $this->withToken('wrong-token')->postJson('/api/service/inbound-email', $payload)->assertUnauthorized();

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_inbound_email_webhook_is_disabled_until_a_token_is_configured(): void
    {
        config(['odden-service.api.token' => null]);

        $this->postJson('/api/service/inbound-email', ['from' => 'a@example.com', 'subject' => 'x', 'body' => 'y'])->assertForbidden();
    }

    public function test_chat_widget_is_rate_limited(): void
    {
        config(['odden-core.rate_limits.public' => 1]);
        $this->flushHeaders();

        $first = $this->postJson(route('odden.service.chat.start'), ['email' => 'visitor@example.com', 'message' => 'Hi']);

        $this->assertNotSame(429, $first->status());
        $this->postJson(route('odden.service.chat.start'), ['email' => 'visitor@example.com', 'message' => 'Hi'])->assertTooManyRequests();
    }
}
