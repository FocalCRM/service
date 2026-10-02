<?php

declare(strict_types=1);

namespace Odden\Service\Enums;

enum TicketSource: string
{
    case WebPortal = 'web_portal';
    case Email = 'email';
    case Phone = 'phone';
    case Chat = 'chat';
    case Api = 'api';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::WebPortal => 'Customer Portal',
            self::Email => 'Email Inbound',
            self::Phone => 'Phone Call',
            self::Chat => 'Live Chat',
            self::Api => 'API / Integration',
        };
    }
}
