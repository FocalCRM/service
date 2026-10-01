<?php

declare(strict_types=1);

namespace Focal\Service\Tests;

use Focal\Service\Actions\CreateTicketAction;
use Focal\Service\Actions\RouteTicketAction;
use Focal\Service\Enums\TicketPriority;
use Focal\Service\Enums\TicketSource;
use Focal\Service\Enums\TicketStatus;
use Focal\Service\Models\Ticket;
use Focal\Service\Models\TicketRoutingRule;
use Focal\Service\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TicketRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_is_routed_by_priority_and_keyword(): void
    {
        $agentA = User::factory()->create(['name' => 'Agent Alice']);
        $agentB = User::factory()->create(['name' => 'Agent Bob']);

        TicketRoutingRule::create([
            'name' => 'Billing Questions',
            'is_active' => true,
            'sort_order' => 1,
            'criteria' => ['keyword' => 'invoice'],
            'assigned_user_ids' => [$agentA->id],
        ]);

        TicketRoutingRule::create([
            'name' => 'Critical Incidents',
            'is_active' => true,
            'sort_order' => 2,
            'criteria' => ['priority' => 'urgent'],
            'assigned_user_ids' => [$agentB->id],
        ]);

        // Ticket 1: has 'invoice' in subject
        $ticket1 = Ticket::create([
            'subject' => 'Need help with overdue invoice',
            'priority' => TicketPriority::Low,
            'status' => TicketStatus::New,
        ]);

        $result1 = (new RouteTicketAction)->execute($ticket1);

        $this->assertNotNull($result1);
        $this->assertSame($agentA->id, $result1['assigned_user_id']);
        $ticket1->refresh();
        $this->assertSame($agentA->id, $ticket1->owner_id);
        $this->assertSame(TicketStatus::Open, $ticket1->status);

        // Ticket 2: is urgent
        $ticket2 = Ticket::create([
            'subject' => 'System outage alert',
            'priority' => TicketPriority::Urgent,
            'status' => TicketStatus::New,
        ]);

        $result2 = (new RouteTicketAction)->execute($ticket2);

        $this->assertNotNull($result2);
        $this->assertSame($agentB->id, $result2['assigned_user_id']);
        $ticket2->refresh();
        $this->assertSame($agentB->id, $ticket2->owner_id);
    }

    public function test_ticket_routing_distributes_round_robin(): void
    {
        $agent1 = User::factory()->create();
        $agent2 = User::factory()->create();
        $agent3 = User::factory()->create();

        TicketRoutingRule::create([
            'name' => 'General Round-Robin',
            'is_active' => true,
            'sort_order' => 10,
            'criteria' => [],
            'assigned_user_ids' => [$agent1->id, $agent2->id, $agent3->id],
        ]);

        $action = new RouteTicketAction;

        $t1 = Ticket::create(['subject' => 'Issue 1']);
        $res1 = $action->execute($t1);

        $t2 = Ticket::create(['subject' => 'Issue 2']);
        $res2 = $action->execute($t2);

        $t3 = Ticket::create(['subject' => 'Issue 3']);
        $res3 = $action->execute($t3);

        $t4 = Ticket::create(['subject' => 'Issue 4']);
        $res4 = $action->execute($t4);

        $this->assertSame($agent1->id, $res1['assigned_user_id']);
        $this->assertSame($agent2->id, $res2['assigned_user_id']);
        $this->assertSame($agent3->id, $res3['assigned_user_id']);
        $this->assertSame($agent1->id, $res4['assigned_user_id']); // wrapped around
    }

    public function test_create_ticket_action_automatically_triggers_routing(): void
    {
        $agent = User::factory()->create();

        TicketRoutingRule::create([
            'name' => 'Auto-Assign Email',
            'is_active' => true,
            'sort_order' => 1,
            'criteria' => ['source' => 'email'],
            'assigned_user_ids' => [$agent->id],
        ]);

        $ticket = (new CreateTicketAction)->execute(
            subject: 'Email inquiry',
            source: TicketSource::Email
        );

        $ticket->refresh();
        $this->assertSame($agent->id, $ticket->owner_id);
    }
}
