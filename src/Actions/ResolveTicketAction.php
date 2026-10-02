<?php

declare(strict_types=1);

namespace Odden\Service\Actions;

use Odden\Service\Models\Ticket;
use Odden\Service\Notifications\TicketResolvedCsatNotification;

class ResolveTicketAction
{
    /**
     * Resolve a ticket and record outcome.
     */
    public function execute(
        Ticket $ticket,
        ?string $resolutionNote = null,
        ?int $csatRating = null,
        ?string $csatComment = null
    ): Ticket {
        $ticket->resolve($resolutionNote);

        if ($csatRating !== null) {
            $ticket->update([
                'csat_rating' => $csatRating,
                'csat_comment' => $csatComment,
            ]);
        }

        if ($ticket->contact !== null) {
            $ticket->contact->logNote(
                body: "Support Ticket #{$ticket->ticket_number} was resolved. ".($resolutionNote ? "Note: {$resolutionNote}" : '')
            );

            if (! empty($ticket->contact->email)) {
                $ticket->contact->notify(new TicketResolvedCsatNotification($ticket, $resolutionNote));
            }
        }

        return $ticket;
    }
}
