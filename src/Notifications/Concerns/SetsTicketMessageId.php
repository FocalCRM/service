<?php

declare(strict_types=1);

namespace Odden\Service\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;
use Odden\Service\Models\Ticket;
use Symfony\Component\Mime\Email;

/**
 * Gives customer ticket emails a Message-ID of the form <ticket.{portal_token}.{unique}@{host}>.
 *
 * Mail clients copy it into the In-Reply-To and References headers of a reply, which is how
 * InboundEmailWebhookController recognizes the ticket. The portal token is the secret; the
 * same token is already in the portal link every one of these emails contains.
 */
trait SetsTicketMessageId
{
    protected function withTicketMessageId(MailMessage $mail, Ticket $ticket): MailMessage
    {
        $messageId = static::ticketMessageId($ticket);

        return $mail->withSymfonyMessage(function (Email $message) use ($messageId): void {
            $headers = $message->getHeaders();
            $headers->remove('Message-ID');
            $headers->addIdHeader('Message-ID', $messageId);
        });
    }

    /**
     * A new Message-ID (without angle brackets) for an email about the ticket.
     */
    public static function ticketMessageId(Ticket $ticket): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return 'ticket.'.$ticket->portal_token.'.'.bin2hex(random_bytes(8)).'@'.(is_string($host) && $host !== '' ? $host : 'localhost');
    }
}
