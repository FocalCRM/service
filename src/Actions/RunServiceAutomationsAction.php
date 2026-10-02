<?php

declare(strict_types=1);

namespace Odden\Service\Actions;

use Odden\Service\Enums\MessageSenderType;
use Odden\Service\Enums\TicketStatus;
use Odden\Service\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

class RunServiceAutomationsAction
{
    /**
     * Run automated maintenance tasks on support tickets.
     *
     * @return array{inactivity_closed: int, resolved_closed: int}
     */
    public function execute(): array
    {
        $inactivityClosedCount = 0;
        $resolvedClosedCount = 0;

        // 1. Auto-close tickets in 'waiting_on_customer' past 7 days
        $sevenDaysAgo = now()->subDays(7);
        /** @var Collection<int, Ticket> $staleCustomerTickets */
        $staleCustomerTickets = Ticket::query()
            ->where('status', TicketStatus::WaitingOnCustomer->value)
            ->where('updated_at', '<=', $sevenDaysAgo)
            ->get();

        foreach ($staleCustomerTickets as $ticket) {
            $ticket->messages()->create([
                'body' => 'Ticket automatically closed after 7 days without customer response.',
                'sender_type' => MessageSenderType::Agent->value,
                'is_internal_note' => false,
            ]);

            $ticket->update([
                'status' => TicketStatus::Closed,
                'closed_at' => now(),
            ]);

            $inactivityClosedCount++;
        }

        // 2. Permanently close resolved tickets after 48 hours
        $twoDaysAgo = now()->subDays(2);
        /** @var Collection<int, Ticket> $pastResolvedTickets */
        $pastResolvedTickets = Ticket::query()
            ->where('status', TicketStatus::Resolved->value)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<=', $twoDaysAgo)
            ->get();

        foreach ($pastResolvedTickets as $ticket) {
            $ticket->update([
                'status' => TicketStatus::Closed,
                'closed_at' => now(),
            ]);

            $resolvedClosedCount++;
        }

        return [
            'inactivity_closed' => $inactivityClosedCount,
            'resolved_closed' => $resolvedClosedCount,
        ];
    }
}
