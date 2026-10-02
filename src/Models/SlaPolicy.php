<?php

declare(strict_types=1);

namespace Odden\Service\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Odden\Service\Enums\TicketPriority;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 * @property bool $is_active
 * @property bool $only_business_hours
 * @property string $business_hours_start
 * @property string $business_hours_end
 * @property list<int>|null $business_days
 * @property list<string>|null $holidays
 * @property string $timezone
 * @property int $urgent_first_response_minutes
 * @property int $urgent_resolution_minutes
 * @property int $high_first_response_minutes
 * @property int $high_resolution_minutes
 * @property int $medium_first_response_minutes
 * @property int $medium_resolution_minutes
 * @property int $low_first_response_minutes
 * @property int $low_resolution_minutes
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, Ticket> $tickets
 */
class SlaPolicy extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'is_default',
        'is_active',
        'only_business_hours',
        'business_hours_start',
        'business_hours_end',
        'business_days',
        'holidays',
        'timezone',
        'urgent_first_response_minutes',
        'urgent_resolution_minutes',
        'high_first_response_minutes',
        'high_resolution_minutes',
        'medium_first_response_minutes',
        'medium_resolution_minutes',
        'low_first_response_minutes',
        'low_resolution_minutes',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('odden-service.tables.sla_policies', 'odden_service_sla_policies');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'only_business_hours' => 'boolean',
            'business_days' => 'array',
            'holidays' => 'array',
            'urgent_first_response_minutes' => 'integer',
            'urgent_resolution_minutes' => 'integer',
            'high_first_response_minutes' => 'integer',
            'high_resolution_minutes' => 'integer',
            'medium_first_response_minutes' => 'integer',
            'medium_resolution_minutes' => 'integer',
            'low_first_response_minutes' => 'integer',
            'low_resolution_minutes' => 'integer',
        ];
    }

    /**
     * Tickets adhering to this SLA.
     *
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'sla_policy_id');
    }

    /**
     * Get the first response target in minutes for a given priority.
     */
    public function getFirstResponseMinutesFor(TicketPriority $priority): int
    {
        return match ($priority) {
            TicketPriority::Urgent => $this->urgent_first_response_minutes,
            TicketPriority::High => $this->high_first_response_minutes,
            TicketPriority::Medium => $this->medium_first_response_minutes,
            TicketPriority::Low => $this->low_first_response_minutes,
        };
    }

    /**
     * Get the resolution target in minutes for a given priority.
     */
    public function getResolutionMinutesFor(TicketPriority $priority): int
    {
        return match ($priority) {
            TicketPriority::Urgent => $this->urgent_resolution_minutes,
            TicketPriority::High => $this->high_resolution_minutes,
            TicketPriority::Medium => $this->medium_resolution_minutes,
            TicketPriority::Low => $this->low_resolution_minutes,
        };
    }

    /**
     * Calculate the deadline timestamp from a start time, respecting business operating hours and holidays if configured.
     */
    public function calculateDueTime(CarbonInterface $from, int $minutes): CarbonInterface
    {
        if (! $this->only_business_hours) {
            return $from->copy()->addMinutes($minutes);
        }

        $tz = $this->timezone ?: 'UTC';
        $current = Carbon::parse($from)->setTimezone($tz);
        $remainingMinutes = $minutes;

        /** @var list<int> $businessDays */
        $businessDays = $this->business_days ?? [1, 2, 3, 4, 5];
        /** @var list<string> $holidays */
        $holidays = $this->holidays ?? [];

        [$startHour, $startMinute] = array_map('intval', explode(':', $this->business_hours_start ?: '09:00'));
        [$endHour, $endMinute] = array_map('intval', explode(':', $this->business_hours_end ?: '17:00'));

        while ($remainingMinutes > 0) {
            $isBusinessDay = in_array($current->dayOfWeekIso, $businessDays, true);
            $isHoliday = in_array($current->format('Y-m-d'), $holidays, true);

            if (! $isBusinessDay || $isHoliday) {
                $current = $current->copy()->addDay()->setTime($startHour, $startMinute, 0);

                continue;
            }

            $dayStart = $current->copy()->setTime($startHour, $startMinute, 0);
            $dayEnd = $current->copy()->setTime($endHour, $endMinute, 0);

            if ($current->isBefore($dayStart)) {
                $current = $dayStart->copy();
            }

            if ($current->isAfter($dayEnd) || $current->equalTo($dayEnd)) {
                $current = $current->copy()->addDay()->setTime($startHour, $startMinute, 0);

                continue;
            }

            $minutesAvailableToday = (int) $current->diffInMinutes($dayEnd, false);
            if ($minutesAvailableToday <= 0) {
                $current = $current->copy()->addDay()->setTime($startHour, $startMinute, 0);

                continue;
            }

            if ($remainingMinutes <= $minutesAvailableToday) {
                $current = $current->copy()->addMinutes($remainingMinutes);
                $remainingMinutes = 0;
            } else {
                $remainingMinutes -= $minutesAvailableToday;
                $current = $current->copy()->addDay()->setTime($startHour, $startMinute, 0);
            }
        }

        return $current->setTimezone(config('app.timezone', 'UTC'));
    }

    /**
     * Default standard SLA configuration preset.
     *
     * @return array<string, mixed>
     */
    public static function defaultPreset(): array
    {
        return [
            'name' => 'Standard Customer Support SLA',
            'description' => 'Default tier SLA with 1h urgent response and 4h high priority response.',
            'is_default' => true,
            'is_active' => true,
            'urgent_first_response_minutes' => 60,
            'urgent_resolution_minutes' => 240,
            'high_first_response_minutes' => 120,
            'high_resolution_minutes' => 480,
            'medium_first_response_minutes' => 240,
            'medium_resolution_minutes' => 1440,
            'low_first_response_minutes' => 480,
            'low_resolution_minutes' => 2880,
        ];
    }
}
