<?php

declare(strict_types=1);

namespace Focal\Service\Listeners;

use Focal\Core\Events\CompaniesMerged;
use Focal\Core\Events\ContactsMerged;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Moves the Service records keyed to a merged-away contact or company onto the record it was
 * merged into. Runs synchronously inside Core's merge transaction, so a failure rolls the
 * merge back. Soft-deleted tickets move too.
 */
class MoveMergedRecords
{
    public function handleContactsMerged(ContactsMerged $event): void
    {
        $from = $event->secondary->getKey();
        $to = $event->primary->getKey();

        foreach (['tickets', 'messages'] as $table) {
            DB::table($this->table($table))->where('contact_id', $from)->update(['contact_id' => $to]);
        }
    }

    public function handleCompaniesMerged(CompaniesMerged $event): void
    {
        DB::table($this->table('tickets'))
            ->where('company_id', $event->secondary->getKey())
            ->update(['company_id' => $event->primary->getKey()]);
    }

    private function table(string $key): string
    {
        $default = match ($key) {
            'tickets' => 'focal_service_tickets',
            'messages' => 'focal_service_ticket_messages',
            default => throw new InvalidArgumentException("Unknown Service table [{$key}]."),
        };

        return config()->string("focal-service.tables.{$key}", $default);
    }
}
