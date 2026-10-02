<?php

declare(strict_types=1);

namespace Odden\Service\Actions;

use Odden\Core\Models\Company;
use Odden\Core\Models\Contact;
use Odden\Service\Enums\MessageSenderType;
use Odden\Service\Enums\TicketPriority;
use Odden\Service\Enums\TicketSource;
use Odden\Service\Enums\TicketStatus;
use Odden\Service\Models\SlaPolicy;
use Odden\Service\Models\Ticket;
use Odden\Service\Notifications\TicketCreatedNotification;
use Illuminate\Database\Eloquent\Model;

class CreateTicketAction
{
    /**
     * Create a new support ticket and record initial activity.
     *
     * Seeds the first customer message from the description, routes the ticket when no owner
     * is given, logs a task on the contact's timeline and, unless $notifyContact is false,
     * emails the contact a confirmation (TicketCreatedNotification, queued).
     *
     * @param  array<string, mixed>  $properties
     */
    public function execute(
        string $subject,
        ?string $description = null,
        TicketPriority $priority = TicketPriority::Medium,
        TicketSource $source = TicketSource::WebPortal,
        ?Contact $contact = null,
        ?Company $company = null,
        ?Model $owner = null,
        ?SlaPolicy $slaPolicy = null,
        array $properties = [],
        bool $notifyContact = true
    ): Ticket {
        // Auto-link company from contact if not explicitly provided
        if ($company === null && $contact !== null && $contact->companies()->exists()) {
            /** @var Company|null $linkedCompany */
            $linkedCompany = $contact->companies()->first();
            $company = $linkedCompany;
        }

        $ticket = Ticket::create([
            'subject' => $subject,
            'description' => $description,
            'status' => TicketStatus::New,
            'priority' => $priority,
            'source' => $source,
            'contact_id' => $contact?->id,
            'company_id' => $company?->id,
            'owner_id' => $owner?->getKey(),
            'sla_policy_id' => $slaPolicy?->id,
            'properties' => $properties,
        ]);

        // If description is provided, seed the first customer message in the thread
        if (! empty($description)) {
            $ticket->messages()->create([
                'body' => $description,
                'sender_type' => MessageSenderType::Customer->value,
                'contact_id' => $contact?->id,
                'is_internal_note' => false,
            ]);
        }

        // Auto-route ticket if no owner specified
        if ($owner === null) {
            (new RouteTicketAction)->execute($ticket);
        }

        // Log activity on Contact timeline
        if ($contact !== null) {
            $contact->logTask(
                title: "Support Ticket #{$ticket->ticket_number}: {$subject}",
                dueAt: $ticket->first_response_due_at,
                body: "Ticket created via {$source->label()} with {$priority->label()} priority."
            );

            if ($notifyContact && ! empty($contact->email)) {
                $contact->notify(new TicketCreatedNotification($ticket));
            }
        }

        return $ticket;
    }
}
