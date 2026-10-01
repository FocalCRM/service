<?php

declare(strict_types=1);

namespace Focal\Service\Http\Controllers;

use Focal\Core\Models\Contact;
use Focal\Service\Actions\CreateTicketAction;
use Focal\Service\Actions\ReplyTicketAction;
use Focal\Service\Enums\MessageSenderType;
use Focal\Service\Enums\TicketPriority;
use Focal\Service\Enums\TicketSource;
use Focal\Service\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class SupportPortalController extends Controller
{
    /**
     * Show ticket submission form.
     */
    public function create(): View
    {
        return view('focal-service::portal.create');
    }

    /**
     * Submit a new customer support ticket.
     */
    public function store(Request $request, CreateTicketAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'string', 'in:low,medium,high,urgent'],
            'description' => ['required', 'string'],
        ]);

        // Split name into first and last name
        $nameParts = explode(' ', trim($validated['name']), 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        /** @var Contact $contact */
        $contact = Contact::query()->firstOrCreate(
            ['email' => $validated['email']],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]
        );

        $priority = TicketPriority::tryFrom($validated['priority']) ?? TicketPriority::Medium;

        $ticket = $action->execute(
            subject: $validated['subject'],
            description: $validated['description'],
            priority: $priority,
            source: TicketSource::WebPortal,
            contact: $contact
        );

        return redirect()
            ->route('focal.support.show', ['token' => $ticket->portal_token])
            ->with('status', 'Your support ticket has been received. Our team will review it shortly.');
    }

    /**
     * View customer ticket status and conversation thread.
     */
    public function show(string $token): View
    {
        /** @var Ticket $ticket */
        $ticket = Ticket::query()
            ->where('portal_token', $token)
            ->with(['messages.user', 'messages.contact', 'contact', 'company'])
            ->firstOrFail();

        return view('focal-service::portal.show', [
            'ticket' => $ticket,
        ]);
    }

    /**
     * Post a customer reply to the ticket thread.
     */
    public function reply(Request $request, string $token, ReplyTicketAction $action): RedirectResponse
    {
        /** @var Ticket $ticket */
        $ticket = Ticket::query()
            ->where('portal_token', $token)
            ->firstOrFail();

        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $action->execute(
            ticket: $ticket,
            body: $validated['body'],
            senderType: MessageSenderType::Customer,
            user: null,
            contact: $ticket->contact,
            isInternalNote: false
        );

        return back()->with('status', 'Your reply has been posted to the ticket.');
    }

    /**
     * Show CSAT rating survey form.
     */
    public function rate(string $token): View
    {
        /** @var Ticket $ticket */
        $ticket = Ticket::query()
            ->where('portal_token', $token)
            ->firstOrFail();

        return view('focal-service::portal.rate', [
            'ticket' => $ticket,
        ]);
    }

    /**
     * Save CSAT rating feedback.
     */
    public function submitRating(Request $request, string $token): RedirectResponse
    {
        /** @var Ticket $ticket */
        $ticket = Ticket::query()
            ->where('portal_token', $token)
            ->firstOrFail();

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $rating = (int) $validated['rating'];
        $comment = isset($validated['comment']) && $validated['comment'] !== '' ? (string) $validated['comment'] : null;

        $ticket->update([
            'csat_rating' => $rating,
            'csat_comment' => $comment,
        ]);

        // If dissatisfied customer feedback (1 or 2 stars), escalate for service recovery
        if ($rating <= 2) {
            $commentText = $comment !== null ? " Comment: \"{$comment}\"" : '';

            $ticket->messages()->create([
                'body' => "⚠️ Negative CSAT rating ({$rating}/5 stars) received from customer.{$commentText} Supervisor review recommended.",
                'sender_type' => MessageSenderType::System->value,
                'is_internal_note' => true,
            ]);

            if ($ticket->contact !== null) {
                $ticket->contact->logTask(
                    title: "CSAT Service Recovery: Ticket #{$ticket->ticket_number} ({$rating}/5 stars)",
                    dueAt: now()->addHours(24),
                    body: "Customer submitted an unsatisfied CSAT rating for ticket '{$ticket->subject}'.{$commentText} Reach out for service recovery."
                );
            }
        }

        return redirect()
            ->route('focal.support.show', ['token' => $ticket->portal_token])
            ->with('status', 'Thank you! Your feedback has been recorded.');
    }
}
