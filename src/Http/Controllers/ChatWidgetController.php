<?php

declare(strict_types=1);

namespace Focal\Service\Http\Controllers;

use Focal\Core\Models\Company;
use Focal\Core\Models\Contact;
use Focal\Service\Enums\MessageSenderType;
use Focal\Service\Enums\TicketPriority;
use Focal\Service\Enums\TicketSource;
use Focal\Service\Enums\TicketStatus;
use Focal\Service\Models\Ticket;
use Focal\Service\Models\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ChatWidgetController extends Controller
{
    /**
     * Start a new live support chat session from the embedded messenger.
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string'],
            'company' => ['nullable', 'string', 'max:255'],
        ]);

        $nameParts = explode(' ', trim($validated['name']), 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        /** @var Contact $contact */
        $contact = Contact::query()->firstOrCreate(
            ['email' => mb_strtolower($validated['email'])],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'lifecycle_stage' => 'customer',
            ]
        );

        $companyId = null;
        if (! empty($validated['company'])) {
            /** @var Company $company */
            $company = Company::query()->firstOrCreate(
                ['name' => trim($validated['company'])],
                ['lifecycle_stage' => 'customer']
            );
            $companyId = $company->id;
            if (! $contact->isAssociatedWith($company)) {
                $contact->associateWith($company);
            }
        }

        $ticket = Ticket::create([
            'subject' => "Live Chat inquiry from {$contact->full_name}",
            'source' => TicketSource::Chat,
            'status' => TicketStatus::New,
            'priority' => TicketPriority::Medium,
            'contact_id' => $contact->id,
            'company_id' => $companyId,
            'description' => $validated['message'],
        ]);

        // Customer's opening message
        $ticket->addMessage(
            body: $validated['message'],
            senderType: MessageSenderType::Customer,
            contactId: $contact->id,
        );

        // Automated welcoming response from support team
        $ticket->addMessage(
            body: "Hi {$contact->first_name}! 👋 Thanks for reaching out to support. A member of our team has received your message and will reply here momentarily.",
            senderType: MessageSenderType::System,
        );

        return response()->json([
            'success' => true,
            'token' => $ticket->portal_token,
            'ticket_number' => $ticket->ticket_number,
            'messages' => $this->formatMessages($ticket),
        ], 201);
    }

    /**
     * Send a customer follow-up message in an ongoing chat session.
     */
    public function message(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string'],
        ]);

        /** @var Ticket|null $ticket */
        $ticket = Ticket::query()->where('portal_token', $token)->first();

        if ($ticket === null) {
            return response()->json(['error' => 'Chat session not found.'], 404);
        }

        $ticket->addMessage(
            body: $validated['message'],
            senderType: MessageSenderType::Customer,
            contactId: $ticket->contact_id,
        );

        if ($ticket->status === TicketStatus::WaitingOnCustomer || $ticket->status === TicketStatus::Resolved) {
            $ticket->update(['status' => TicketStatus::WaitingOnAgent]);
        }

        return response()->json([
            'success' => true,
            'messages' => $this->formatMessages($ticket),
        ]);
    }

    /**
     * Fetch conversation thread messages for the chat widget.
     */
    public function messages(string $token): JsonResponse
    {
        /** @var Ticket|null $ticket */
        $ticket = Ticket::query()->where('portal_token', $token)->first();

        if ($ticket === null) {
            return response()->json(['error' => 'Chat session not found.'], 404);
        }

        return response()->json([
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status->value,
            'messages' => $this->formatMessages($ticket),
        ]);
    }

    /**
     * Format non-internal messages for public chat display.
     *
     * @return list<array{id: int, sender_type: string, sender_name: string, body: string, is_customer: bool, created_at: string}>
     */
    protected function formatMessages(Ticket $ticket): array
    {
        /** @var list<array{id: int, sender_type: string, sender_name: string, body: string, is_customer: bool, created_at: string}> $list */
        $list = array_values($ticket->messages()
            ->where('is_internal_note', false)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (TicketMessage $m): array => [
                'id' => $m->id,
                'sender_type' => $m->sender_type->value,
                'sender_name' => $m->senderName(),
                'body' => $m->body,
                'is_customer' => $m->sender_type === MessageSenderType::Customer,
                'created_at' => $m->created_at?->diffForHumans() ?? 'just now',
            ])
            ->all());

        return $list;
    }
}
