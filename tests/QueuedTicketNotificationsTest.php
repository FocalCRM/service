<?php

declare(strict_types=1);

namespace Focal\Service\Tests;

use Focal\Core\Models\Contact;
use Focal\Service\Actions\CreateTicketAction;
use Focal\Service\Actions\ReplyTicketAction;
use Focal\Service\Enums\MessageSenderType;
use Focal\Service\Models\Ticket;
use Focal\Service\Notifications\SlaBreachAlertNotification;
use Focal\Service\Notifications\TicketCreatedNotification;
use Focal\Service\Notifications\TicketRepliedNotification;
use Focal\Service\Notifications\TicketResolvedCsatNotification;
use Focal\Service\Tests\Fixtures\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mime\Email;

/**
 * Issue #17 (service): ticket notifications are queued, on a configurable queue, and keep the
 * ticket Message-ID that inbound email threading relies on.
 */
class QueuedTicketNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_and_owner_notifications_are_queued(): void
    {
        $ticket = Ticket::create(['subject' => 'Queue me']);
        $message = $ticket->addMessage('Hi');

        foreach ([
            new TicketCreatedNotification($ticket),
            new TicketRepliedNotification($ticket, $message),
            new TicketResolvedCsatNotification($ticket),
            new SlaBreachAlertNotification($ticket, 'first_response'),
        ] as $notification) {
            $this->assertInstanceOf(ShouldQueue::class, $notification, $notification::class);
        }
    }

    public function test_queue_and_connection_come_from_config(): void
    {
        config([
            'focal-service.notifications.queue' => 'support-mail',
            'focal-service.notifications.connection' => 'database',
        ]);

        $notification = new TicketCreatedNotification(Ticket::create(['subject' => 'Queue me']));

        $this->assertSame('support-mail', $notification->queue);
        $this->assertSame('database', $notification->connection);
        $this->assertTrue($notification->afterCommit);
    }

    public function test_queue_defaults_to_the_connection_default(): void
    {
        $notification = new TicketCreatedNotification(Ticket::create(['subject' => 'Queue me']));

        $this->assertNull($notification->queue);
        $this->assertNull($notification->connection);
    }

    public function test_creating_a_ticket_pushes_the_confirmation_to_the_configured_queue(): void
    {
        config(['focal-service.notifications.queue' => 'support-mail']);
        Queue::fake();

        $contact = Contact::factory()->create(['email' => 'dana@client.test']);
        (new CreateTicketAction)->execute(subject: 'Cannot log in', contact: $contact);

        Queue::assertPushedOn('support-mail', SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof TicketCreatedNotification);
        $this->assertSame([], $this->sentEmails());
    }

    public function test_queued_reply_email_keeps_the_ticket_message_id(): void
    {
        config(['app.url' => 'https://crm.example.com']);
        Queue::fake();

        $contact = Contact::factory()->create(['email' => 'dana@client.test']);
        $ticket = Ticket::create(['subject' => 'Cannot log in', 'contact_id' => $contact->id]);
        (new ReplyTicketAction)->execute($ticket, 'We are on it.', MessageSenderType::Agent, User::factory()->create());

        $jobs = Queue::pushed(SendQueuedNotifications::class);
        $this->assertCount(1, $jobs);

        // Round-trip through serialization the way a worker would, then run it.
        /** @var SendQueuedNotifications $job */
        $job = unserialize(serialize($jobs->first()));
        $job->handle(app(ChannelManager::class));

        $emails = $this->sentEmails();
        $this->assertCount(1, $emails);
        $messageId = $emails[0]->getHeaders()->get('Message-ID')?->getBodyAsString();
        $this->assertMatchesRegularExpression('/^<ticket\.'.$ticket->portal_token.'\.[a-f0-9]{16}@crm\.example\.com>$/', (string) $messageId);
    }

    public function test_notification_fake_still_sees_queued_notifications(): void
    {
        Notification::fake();

        $contact = Contact::factory()->create(['email' => 'dana@client.test']);
        $ticket = (new CreateTicketAction)->execute(subject: 'Cannot log in', contact: $contact);

        Notification::assertSentTo($contact, TicketCreatedNotification::class, fn (TicketCreatedNotification $n): bool => $n->ticket->is($ticket));
    }

    /**
     * @return list<Email>
     */
    private function sentEmails(): array
    {
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        /** @var list<Email> $emails */
        $emails = $transport->messages()->map(fn ($sent) => $sent->getOriginalMessage())->values()->all();

        return $emails;
    }
}
