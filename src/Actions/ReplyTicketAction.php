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
