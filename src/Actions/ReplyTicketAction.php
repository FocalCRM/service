<?php

declare(strict_types=1);

namespace Focal\Service\Actions;

use Focal\Core\Models\Contact;
use Focal\Service\Enums\MessageSenderType;
use Focal\Service\Models\Ticket;
use Focal\Service\Models\TicketMessage;
use Focal\Service\Notifications\TicketRepliedNotification;
use Illuminate\Database\Eloquent\Model;

class ReplyTicketAction
{
    /**
     * Post a reply or internal note to a ticket thread.
     *
     * A public customer reply to a ticket that was merged into another is posted on the primary
     * ticket instead (following the whole merge chain; see Ticket::mergeTarget()), and a customer
     * reply reopens a resolved or closed ticket when focal-service.reopen_on_customer_reply is
     * on. Check who may reply against the ticket the customer referenced before calling this.
     * The returned message's ticket_id says which ticket the reply landed on.
     *
     * @param  array<int, mixed>|null  $attachments
     */
    public function execute(
        Ticket $ticket,
        string $body,
        MessageSenderType $senderType = MessageSenderType::Agent,
        ?Model $user = null,
        ?Contact $contact = null,
        bool $isInternalNote = false,
        ?array $attachments = null
    ): TicketMessage {
        if (! $isInternalNote && $senderType === MessageSenderType::Customer) {
            $ticket = $ticket->mergeTarget();
        }

        $message = $ticket->addMessage(
            body: $body,
            senderType: $senderType,
            userId: $user?->getKey(),
            contactId: $contact?->id,
            isInternalNote: $isInternalNote,
            attachments: $attachments
        );

        // If public agent reply, log an email/activity on the contact timeline and notify customer
        if (! $isInternalNote && $senderType === MessageSenderType::Agent && $ticket->contact !== null) {
            $ticket->contact->logNote(
                body: "Agent {$user?->getAttribute('name')} replied to Ticket #{$ticket->ticket_number}: \"".mb_substr(strip_tags($body), 0, 150).'"'
            );

            if (! empty($ticket->contact->email)) {
                $ticket->contact->notify(new TicketRepliedNotification($ticket, $message));
            }
        }

        return $message;
    }
}
