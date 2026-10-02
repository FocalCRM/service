<?php

declare(strict_types=1);

namespace Odden\Service\Tests;

use Odden\Core\Enums\ActivityType;
use Odden\Core\Models\Contact;
use Odden\Service\Enums\TicketSource;
use Odden\Service\Enums\TicketStatus;
use Odden\Service\Models\Ticket;
use Odden\Service\Models\TicketRoutingRule;
use Odden\Service\Notifications\TicketCreatedNotification;
use Odden\Service\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/**
 * Issue #15: chat-started tickets go through CreateTicketAction like every other channel.
 */
class ChatTicketCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_ticket_is_routed_by_routing_rules(): void
    {
        $agent = User::factory()->create();

        TicketRoutingRule::create([
            'name' => 'Chat desk',
            'is_active' => true,
            'sort_order' => 1,
            'criteria' => ['source' => 'chat'],
            'assigned_user_ids' => [$agent->id],
        ]);

        $ticket = $this->startChat();

        $this->assertSame(TicketSource::Chat, $ticket->source);
        $this->assertSame($agent->id, $ticket->owner_id);
        $this->assertSame(TicketStatus::Open, $ticket->status);
    }

    public function test_chat_ticket_logs_a_task_on_the_contact_timeline(): void
    {
        $ticket = $this->startChat();

        $contact = $ticket->contact;
        $this->assertNotNull($contact);

        $task = $contact->activities()->where('type', ActivityType::Task)->first();
        $this->assertNotNull($task);
        $this->assertStringContainsString("Support Ticket #{$ticket->ticket_number}", (string) $task->title);
    }

    public function test_chat_ticket_does_not_send_the_confirmation_email_by_default(): void
    {
        // The chat endpoint is public and never verifies the email address, so by default it
        // must not email whatever address a visitor types in.
        Notification::fake();

        $ticket = $this->startChat();

        $this->assertNotNull($ticket->contact);
        Notification::assertNotSentTo($ticket->contact, TicketCreatedNotification::class);
    }

    public function test_chat_confirmation_email_can_be_turned_on(): void
    {
        Notification::fake();
        config(['odden-service.chat.confirmation_email' => true]);

        $ticket = $this->startChat();

        $this->assertNotNull($ticket->contact);
        Notification::assertSentTo(
            $ticket->contact,
            TicketCreatedNotification::class,
            fn (TicketCreatedNotification $notification): bool => $notification->ticket->id === $ticket->id,
        );
    }

    public function test_chat_thread_has_one_customer_message_then_the_greeting(): void
    {
        $ticket = $this->startChat('Where is my order?');

        $bodies = $ticket->messages()->pluck('body')->all();

        $this->assertCount(2, $bodies);
        $this->assertSame('Where is my order?', $bodies[0]);
        $this->assertStringContainsString('Thanks for reaching out', $bodies[1]);
    }

    public function test_chat_reuses_a_contact_regardless_of_email_case_or_whitespace(): void
    {
        $existing = Contact::factory()->create(['email' => 'Sarah.Connor@Cyberdyne.test']);

        $ticket = $this->startChat(email: '  SARAH.connor@cyberdyne.TEST ');

        $this->assertSame($existing->id, $ticket->contact_id);
        $this->assertSame(1, Contact::query()->count());
    }

    private function startChat(string $message = 'Hello, I need help.', string $email = 'sarah@cyberdyne.test'): Ticket
    {
        $response = $this->postJson(route('odden.service.chat.start'), [
            'name' => 'Sarah Connor',
            'email' => $email,
            'message' => $message,
        ]);

        $response->assertCreated();

        return Ticket::query()->where('portal_token', (string) $response->json('token'))->sole();
    }
}
