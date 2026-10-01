<?php

declare(strict_types=1);

namespace Focal\Service\Notifications;

use Focal\Service\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketResolvedCsatNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public ?string $resolutionNote = null
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
        $subject = "[#{$this->ticket->ticket_number}] Resolved: {$this->ticket->subject}";

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello,')
            ->line('Your support ticket has been marked as resolved by our customer care team.')
            ->line("**Ticket Reference:** #{$this->ticket->ticket_number}")
            ->line("**Subject:** {$this->ticket->subject}");

        if (! empty($this->resolutionNote)) {
            $mail->line("**Resolution Summary:** {$this->resolutionNote}");
        }

        return $mail
            ->line('How did we do? We value your feedback and would love to hear about your experience:')
            ->action('Rate Your Support Experience', $this->ticket->getCsatUrl())
            ->line('If you feel your issue is not fully resolved, you can reopen your ticket at any time using your portal link.');
    }
}
