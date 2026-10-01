<?php

declare(strict_types=1);

namespace Focal\Service\Notifications;

use Focal\Service\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

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

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello,')
            ->line('Thank you for contacting customer support. We have received your request and our team is actively reviewing it.')
            ->line("**Ticket Reference:** #{$this->ticket->ticket_number}")
            ->line("**Subject:** {$this->ticket->subject}")
            ->line('**Priority:** '.$this->ticket->priority->getLabel())
            ->action('View & Track Ticket', $this->ticket->getPortalUrl())
            ->line('You can check status updates, respond, or attach additional files at any time via the customer portal link above.');
    }
}
