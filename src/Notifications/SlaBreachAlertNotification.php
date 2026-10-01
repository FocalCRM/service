<?php

declare(strict_types=1);

namespace Focal\Service\Notifications;

use Focal\Core\Support\UserModel;
use Focal\Service\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaBreachAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public string $breachType // 'first_response' or 'resolution'
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
        $typeName = $this->breachType === 'first_response' ? 'First Response' : 'Resolution';
        $subject = "[URGENT SLA BREACH] Ticket #{$this->ticket->ticket_number}: {$this->ticket->subject}";

        $agentName = UserModel::displayName($this->ticket->owner, 'Unassigned');

        return (new MailMessage)
            ->error()
            ->subject($subject)
            ->greeting('Attention Support Team,')
            ->line("An SLA target has been breached on support ticket #{$this->ticket->ticket_number}.")
            ->line("**Breach Type:** {$typeName} SLA Target Exceeded")
            ->line('**Priority:** '.$this->ticket->priority->getLabel())
            ->line("**Subject:** {$this->ticket->subject}")
            ->line("**Assigned Agent:** {$agentName}")
            ->action('Open Ticket in Cockpit', url('/admin/tickets/'.$this->ticket->id.'/edit'))
            ->line("Please take immediate action to address this customer's inquiry.");
    }
}
