<?php

declare(strict_types=1);

use Odden\Core\Actions\MergeCompaniesAction;
use Odden\Core\Actions\MergeContactsAction;
use Odden\Core\Models\Company;
use Odden\Core\Models\Contact;
use Odden\Service\Models\Ticket;
use Odden\Service\Models\TicketMessage;

test('merging contacts moves the secondary contact\'s tickets and ticket messages to the primary', function () {
    [$primary, $secondary] = Contact::factory()->count(2)->create();
    $open = Ticket::create(['subject' => 'Cannot log in', 'contact_id' => $secondary->id]);
    $archived = Ticket::create(['subject' => 'Old question', 'contact_id' => $secondary->id]);
    $archived->delete();
    $own = Ticket::create(['subject' => 'Billing', 'contact_id' => $primary->id]);
    $message = TicketMessage::create(['ticket_id' => $open->id, 'sender_type' => 'customer', 'contact_id' => $secondary->id, 'body' => 'Still broken']);

    app(MergeContactsAction::class)->execute($primary, $secondary);

    expect($open->fresh()->contact_id)->toBe($primary->id)
        ->and(Ticket::withTrashed()->find($archived->id)->contact_id)->toBe($primary->id)
        ->and($own->fresh()->contact_id)->toBe($primary->id)
        ->and($message->fresh()->contact_id)->toBe($primary->id)
        ->and($primary->tickets()->count())->toBe(2);
});

test('merging companies moves the secondary company\'s tickets to the primary', function () {
    [$primary, $secondary] = Company::factory()->count(2)->create();
    $ticket = Ticket::create(['subject' => 'Outage', 'company_id' => $secondary->id]);
    $archived = Ticket::create(['subject' => 'Old outage', 'company_id' => $secondary->id]);
    $archived->delete();

    app(MergeCompaniesAction::class)->execute($primary, $secondary);

    expect($ticket->fresh()->company_id)->toBe($primary->id)
        ->and(Ticket::withTrashed()->find($archived->id)->company_id)->toBe($primary->id)
        ->and($secondary->tickets()->count())->toBe(0);
});
