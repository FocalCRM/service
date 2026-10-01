<?php

declare(strict_types=1);

namespace Focal\Service\Tests;

use Focal\Core\Models\Company;
use Focal\Core\Models\Contact;
use Focal\Service\Enums\TicketSource;
use Focal\Service\Enums\TicketStatus;
use Focal\Service\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_start_live_chat_session_creating_contact_and_ticket(): void
    {
        $payload = [
            'name' => 'Sarah Connor',
            'email' => 'sarah@cyberdyne.test',
            'company' => 'Cyberdyne Systems',
            'message' => 'Hello! We need assistance setting up webhook triggers.',
        ];

        $response = $this->postJson(route('focal.service.chat.start'), $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'token',
                'ticket_number',
                'messages',
            ]);

        $token = (string) $response->json('token');
        $this->assertNotEmpty($token);

        /** @var Ticket|null $ticket */
        $ticket = Ticket::query()->where('portal_token', $token)->first();
        $this->assertNotNull($ticket);
        $this->assertSame(TicketSource::Chat, $ticket->source);
        $this->assertSame(TicketStatus::Open, $ticket->status);

        /** @var Contact|null $contact */
        $contact = Contact::query()->where('email', 'sarah@cyberdyne.test')->first();
        $this->assertNotNull($contact);
        $this->assertSame('Sarah', $contact->first_name);
        $this->assertSame('Connor', $contact->last_name);
        $this->assertSame($contact->id, $ticket->contact_id);

        /** @var Company|null $company */
        $company = Company::query()->where('name', 'Cyberdyne Systems')->first();
        $this->assertNotNull($company);
        $this->assertSame($company->id, $ticket->company_id);

        // Verify initial customer message & system greeting
        $messages = $ticket->messages;
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('Hello! We need assistance setting up webhook triggers.', $messages[0]->body);
        $this->assertStringContainsString('Thanks for reaching out', $messages[1]->body);
    }

    public function test_can_send_subsequent_chat_message(): void
    {
        $startResponse = $this->postJson(route('focal.service.chat.start'), [
            'name' => 'Kyle Reese',
            'email' => 'kyle@future.test',
            'message' => 'First message.',
        ]);

        $token = (string) $startResponse->json('token');

        $messageResponse = $this->postJson(route('focal.service.chat.message', ['token' => $token]), [
            'message' => 'Follow up message with more details.',
        ]);

        $messageResponse->assertOk()
            ->assertJsonPath('success', true);

        /** @var Ticket|null $ticket */
        $ticket = Ticket::query()->where('portal_token', $token)->first();
        $this->assertNotNull($ticket);
        $this->assertSame(3, $ticket->messages()->count());
    }

    public function test_can_fetch_chat_messages_by_token(): void
    {
        $startResponse = $this->postJson(route('focal.service.chat.start'), [
            'name' => 'Miles Dyson',
            'email' => 'miles@future.test',
            'message' => 'Can we upgrade our tier?',
        ]);

        $token = (string) $startResponse->json('token');

        $fetchResponse = $this->getJson(route('focal.service.chat.messages', ['token' => $token]));

        $fetchResponse->assertOk()
            ->assertJsonStructure([
                'ticket_number',
                'status',
                'messages' => [
                    '*' => ['id', 'sender_type', 'sender_name', 'body', 'is_customer', 'created_at'],
                ],
            ]);
    }
}
