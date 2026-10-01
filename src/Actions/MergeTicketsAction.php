<?php

declare(strict_types=1);

namespace Focal\Service\Actions;

use Focal\Service\Enums\MessageSenderType;
use Focal\Service\Enums\TicketStatus;
use Focal\Service\Models\Ticket;
use Focal\Service\Models\TicketMessage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MergeTicketsAction
{
    /**
     * Merge a secondary (duplicate) ticket into a primary ticket.
     *
     * @param  Ticket  $primaryTicket  The ticket to keep active and receive merged messages
     * @param  Ticket  $secondaryTicket  The duplicate ticket to close and merge into primary
     * @param  string|null  $reason  Optional reason for the merge
     * @param  int|null  $performedByUserId  The user/agent initiating the merge
     *
     * @throws InvalidArgumentException
     */
    public function execute(
        Ticket $primaryTicket,
        Ticket $secondaryTicket,
        ?string $reason = null,
        ?int $performedByUserId = null
    ): Ticket {
        if ($primaryTicket->id === $secondaryTicket->id) {
            throw new InvalidArgumentException('Cannot merge a ticket into itself.');
        }

        return DB::transaction(function () use ($primaryTicket, $secondaryTicket, $reason, $performedByUserId): Ticket {
            $reasonText = $reason ? " Reason: {$reason}" : '';

            // 1. Move all messages from secondary ticket into primary ticket
            TicketMessage::query()
                ->where('ticket_id', $secondaryTicket->id)
                ->update(['ticket_id' => $primaryTicket->id]);

            // 2. Add an internal audit note to the primary ticket
            TicketMessage::create([
                'ticket_id' => $primaryTicket->id,
                'sender_type' => MessageSenderType::System,
                'user_id' => $performedByUserId,
                'body' => "Ticket #{$secondaryTicket->ticket_number} ('{$secondaryTicket->subject}') was merged into this ticket.{$reasonText}",
                'is_internal_note' => true,
            ]);

            // 3. Mark secondary ticket as merged & closed
            $secondaryTicket->merged_into_ticket_id = $primaryTicket->id;
            $secondaryTicket->merged_at = now();
            $secondaryTicket->status = TicketStatus::Closed;
            $secondaryTicket->closed_at = now();
            $secondaryTicket->save();

            // 4. Add an internal note to the closed secondary ticket
            TicketMessage::create([
                'ticket_id' => $secondaryTicket->id,
                'sender_type' => MessageSenderType::System,
                'user_id' => $performedByUserId,
                'body' => "This ticket was merged into #{$primaryTicket->ticket_number}. All future communications will occur in ticket #{$primaryTicket->ticket_number}.{$reasonText}",
                'is_internal_note' => true,
            ]);

            return $primaryTicket->fresh() ?? $primaryTicket;
        });
    }
}
