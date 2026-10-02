<?php

declare(strict_types=1);

namespace Odden\Service\Notifications;

use Odden\Service\Models\Ticket;
use Odden\Service\Notifications\Concerns\SetsTicketMessageId;
use Odden\Service\Notifications\Concerns\UsesServiceNotificationQueue;
use Odden\Service\Support\MailMarkdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SetsTicketMessageId;
    use UsesServiceNotificationQueue;

    public function __construct(public Ticket $ticket)
    {
        $this->useServiceNotificationQueue();
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $subject = "[#{$this->ticket->ticket_number}] Support Request Received: {$this->ticket->subject}";

        return $this->withTicketMessageId(new MailMessage, $this->ticket)
            ->subject($subject)
            ->greeting('Hello,')
            ->line('Thank you for contacting customer support. We have received your request and our team is actively reviewing it.')
            ->line("**Ticket Reference:** #{$this->ticket->ticket_number}")
            ->line('**Subject:** '.MailMarkdown::escape($this->ticket->subject))
            ->line('**Priority:** '.$this->ticket->priority->getLabel())
            ->action('View & Track Ticket', $this->ticket->getPortalUrl())
            ->line('You can check status updates, respond, or attach additional files at any time via the customer portal link above.');
    }
}
