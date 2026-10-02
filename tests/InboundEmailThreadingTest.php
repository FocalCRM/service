<?php

declare(strict_types=1);

namespace Focal\Service\Tests;

use Focal\Core\Models\Contact;
use Focal\Service\Actions\CreateTicketAction;
use Focal\Service\Actions\ReplyTicketAction;
use Focal\Service\Actions\ResolveTicketAction;
use Focal\Service\Enums\MessageSenderType;
use Focal\Service\Enums\TicketStatus;
use Focal\Service\Models\SlaPolicy;
use Focal\Service\Models\Ticket;
use Focal\Service\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;

class InboundEmailThreadingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SlaPolicy::create(SlaPolicy::defaultPreset());
        config(['app.url' => 'https://crm.example.com']);
    }

    public function test_ticket_number_alone_with_matching_sender_no_longer_threads(): void
    {
        [$contact, $ticket] = $this->ticketFor('alex@client.test', ['ticket_number' => 'TICK-2026-ABCD']);

        $response = $this->postJson('/api/service/inbound-email', [
            'from' => 'Alex Smith <alex@client.test>',
            'subject' => 'Re: [#TICK-2026-ABCD] SAML metadata expired',
            'body' => 'Following up on TICK-2026-ABCD.',
            'In-Reply-To' => '<TICK-2026-ABCD@mail.focal.test>',
            'References' => '<TICK-2026-ABCD@mail.focal.test>',
        ]);

        $response->assertStatus(201)->assertJsonPath('status', 'created');
        $this->assertNotSame('TICK-2026-ABCD', $response->json('ticket_number'));
        $this->assertCount(0, $ticket->messages()->get());

        $newTicket = Ticket::query()->where('ticket_number', $response->json('ticket_number'))->sole();
        $this->assertSame($contact->id, $newTicket->contact_id);
    }

    public function test_in_reply_to_with_the_notification_message_id_threads(): void
    {
        $contact = Contact::factory()->create(['email' => 'dana@client.test']);
        $ticket = app(CreateTicketAction::class)->execute(subject: 'Cannot log in', description: 'Locked out.', contact: $contact);

        $messageId = $this->lastSentMessageId();

        $response = $this->postJson('/api/service/inbound-email', [
            'from' => 'Dana <dana@client.test>',
            'subject' => 'Re: Cannot log in',
            'body' => 'Still locked out.',
            'in_reply_to' => $messageId,
        ]);

        $response->assertOk()->assertJson(['status' => 'appended', 'ticket_number' => $ticket->ticket_number]);
        $this->assertSame(1, $ticket->messages()->where('body', 'Still locked out.')->count());
    }

    public function test_references_header_threads(): void
    {
        [, $ticket] = $this->ticketFor('dana@client.test');
        $messageId = $this->messageIdFor($ticket);

        $response = $this->postJson('/api/service/inbound-email', [
            'from' => 'dana@client.test',
            'subject' => 'Re: Cannot log in',
            'body' => 'Any news?',
            'References' => "<unrelated.1@mail.client.test>\r\n {$messageId} <other.2@mail.client.test>",
        ]);

        $response->assertOk()->assertJson(['status' => 'appended', 'ticket_number' => $ticket->ticket_number]);
    }

    public function test_headers_field_threads_as_raw_block_map_or_name_value_list(): void
    {
        [, $ticket] = $this->ticketFor('dana@client.test');
        $messageId = $this->messageIdFor($ticket);

        $shapes = [
            "Received: from mx.client.test\r\nIn-Reply-To: {$messageId}\r\nSubject: Re: hi\r\n",
            ['In-Reply-To' => $messageId],
            ['References' => ['<a.1@x.test>', $messageId]],
            [['Name' => 'References', 'Value' => $messageId]],
            [['references', "<a.1@x.test> {$messageId}"]],
        ];

        foreach ($shapes as $index => $headers) {
            $this->postJson('/api/service/inbound-email', [
                'from' => 'dana@client.test',
                'subject' => 'Re: hi',
                'body' => "Reply {$index}",
                'headers' => $headers,
            ])->assertOk()->assertJson(['status' => 'appended', 'ticket_number' => $ticket->ticket_number]);
        }

        $this->assertSame(count($shapes), $ticket->messages()->count());
    }

    public function test_message_id_in_an_unrelated_header_does_not_thread(): void
    {
        [, $ticket] = $this->ticketFor('dana@client.test');
        $messageId = $this->messageIdFor($ticket);

        $this->postJson('/api/service/inbound-email', [
            'from' => 'dana@client.test',
            'subject' => 'New question',
            'body' => 'Unrelated.',
            'headers' => ['X-Custom' => $messageId],
        ])->assertStatus(201)->assertJsonPath('status', 'created');

        $this->assertCount(0, $ticket->messages()->get());
    }

    public function test_portal_link_in_body_or_subject_threads(): void
    {
        [, $ticket] = $this->ticketFor('portal@client.test');

        $this->postJson('/api/service/inbound-email', [
            'from' => 'portal@client.test',
            'subject' => 'Re: your request',
            'body' => "Thanks.\n\n> View your ticket: {$ticket->getPortalUrl()}",
        ])->assertOk()->assertJson(['status' => 'appended', 'ticket_number' => $ticket->ticket_number]);

        $this->postJson('/api/service/inbound-email', [
            'from' => 'portal@client.test',
            'subject' => "Re: {$ticket->getPortalUrl()}",
            'body' => 'Second reply.',
        ])->assertOk()->assertJson(['status' => 'appended', 'ticket_number' => $ticket->ticket_number]);

        $this->assertSame(2, $ticket->messages()->count());
    }

    public function test_correct_token_from_the_wrong_sender_creates_a_new_ticket(): void
    {
        [, $ticket] = $this->ticketFor('owner@client.test');

        $response = $this->postJson('/api/service/inbound-email', [
            'from' => 'Colleague <colleague@client.test>',
            'subject' => 'Re: Cannot log in',
            'body' => 'Chiming in from CC.',
            'In-Reply-To' => $this->messageIdFor($ticket),
        ]);

        $response->assertStatus(201)->assertJsonPath('status', 'created');
        $this->assertCount(0, $ticket->messages()->get());
        $this->assertSame('colleague@client.test', Ticket::query()->where('ticket_number', $response->json('ticket_number'))->sole()->contact?->email);
    }

    public function test_token_for_one_ticket_does_not_thread_onto_another(): void
    {
        [, $ticketA] = $this->ticketFor('alice@client.test');
        [, $ticketB] = $this->ticketFor('bob@client.test');

        // Bob quotes his own ticket number but carries Alice's token.
        $response = $this->postJson('/api/service/inbound-email', [
            'from' => 'bob@client.test',
            'subject' => "Re: [#{$ticketB->ticket_number}] Cannot log in",
            'body' => "See {$ticketA->getPortalUrl()}",
            'In-Reply-To' => $this->messageIdFor($ticketA),
        ]);

        $response->assertStatus(201)->assertJsonPath('status', 'created');
        $this->assertCount(0, $ticketA->messages()->get());
        $this->assertCount(0, $ticketB->messages()->get());
    }

    public function test_customer_notifications_carry_a_ticket_message_id(): void
    {
        $contact = Contact::factory()->create(['email' => 'gordon@client.test']);
        $agent = User::factory()->create();

        $ticket = app(CreateTicketAction::class)->execute(subject: 'HEV suit', description: 'No power.', contact: $contact);
        app(ReplyTicketAction::class)->execute(ticket: $ticket, body: 'Battery shipped.', senderType: MessageSenderType::Agent, user: $agent);
        app(ResolveTicketAction::class)->execute(ticket: $ticket->fresh() ?? $ticket);

        $sent = $this->sentEmails();
        $this->assertCount(3, $sent);

        $ids = [];
        foreach ($sent as $email) {
            $header = $email->getHeaders()->get('Message-ID');
            $this->assertNotNull($header);
            $ids[] = $header->getBodyAsString();
            $this->assertMatchesRegularExpression(
                '/\A<ticket\.'.preg_quote($ticket->portal_token, '/').'\.[a-z0-9]{16}@crm\.example\.com>\z/',
                $header->getBodyAsString()
            );
        }

        $this->assertCount(3, array_unique($ids));
    }

    public function test_require_authenticated_sender_blocks_threading_without_a_passing_verdict(): void
    {
        config(['focal-service.inbound_email.require_authenticated_sender' => true]);

        [, $ticket] = $this->ticketFor('dana@client.test');
        $payload = [
            'from' => 'dana@client.test',
            'subject' => 'Re: Cannot log in',
            'body' => 'Reply',
            'In-Reply-To' => $this->messageIdFor($ticket),
        ];

        $this->postJson('/api/service/inbound-email', $payload)->assertStatus(201)->assertJsonPath('status', 'created');
        $this->postJson('/api/service/inbound-email', [...$payload, 'sender_authenticated' => false])->assertStatus(201);
        $this->postJson('/api/service/inbound-email', [...$payload, 'dmarc' => 'fail'])->assertStatus(201);
        $this->assertCount(0, $ticket->messages()->get());

        $this->postJson('/api/service/inbound-email', [...$payload, 'sender_authenticated' => true])
            ->assertOk()->assertJsonPath('status', 'appended');
        $this->post('/api/service/inbound-email', [...$payload, 'sender_authenticated' => 'true'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('status', 'appended');
        $this->postJson('/api/service/inbound-email', [...$payload, 'dmarc' => 'Pass'])
            ->assertOk()->assertJsonPath('status', 'appended');

        $this->assertSame(3, $ticket->messages()->count());
    }

    public function test_authentication_verdict_is_ignored_when_not_required(): void
    {
        [, $ticket] = $this->ticketFor('dana@client.test');

        $this->postJson('/api/service/inbound-email', [
            'from' => 'dana@client.test',
            'subject' => 'Re: Cannot log in',
            'body' => 'Reply',
            'In-Reply-To' => $this->messageIdFor($ticket),
            'sender_authenticated' => false,
        ])->assertOk()->assertJsonPath('status', 'appended');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: Contact, 1: Ticket}
     */
    private function ticketFor(string $email, array $attributes = []): array
    {
        $contact = Contact::factory()->create(['email' => $email]);

        $ticket = Ticket::create([
            'subject' => 'Cannot log in',
            'status' => TicketStatus::WaitingOnCustomer,
            'contact_id' => $contact->id,
            ...$attributes,
        ]);

        return [$contact, $ticket];
    }

    /**
     * The Message-ID a ticket notification would carry, taken from a real sent email.
     */
    private function messageIdFor(Ticket $ticket): string
    {
        $ticket->loadMissing('contact');
        $agent = User::factory()->create();
        app(ReplyTicketAction::class)->execute(ticket: $ticket, body: 'Agent update.', senderType: MessageSenderType::Agent, user: $agent);
        $ticket->messages()->delete();

        return $this->lastSentMessageId();
    }

    private function lastSentMessageId(): string
    {
        $emails = $this->sentEmails();
        $this->assertNotEmpty($emails);

        $header = end($emails)->getHeaders()->get('Message-ID');
        $this->assertNotNull($header);

        return $header->getBodyAsString();
    }

    /**
     * @return list<Email>
     */
    private function sentEmails(): array
    {
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        return $transport->messages()
            ->map(fn (SentMessage $sent): Email => $sent->getOriginalMessage() instanceof Email ? $sent->getOriginalMessage() : throw new \LogicException('Not an email.'))
            ->values()
            ->all();
    }
}
