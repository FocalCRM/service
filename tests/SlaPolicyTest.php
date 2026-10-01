<?php

declare(strict_types=1);

namespace Focal\Service\Tests;

use Focal\Service\Actions\CheckSlaBreachesAction;
use Focal\Service\Enums\TicketPriority;
use Focal\Service\Enums\TicketStatus;
use Focal\Service\Models\SlaPolicy;
use Focal\Service\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlaPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sla_policy_calculates_correct_durations_per_priority(): void
    {
        $policy = SlaPolicy::create(SlaPolicy::defaultPreset());

        $this->assertSame(60, $policy->getFirstResponseMinutesFor(TicketPriority::Urgent));
        $this->assertSame(240, $policy->getResolutionMinutesFor(TicketPriority::Urgent));

        $this->assertSame(120, $policy->getFirstResponseMinutesFor(TicketPriority::High));
        $this->assertSame(480, $policy->getResolutionMinutesFor(TicketPriority::High));

        $this->assertSame(240, $policy->getFirstResponseMinutesFor(TicketPriority::Medium));
        $this->assertSame(1440, $policy->getResolutionMinutesFor(TicketPriority::Medium));

        $this->assertSame(480, $policy->getFirstResponseMinutesFor(TicketPriority::Low));
        $this->assertSame(2880, $policy->getResolutionMinutesFor(TicketPriority::Low));
    }

    public function test_sla_breach_detection_flags_overdue_tickets(): void
    {
        $policy = SlaPolicy::create(SlaPolicy::defaultPreset());

        // Overdue first response ticket (due 2 hours ago, unresponded)
        $breachedResponseTicket = Ticket::create([
            'subject' => 'Urgent database timeout',
            'status' => TicketStatus::New,
            'priority' => TicketPriority::Urgent,
            'first_response_due_at' => now()->subHours(2),
            'resolution_due_at' => now()->addHours(2),
            'sla_policy_id' => $policy->id,
        ]);

        // Overdue resolution ticket (due 1 hour ago, responded but unresolved)
        $breachedResolutionTicket = Ticket::create([
            'subject' => 'High latency during peak hours',
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::High,
            'first_response_due_at' => now()->subHours(5),
            'first_responded_at' => now()->subHours(4),
            'resolution_due_at' => now()->subHour(),
            'sla_policy_id' => $policy->id,
        ]);

        // Normal ticket (deadlines in future)
        $normalTicket = Ticket::create([
            'subject' => 'UI button styling feedback',
            'status' => TicketStatus::New,
            'priority' => TicketPriority::Low,
            'first_response_due_at' => now()->addHours(4),
            'resolution_due_at' => now()->addHours(24),
            'sla_policy_id' => $policy->id,
        ]);

        $checker = new CheckSlaBreachesAction;
        $results = $checker->execute();

        $this->assertSame(1, $results['response_breaches']);
        $this->assertSame(1, $results['resolution_breaches']);

        $breachedResponseTicket->refresh();
        $breachedResolutionTicket->refresh();
        $normalTicket->refresh();

        $this->assertTrue($breachedResponseTicket->is_sla_response_breached);
        $this->assertTrue($breachedResolutionTicket->is_sla_resolution_breached);
        $this->assertFalse($normalTicket->is_sla_response_breached);
        $this->assertFalse($normalTicket->is_sla_resolution_breached);
    }
}
