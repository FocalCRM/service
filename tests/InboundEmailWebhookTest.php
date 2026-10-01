<?php

declare(strict_types=1);

namespace Focal\Service\Tests;

use Focal\Core\Models\Contact;
use Focal\Service\Enums\TicketSource;
use Focal\Service\Enums\TicketStatus;
use Focal\Service\Models\SlaPolicy;
use Focal\Service\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InboundEmailWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SlaPolicy::create(SlaPolicy::defaultPreset());
    }

    public function test_inbound_email_creates_new_ticket_and_provisions_contact(): void
    {
        $payload = [
            'from' => 'Jane Doe <jane.doe@enterprise.test>',
            'subject' => 'Cannot access reporting dashboard',
            'body' => 'Getting 403 Forbidden when clicking on Quarterly Reports.',
        ];

        $response = $this->postJson('/api/service/inbound-email', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure(['status', 'ticket_number', 'portal_url']);
        $this->assertSame('created', $response->json('status'));

        // Verify ticket
        $ticket = Ticket::where('ticket_number', $response->json('ticket_number'))->first();
        $this->assertNotNull($ticket);
        $this->assertSame('Cannot access reporting dashboard', $ticket->subject);
        $this->assertSame(TicketSource::Email, $ticket->source);
        $this->assertSame(TicketStatus::New, $ticket->status);

        // Verify Contact created
        $contact = Contact::where('email', 'jane.doe@enterprise.test')->first();
        $this->assertNotNull($contact);
        $this->assertSame('Jane', $contact->first_name);
        $this->assertSame('Doe', $contact->last_name);
        $this->assertSame($contact->id, $ticket->contact_id);

        // Verify initial message in thread
        $this->assertCount(1, $ticket->messages);
        $this->assertSame('Getting 403 Forbidden when clicking on Quarterly Reports.', $ticket->messages->first()?->body);
    }

    public function test_inbound_email_with_ticket_number_in_subject_appends_to_existing_conversation(): void
    {
        $contact = Contact::factory()->create([
            'email' => 'techlead@client.test',
            'first_name' => 'Alex',
            'last_name' => 'Smith',
        ]);

        $ticket = Ticket::create([
            'ticket_number' => 'TICK-2026-ABCD',
            'subject' => 'SAML Metadata expired',
            'status' => TicketStatus::WaitingOnCustomer,
            'contact_id' => $contact->id,
        ]);

        $payload = [
            'from' => 'Alex Smith <techlead@client.test>',
            'subject' => 'Re: [#TICK-2026-ABCD] SAML Metadata expired',
            'body' => 'Here is the renewed certificate attachment: SHA-256 cert renewed until 2028.',
        ];

        $response = $this->postJson('/api/service/inbound-email', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'appended',
            'ticket_number' => 'TICK-2026-ABCD',
        ]);

        $ticket->refresh();
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertCount(1, $ticket->messages);
        $this->assertSame('Here is the renewed certificate attachment: SHA-256 cert renewed until 2028.', $ticket->messages->first()?->body);
    }

    public function test_inbound_email_requires_sender_and_body(): void
    {
        $response = $this->postJson('/api/service/inbound-email', [
            'subject' => 'Empty inquiry',
        ]);

        $response->assertStatus(422);
    }
}
