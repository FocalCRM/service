<?php

declare(strict_types=1);

namespace Focal\Service\Notifications;

use Focal\Service\Models\Ticket;
use Focal\Service\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TicketRepliedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public TicketMessage $message
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $subject = "[#{$this->ticket->ticket_number}] Re: {$this->ticket->subject}";
        $preview = Str::limit(strip_tags($this->message->body), 500);

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello,')
            ->line('A support agent has posted an update on your ticket:')
            ->line("> {$preview}")
            ->action('View Ticket & Reply Online', $this->ticket->getPortalUrl())
            ->line('You can also reply directly to this email to update the support thread.');
    }
}
