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

        // Only thread onto a ticket when the email carries that ticket's portal token (in a reply
        // header or a portal link), the sender is the ticket's contact, and, if required, the
        // provider authenticated the sender. Anything else gets a new ticket of its own.
        $existingTicket = $this->senderIsAuthenticated($request)
            ? $this->findReferencedTicket($request, $subject, $body, $sender['email'])
            : null;

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
     * Find the ticket whose portal token the email carries and whose contact is the sender.
     *
     * Tokens are read from ticket Message-IDs in the In-Reply-To and References headers first,
     * then from portal links in the subject and body. Ticket numbers are guessable and are
     * deliberately not used.
     */
    protected function findReferencedTicket(Request $request, string $subject, string $body, string $senderEmail): ?Ticket
    {
        $tokens = [];

        // Message-IDs set by SetsTicketMessageId: <ticket.{portal_token}.{unique}@{host}>.
        if (preg_match_all('/(?<![A-Za-z0-9.])ticket\.([A-Za-z0-9]{40})\.[A-Za-z0-9]+@/', $this->replyHeaders($request), $headerMatches)) {
            array_push($tokens, ...$headerMatches[1]);
        }

        // Portal links: /support/tickets/{token}, portal/{token} or token={token}.
        foreach ([$subject, $body] as $haystack) {
            if (preg_match_all('/(?:support\/tickets\/|portal\/|token=)([a-zA-Z0-9]{40})(?![a-zA-Z0-9])/i', $haystack, $linkMatches)) {
                array_push($tokens, ...$linkMatches[1]);
            }
        }

        foreach (array_unique($tokens) as $token) {
            /** @var Ticket|null $ticket */
            $ticket = Ticket::query()->with('contact')->where('portal_token', $token)->first();

            if ($ticket !== null && $this->isTicketContact($ticket, $senderEmail)) {
                return $ticket;
            }
        }

        return null;
    }

    /**
     * The In-Reply-To and References values from every place a provider may put them, joined.
     *
     * Read from the in_reply_to / In-Reply-To and references / References fields, the HTTP
     * headers of the same names, and a headers / Headers field holding a raw header block,
     * a name => value map, or a list of {Name, Value} objects or [name, value] pairs.
     */
    protected function replyHeaders(Request $request): string
    {
        $values = [
            $request->input('in_reply_to'),
            $request->input('In-Reply-To'),
            $request->input('references'),
            $request->input('References'),
            $request->header('In-Reply-To'),
            $request->header('References'),
        ];

        foreach (['headers', 'Headers'] as $field) {
            array_push($values, ...$this->replyHeadersFromField($request->input($field)));
        }

        return implode(' ', array_filter($values, is_string(...)));
    }

    /**
     * @return list<string>
     */
    protected function replyHeadersFromField(mixed $headers): array
    {
        $isReplyHeader = fn (mixed $name): bool => is_string($name) && in_array(strtolower(trim($name)), ['in-reply-to', 'references'], true);

        if (is_string($headers)) {
            $unfolded = (string) preg_replace('/\r?\n[ \t]+/', ' ', $headers);
            preg_match_all('/^(?:In-Reply-To|References):(.*)$/mi', $unfolded, $matches);

            return $matches[1];
        }

        if (! is_array($headers)) {
            return [];
        }

        $found = [];

        foreach ($headers as $name => $value) {
            if (is_int($name) && is_array($value) && array_is_list($value) && count($value) === 2) {
                [$name, $value] = $value;
            } elseif (is_int($name) && is_array($value) && (isset($value['Name']) || isset($value['name']))) {
                [$name, $value] = [$value['Name'] ?? $value['name'], $value['Value'] ?? $value['value'] ?? null];
            }

            if ($isReplyHeader($name)) {
                array_push($found, ...array_filter(is_array($value) ? $value : [$value], is_string(...)));
            }
        }

        return $found;
    }

    /**
     * Whether threading is allowed by the provider's sender authentication verdict.
     *
     * Only checked when focal-service.inbound_email.require_authenticated_sender is true. The
     * sender counts as authenticated when sender_authenticated is true, 1, "true", "yes", "on"
     * or "pass", or dmarc is "pass" (both case-insensitive).
     */
    protected function senderIsAuthenticated(Request $request): bool
    {
        if (! (bool) config('focal-service.inbound_email.require_authenticated_sender', false)) {
            return true;
        }

        $verdict = $request->input('sender_authenticated');

        if ($verdict === true || (is_scalar($verdict) && in_array(strtolower(trim((string) $verdict)), ['1', 'true', 'yes', 'on', 'pass'], true))) {
            return true;
        }

        $dmarc = $request->input('dmarc');

        return is_string($dmarc) && strtolower(trim($dmarc)) === 'pass';
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
