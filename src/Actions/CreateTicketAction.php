<?php

declare(strict_types=1);

namespace Focal\Service\Actions;

use Focal\Core\Models\Company;
use Focal\Core\Models\Contact;
use Focal\Service\Enums\MessageSenderType;
use Focal\Service\Enums\TicketPriority;
use Focal\Service\Enums\TicketSource;
use Focal\Service\Enums\TicketStatus;
use Focal\Service\Models\SlaPolicy;
use Focal\Service\Models\Ticket;
use Focal\Service\Notifications\TicketCreatedNotification;
use Illuminate\Database\Eloquent\Model;

class CreateTicketAction
{
    /**
     * Create a new support ticket and record initial activity.
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
        array $properties = []
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

            if (! empty($contact->email)) {
                $contact->notify(new TicketCreatedNotification($ticket));
            }
        }

        return $ticket;
    }
}
