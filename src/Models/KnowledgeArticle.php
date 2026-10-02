<?php

declare(strict_types=1);

namespace Odden\Service\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Odden\Core\Support\UserModel;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $category
 * @property string $body
 * @property bool $is_published
 * @property int $views_count
 * @property int $helpful_count
 * @property int $not_helpful_count
 * @property int $deflections_count
 * @property int|null $user_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Model|null $author
 */
class KnowledgeArticle extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'category',
        'body',
        'is_published',
        'views_count',
        'helpful_count',
        'not_helpful_count',
        'deflections_count',
        'user_id',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_published' => true,
        'views_count' => 0,
        'helpful_count' => 0,
        'not_helpful_count' => 0,
        'deflections_count' => 0,
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('odden-service.tables.articles', 'odden_service_articles');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'views_count' => 'integer',
            'helpful_count' => 'integer',
            'not_helpful_count' => 'integer',
            'deflections_count' => 'integer',
        ];
    }

    /**
     * Author / agent who wrote this article.
     *
     * @return BelongsTo<Model, $this>
     */
    public function author(): BelongsTo
    {
        $userModel = UserModel::className();

        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * Increment views.
     */
    public function recordView(): self
    {
        $this->increment('views_count');

        return $this;
    }

    /**
     * Vote as helpful.
     */
    public function voteHelpful(): self
    {
        $this->increment('helpful_count');

        return $this;
    }

    /**
     * Vote as not helpful.
     */
    public function voteNotHelpful(): self
    {
        $this->increment('not_helpful_count');

        return $this;
    }

    /**
     * Record a successful self-service ticket deflection.
     */
    public function recordDeflection(): self
    {
        $this->increment('deflections_count');

        return $this;
    }
}
