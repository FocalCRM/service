<?php

declare(strict_types=1);

namespace Focal\Service\Tests;

use App\Models\User;
use Focal\Core\Models\Contact;
use Focal\Service\Actions\CheckSlaBreachesAction;
use Focal\Service\Actions\CreateTicketAction;
use Focal\Service\Actions\ReplyTicketAction;
use Focal\Service\Actions\ResolveTicketAction;
use Focal\Service\Enums\MessageSenderType;
use Focal\Service\Enums\TicketPriority;
use Focal\Service\Enums\TicketStatus;
use Focal\Service\Models\Ticket;
use Focal\Service\Notifications\SlaBreachAlertNotification;
use Focal\Service\Notifications\TicketCreatedNotification;
use Focal\Service\Notifications\TicketRepliedNotification;
use Focal\Service\Notifications\TicketResolvedCsatNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_receives_confirmation_notification_when_ticket_is_created(): void
    {
        Notification::fake();

        $contact = Contact::factory()->create([
            'first_name' => 'Gordon',
            'last_name' => 'Freeman',
            'email' => 'gordon@blackmesa.gov',
        ]);

        $ticket = (new CreateTicketAction)->execute(
            subject: 'HEV suit power failure',
            description: 'The auxiliary power system is depleted.',
            priority: TicketPriority::High,
            contact: $contact
        );

        Notification::assertSentTo(
            $contact,
            TicketCreatedNotification::class,
            function (TicketCreatedNotification $notification) use ($ticket) {
                return $notification->ticket->id === $ticket->id;
            }
        );
    }

    public function test_customer_receives_notification_when_agent_posts_public_reply(): void
    {
        Notification::fake();

        $contact = Contact::factory()->create([
            'first_name' => 'Alyx',
            'last_name' => 'Vance',
            'email' => 'alyx@city17.resistance',
        ]);

        $agent = User::factory()->create(['name' => 'Barney Calhoun']);

        $ticket = Ticket::create([
            'subject' => 'EMP tool recharge required',
            'contact_id' => $contact->id,
            'owner_id' => $agent->id,
        ]);

        (new ReplyTicketAction)->execute(
            ticket: $ticket,
            body: 'We dispatched a replacement battery pack.',
            senderType: MessageSenderType::Agent,
            user: $agent,
            isInternalNote: false
        );

        Notification::assertSentTo(
            $contact,
            TicketRepliedNotification::class,
            function (TicketRepliedNotification $notification) use ($ticket) {
                return $notification->ticket->id === $ticket->id
                    && str_contains($notification->message->body, 'replacement battery pack');
            }
        );
    }

    public function test_customer_does_not_receive_notification_on_internal_note(): void
    {
        Notification::fake();

        $contact = Contact::factory()->create([
            'first_name' => 'Eli',
            'last_name' => 'Vance',
            'email' => 'eli@blackmesa.gov',
        ]);

        $agent = User::factory()->create();

        $ticket = Ticket::create([
            'subject' => 'Teleport gate alignment',
            'contact_id' => $contact->id,
            'owner_id' => $agent->id,
        ]);

        (new ReplyTicketAction)->execute(
            ticket: $ticket,
            body: 'Internal note: Xen crystals are fluctuating.',
            senderType: MessageSenderType::Agent,
            user: $agent,
            isInternalNote: true
        );

        Notification::assertNothingSent();
    }

    public function test_customer_receives_csat_survey_notification_when_ticket_is_resolved(): void
    {
        Notification::fake();

        $contact = Contact::factory()->create([
            'first_name' => 'Wallace',
            'last_name' => 'Breen',
            'email' => 'breen@citadel.gov',
        ]);

        $ticket = Ticket::create([
            'subject' => 'Broadcast frequency issue',
            'contact_id' => $contact->id,
            'status' => TicketStatus::Open,
        ]);

        (new ResolveTicketAction)->execute(
            ticket: $ticket,
            resolutionNote: 'Re-aligned communications array.'
        );

        Notification::assertSentTo(
            $contact,
            TicketResolvedCsatNotification::class,
            function (TicketResolvedCsatNotification $notification) use ($ticket) {
                return $notification->ticket->id === $ticket->id
                    && $notification->resolutionNote === 'Re-aligned communications array.';
            }
        );
    }

    public function test_agent_receives_sla_breach_alert_notification(): void
    {
        Notification::fake();

        $agent = User::factory()->create();

        $ticket = Ticket::create([
            'subject' => 'Reactor containment breach',
            'status' => TicketStatus::Open,
            'owner_id' => $agent->id,
            'first_response_due_at' => now()->subHour(),
            'is_sla_response_breached' => false,
        ]);

        (new CheckSlaBreachesAction)->execute();

        Notification::assertSentTo(
            $agent,
            SlaBreachAlertNotification::class,
            function (SlaBreachAlertNotification $notification) use ($ticket) {
                return $notification->ticket->id === $ticket->id
                    && $notification->breachType === 'first_response';
            }
        );
    }
}
