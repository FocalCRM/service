<?php

declare(strict_types=1);

namespace Odden\Service\Tests;

use Odden\Core\Models\Contact;
use Odden\Service\Actions\MergeTicketsAction;
use Odden\Service\Enums\MessageSenderType;
use Odden\Service\Enums\TicketPriority;
use Odden\Service\Enums\TicketStatus;
use Odden\Service\Models\Ticket;
use Odden\Service\Models\TicketMessage;
use Odden\Service\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

class MergeTicketsTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_tickets_reassigns_messages_and_closes_secondary_ticket(): void
    {
        $user = User::factory()->create();
        $contact = Contact::create([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com',
        ]);

        $primaryTicket = Ticket::create([
            'subject' => 'Cannot log into customer portal',
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::High,
            'contact_id' => $contact->id,
        ]);

        $primaryTicket->addMessage('Initial customer message on primary ticket.', MessageSenderType::Customer, contactId: $contact->id);

        $secondaryTicket = Ticket::create([
            'subject' => 'Password reset link expired',
            'status' => TicketStatus::New,
            'priority' => TicketPriority::Medium,
            'contact_id' => $contact->id,
        ]);

        $secondaryTicket->addMessage('Secondary message 1 from customer', MessageSenderType::Customer, contactId: $contact->id);
        $secondaryTicket->addMessage('Secondary reply from agent', MessageSenderType::Agent, userId: $user->id);

        $action = new MergeTicketsAction;
        $updatedPrimary = $action->execute(
            primaryTicket: $primaryTicket,
            secondaryTicket: $secondaryTicket,
            reason: 'Identical auth issue from same customer',
            performedByUserId: $user->id,
        );

        $secondaryTicket->refresh();

        // 1. Secondary ticket is marked as merged and closed
        $this->assertSame(TicketStatus::Closed, $secondaryTicket->status);
        $this->assertSame($primaryTicket->id, $secondaryTicket->merged_into_ticket_id);
        $this->assertNotNull($secondaryTicket->merged_at);
        $this->assertNotNull($secondaryTicket->closed_at);

        // 2. Messages originally on secondary ticket are now on primary ticket
        $primaryMessages = $updatedPrimary->messages;
        $this->assertTrue($primaryMessages->contains('body', 'Secondary message 1 from customer'));
        $this->assertTrue($primaryMessages->contains('body', 'Secondary reply from agent'));

        // 3. System audit notes are present on primary
        $mergeNote = $primaryMessages->first(fn (TicketMessage $m): bool => str_contains($m->body, "Ticket #{$secondaryTicket->ticket_number}") && str_contains($m->body, 'was merged into this ticket'));
        $this->assertNotNull($mergeNote);
        $this->assertTrue($mergeNote->is_internal_note);
        $this->assertStringContainsString('Identical auth issue from same customer', $mergeNote->body);

        // 4. Audit note on secondary ticket
        $secondaryNote = $secondaryTicket->messages()->where('is_internal_note', true)->first();
        $this->assertNotNull($secondaryNote);
        $this->assertStringContainsString("This ticket was merged into #{$primaryTicket->ticket_number}", $secondaryNote->body);

        // 5. Relationship check
        $this->assertSame($primaryTicket->id, $secondaryTicket->mergedInto?->id);
        $this->assertTrue($primaryTicket->mergedTickets->contains('id', $secondaryTicket->id));
    }

    public function test_cannot_merge_ticket_into_itself(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Stand-alone inquiry',
            'status' => TicketStatus::Open,
        ]);

        $this->expectException(InvalidArgumentException::class);
        (new MergeTicketsAction)->execute($ticket, $ticket);
    }
}
