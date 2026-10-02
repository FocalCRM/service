<?php

declare(strict_types=1);

namespace Odden\Service\Enums;

enum TicketStatus: string
{
    case New = 'new';
    case Open = 'open';
    case WaitingOnCustomer = 'waiting_on_customer';
    case WaitingOnAgent = 'waiting_on_agent';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Open => 'Open',
            self::WaitingOnCustomer => 'Waiting on Customer',
            self::WaitingOnAgent => 'Waiting on Agent',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Open => 'primary',
            self::WaitingOnCustomer => 'warning',
            self::WaitingOnAgent => 'danger',
            self::Resolved => 'success',
            self::Closed => 'gray',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Resolved, self::Closed], true);
    }
}
