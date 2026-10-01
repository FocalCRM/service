<?php

declare(strict_types=1);

namespace Focal\Service\Enums;

enum MessageSenderType: string
{
    case Agent = 'agent';
    case Customer = 'customer';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Agent => 'Support Agent',
            self::Customer => 'Customer',
            self::System => 'System Automation',
        };
    }
}
