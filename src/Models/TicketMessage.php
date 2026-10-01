<?php

declare(strict_types=1);

namespace Focal\Service\Models;

use Carbon\CarbonInterface;
use Focal\Core\Models\Contact;
use Focal\Core\Support\UserModel;
use Focal\Service\Enums\MessageSenderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ticket_id
 * @property MessageSenderType $sender_type
 * @property int|null $user_id
 * @property int|null $contact_id
 * @property string $body
 * @property bool $is_internal_note
 * @property array<int, mixed>|null $attachments
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Ticket $ticket
 * @property-read Model|null $user
 * @property-read Contact|null $contact
 */
class TicketMessage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'sender_type',
        'user_id',
        'contact_id',
        'body',
        'is_internal_note',
        'attachments',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('focal-service.tables.messages', 'focal_service_ticket_messages');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sender_type' => MessageSenderType::class,
            'is_internal_note' => 'boolean',
            'attachments' => 'array',
        ];
    }

    /**
     * The ticket this message belongs to.
     *
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /**
     * The user / agent who sent this message.
     *
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        $userModel = UserModel::className();

        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * The contact / customer who sent this message.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * Human-readable sender display name.
     */
    public function senderName(): string
    {
        if ($this->user !== null) {
            return UserModel::displayName($this->user);
        }

        if ($this->contact !== null) {
            return $this->contact->full_name;
        }

        return $this->sender_type->label();
    }
}
