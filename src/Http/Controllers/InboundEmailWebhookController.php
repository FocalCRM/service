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

        // Only thread onto a referenced ticket when the sender is that ticket's contact; anyone
        // else gets a new ticket of their own, as if no ticket reference were present.
        $existingTicket = $this->findReferencedTicket($request, $subject, $body, $sender['email']);

        if ($existingTicket !== null && $existingTicket->contact !== null) {
            $message = $replyAction->execute(
                ticket: $existingTicket,
                body: $body,
                senderType: MessageSenderType::Customer,
                user: null,
                contact: $existingTicket->contact,
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
     * Find an existing ticket referenced in the email subject, headers, or body whose contact is the sender.
     */
    protected function findReferencedTicket(Request $request, string $subject, string $body, string $senderEmail): ?Ticket
    {
        $configuredPrefix = (string) config('focal-service.defaults.prefix', 'TICK');
        $pattern = '/((?:'.preg_quote($configuredPrefix, '/').'|TICK)-\d{4}-[A-Z0-9]{4,6})/i';

        $headers = (string) ($request->header('In-Reply-To') ?? $request->header('References') ?? $request->input('In-Reply-To') ?? $request->input('References') ?? '');

        // 1. Subject, 2. In-Reply-To / References headers, 3. body: ticket number (e.g. [#TICK-2026-AB12C]).
        $candidates = [];
        foreach ([$subject, $headers] as $haystack) {
            if ($haystack !== '' && preg_match_all($pattern, $haystack, $matches)) {
                array_push($candidates, ...array_map(fn (string $number): array => ['ticket_number', $this->normalizeTicketNumber($number, $configuredPrefix)], $matches[1]));
            }
        }

        // Body: portal link (/support/tickets/{token}, portal/{token} or token={token}) before a ticket number.
        if (preg_match_all('/(?:support\/tickets\/|portal\/|token=)([a-zA-Z0-9]{40})(?![a-zA-Z0-9])/i', $body, $tokenMatches)) {
            array_push($candidates, ...array_map(fn (string $token): array => ['portal_token', $token], $tokenMatches[1]));
        }

        if (preg_match_all($pattern, $body, $bodyMatches)) {
            array_push($candidates, ...array_map(fn (string $number): array => ['ticket_number', $this->normalizeTicketNumber($number, $configuredPrefix)], $bodyMatches[1]));
        }

        foreach ($candidates as [$column, $value]) {
            /** @var Ticket|null $ticket */
            $ticket = Ticket::query()->with('contact')->where($column, $value)->first();

            if ($ticket !== null && $this->isTicketContact($ticket, $senderEmail)) {
                return $ticket;
            }
        }

        return null;
    }

    /**
     * Restore a matched ticket number to its stored form: the configured prefix's case, an uppercase suffix.
     */
    protected function normalizeTicketNumber(string $number, string $configuredPrefix): string
    {
        if (stripos($number, $configuredPrefix.'-') === 0) {
            return $configuredPrefix.strtoupper(substr($number, strlen($configuredPrefix)));
        }

        return strtoupper($number);
    }

    /**
     * Whether the sender's email is the ticket contact's email (case-insensitive, trimmed).
     */
    protected function isTicketContact(Ticket $ticket, string $senderEmail): bool
    {
        $contactEmail = mb_strtolower(trim((string) $ticket->contact?->email));

        return $contactEmail !== '' && $contactEmail === mb_strtolower(trim($senderEmail));
    }
}
