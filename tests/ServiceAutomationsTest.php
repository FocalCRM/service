<?php

declare(strict_types=1);

namespace Focal\Service\Tests;

use Focal\Service\Actions\RunServiceAutomationsAction;
use Focal\Service\Enums\TicketStatus;
use Focal\Service\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ServiceAutomationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_waiting_tickets_are_automatically_closed_after_7_days(): void
    {
        // Ticket 1: waiting on customer for 8 days
        $ticketStale = Ticket::create([
            'subject' => 'Please provide error screenshot',
            'status' => TicketStatus::WaitingOnCustomer,
        ]);
        $ticketStale->updated_at = now()->subDays(8);
        $ticketStale->saveQuietly();

        // Ticket 2: waiting on customer for 2 days
        $ticketActive = Ticket::create([
            'subject' => 'Recent inquiry',
            'status' => TicketStatus::WaitingOnCustomer,
        ]);
        $ticketActive->updated_at = now()->subDays(2);
        $ticketActive->saveQuietly();

        $results = (new RunServiceAutomationsAction)->execute();

        $this->assertSame(1, $results['inactivity_closed']);

        $ticketStale->refresh();
        $this->assertSame(TicketStatus::Closed, $ticketStale->status);
        $this->assertNotNull($ticketStale->closed_at);
        $this->assertStringContainsString('closed after 7 days', (string) $ticketStale->messages->last()?->body);

        $ticketActive->refresh();
        $this->assertSame(TicketStatus::WaitingOnCustomer, $ticketActive->status);
    }

    public function test_resolved_tickets_are_auto_archived_after_48_hours(): void
    {
        $ticketOldResolved = Ticket::create([
            'subject' => 'VPN configuration issue',
            'status' => TicketStatus::Resolved,
            'resolved_at' => now()->subHours(50),
        ]);

        $ticketFreshResolved = Ticket::create([
            'subject' => 'Printer setup',
            'status' => TicketStatus::Resolved,
            'resolved_at' => now()->subHours(10),
        ]);

        $results = (new RunServiceAutomationsAction)->execute();

        $this->assertSame(1, $results['resolved_closed']);

        $ticketOldResolved->refresh();
        $this->assertSame(TicketStatus::Closed, $ticketOldResolved->status);
        $this->assertNotNull($ticketOldResolved->closed_at);

        $ticketFreshResolved->refresh();
        $this->assertSame(TicketStatus::Resolved, $ticketFreshResolved->status);
    }
}
