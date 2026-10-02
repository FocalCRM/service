<?php

declare(strict_types=1);

namespace Odden\Service\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Odden\Service\Models\Ticket;
use Odden\Service\Models\TicketMessage;
use Odden\Service\Notifications\Concerns\SetsTicketMessageId;
use Odden\Service\Notifications\Concerns\UsesServiceNotificationQueue;

class TicketRepliedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SetsTicketMessageId;
    use UsesServiceNotificationQueue;

    public function __construct(
        public Ticket $ticket,
        public TicketMessage $message
    ) {
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
        $subject = "[#{$this->ticket->ticket_number}] Re: {$this->ticket->subject}";
        $preview = Str::limit(strip_tags($this->message->body), 500);

        return $this->withTicketMessageId(new MailMessage, $this->ticket)
            ->subject($subject)
            ->greeting('Hello,')
            ->line('A support agent has posted an update on your ticket:')
            ->line("> {$preview}")
            ->action('View Ticket & Reply Online', $this->ticket->getPortalUrl())
            ->line('You can also reply directly to this email to update the support thread.');
    }
}
