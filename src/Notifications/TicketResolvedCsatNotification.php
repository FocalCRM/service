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

class TicketResolvedCsatNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SetsTicketMessageId;
    use UsesServiceNotificationQueue;

    public function __construct(
        public Ticket $ticket,
        public ?string $resolutionNote = null
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
        $subject = "[#{$this->ticket->ticket_number}] Resolved: {$this->ticket->subject}";

        $mail = $this->withTicketMessageId(new MailMessage, $this->ticket)
            ->subject($subject)
            ->greeting('Hello,')
            ->line('Your support ticket has been marked as resolved by our customer care team.')
            ->line("**Ticket Reference:** #{$this->ticket->ticket_number}")
            ->line('**Subject:** '.MailMarkdown::escape($this->ticket->subject));

        if (! empty($this->resolutionNote)) {
            $mail->line("**Resolution Summary:** {$this->resolutionNote}");
        }

        return $mail
            ->line('How did we do? We value your feedback and would love to hear about your experience:')
            ->action('Rate Your Support Experience', $this->ticket->getCsatUrl())
            ->line('If you feel your issue is not fully resolved, you can reopen your ticket at any time using your portal link.');
    }
}
