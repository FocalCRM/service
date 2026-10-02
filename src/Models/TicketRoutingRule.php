<?php

declare(strict_types=1);

namespace Odden\Service\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property int $sort_order
 * @property array<string, mixed>|null $criteria
 * @property list<int> $assigned_user_ids
 * @property int $last_assigned_index
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class TicketRoutingRule extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'is_active',
        'sort_order',
        'criteria',
        'assigned_user_ids',
        'last_assigned_index',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('odden-service.tables.routing_rules', 'odden_service_routing_rules');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'criteria' => 'array',
            'assigned_user_ids' => 'array',
            'last_assigned_index' => 'integer',
        ];
    }
}
