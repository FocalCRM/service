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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InboundEmailWebhookController extends Controller
{
    /**
     * Handle incoming parsed email webhooks (Postmark, Mailgun, SendGrid, or generic JSON).
     */
    public function __invoke(
        Request $request,
        CreateTicketAction $createAction,
        ReplyTicketAction $replyAction
    ): JsonResponse {
        $fromRaw = (string) ($request->input('from') ?? $request->input('sender') ?? $request->input('From') ?? '');
        $subject = (string) ($request->input('subject') ?? $request->input('Subject') ?? 'No Subject');
        $body = (string) ($request->input('body') ?? $request->input('text') ?? $request->input('stripped-text') ?? $request->input('html') ?? $request->input('body_html') ?? '');

        if (empty($fromRaw) || empty($body)) {
            return response()->json([
                'error' => 'Missing required email fields (from, body/text).',
            ], 422);
        }

        $sender = $this->parseSender($fromRaw);

        // Check if incoming email references an existing ticket
        $existingTicket = $this->findReferencedTicket($request, $subject, $body);

        if ($existingTicket !== null) {
            $contact = $this->resolveOrCreateContact($sender['email'], $sender['name']);

            $message = $replyAction->execute(
                ticket: $existingTicket,
                body: $body,
                senderType: MessageSenderType::Customer,
                user: null,
                contact: $contact,
                isInternalNote: false
            );

            return response()->json([
                'status' => 'appended',
                'ticket_number' => $existingTicket->ticket_number,
                'message_id' => $message->id,
            ]);
        }

        // New Ticket Creation
        $contact = $this->resolveOrCreateContact($sender['email'], $sender['name']);

        $newTicket = $createAction->execute(
            subject: $subject,
            description: $body,
            priority: TicketPriority::Medium,
            source: TicketSource::Email,
            contact: $contact
        );

        return response()->json([
            'status' => 'created',
            'ticket_number' => $newTicket->ticket_number,
            'portal_url' => $newTicket->getPortalUrl(),
        ], 201);
    }

    /**
     * Parse name and email from an RFC 2822 sender string.
     *
     * @return array{name: string, email: string}
     */
    protected function parseSender(string $from): array
    {
        if (preg_match('/(.*)<(.+@.+?)>/', $from, $matches)) {
            return [
                'name' => trim(trim($matches[1]), '"\' '),
                'email' => trim($matches[2]),
            ];
        }

        $email = trim($from);
        $name = explode('@', $email)[0];

        return [
            'name' => $name,
            'email' => $email,
        ];
    }

    /**
     * Find existing or create a new Contact record for the sender.
     */
    protected function resolveOrCreateContact(string $email, string $fullName): Contact
    {
        /** @var Contact|null $contact */
        $contact = Contact::query()->where('email', $email)->first();

        if ($contact !== null) {
            return $contact;
        }

        $parts = explode(' ', trim($fullName), 2);
        $firstName = $parts[0] !== '' ? $parts[0] : 'Customer';
        $lastName = $parts[1] ?? '';

        /** @var Contact $created */
        $created = Contact::query()->create([
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        return $created;
    }

    /**
     * Attempt to find an existing ticket referenced in the email subject, body, or headers.
     */
    protected function findReferencedTicket(Request $request, string $subject, string $body): ?Ticket
    {
        $prefix = preg_quote((string) config('focal-service.defaults.prefix', 'TICK'), '/');

        // 1. Check subject for ticket number pattern (e.g. [#TICK-2026-0001] or #PREFIX-2026-0001)
        if (preg_match('/(?:#)?((?:'.$prefix.'|TICK)-\d{4}-[A-Z0-9]{4,6})/i', $subject, $matches)) {
            /** @var Ticket|null $ticket */
            $ticket = Ticket::query()->where('ticket_number', strtoupper($matches[1]))->first();
            if ($ticket !== null) {
                return $ticket;
            }
        }

        // 2. Check email headers (In-Reply-To or References) for ticket number
        $headers = (string) ($request->header('In-Reply-To') ?? $request->header('References') ?? $request->input('In-Reply-To') ?? $request->input('References') ?? '');
        if ($headers !== '' && preg_match('/((?:'.$prefix.'|TICK)-\d{4}-[A-Z0-9]{4,6})/i', $headers, $hMatches)) {
            /** @var Ticket|null $ticket */
            $ticket = Ticket::query()->where('ticket_number', strtoupper($hMatches[1]))->first();
            if ($ticket !== null) {
                return $ticket;
            }
        }

        // 3. Check body for embedded portal token or ticket number signature
        if (preg_match('/(?:portal\/|token=)([a-zA-Z0-9]{40})/i', $body, $tokenMatches)) {
            /** @var Ticket|null $ticket */
            $ticket = Ticket::query()->where('portal_token', $tokenMatches[1])->first();
            if ($ticket !== null) {
                return $ticket;
            }
        }

        if (preg_match('/(?:#)?((?:'.$prefix.'|TICK)-\d{4}-[A-Z0-9]{4,6})/i', $body, $bodyMatches)) {
            /** @var Ticket|null $ticket */
            $ticket = Ticket::query()->where('ticket_number', strtoupper($bodyMatches[1]))->first();
            if ($ticket !== null) {
                return $ticket;
            }
        }

        return null;
    }
}
