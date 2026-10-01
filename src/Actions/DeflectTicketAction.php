<?php

declare(strict_types=1);

namespace Focal\Service\Actions;

use Focal\Service\Models\KnowledgeArticle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DeflectTicketAction
{
    /**
     * Stop words to filter out when tokenizing search queries.
     *
     * @var list<string>
     */
    protected const STOP_WORDS = [
        'the', 'a', 'an', 'and', 'or', 'is', 'in', 'at', 'of', 'on', 'for',
        'to', 'with', 'by', 'from', 'about', 'how', 'do', 'i', 'my', 'we',
        'our', 'you', 'your', 'please', 'help', 'having', 'issue', 'problem',
    ];

    /**
     * Search knowledge articles for deflection suggestions based on subject/message query.
     *
     * @return Collection<int, array{id: int, title: string, slug: string, category: string, excerpt: string, helpful_count: int, deflections_count: int, url: string}>
     */
    public function execute(string $query, int $limit = 5): Collection
    {
        $rawQuery = trim($query);
        if ($rawQuery === '') {
            return collect();
        }

        // Tokenize into keywords
        $words = preg_split('/[\s,\.\?\!\:\;]+/', mb_strtolower($rawQuery));
        /** @var list<string> $terms */
        $terms = collect($words ?: [])
            ->filter(fn (string $w): bool => mb_strlen($w) >= 3 && ! in_array($w, self::STOP_WORDS, true))
            ->values()
            ->all();

        $articleQuery = KnowledgeArticle::query()
            ->where('is_published', true);

        if (! empty($terms)) {
            $articleQuery->where(function (Builder $builder) use ($terms, $rawQuery): void {
                // Exact or full string match in title or category
                $builder->where('title', 'like', "%{$rawQuery}%")
                    ->orWhere('category', 'like', "%{$rawQuery}%");

                // Individual keyword matches
                foreach ($terms as $term) {
                    $builder->orWhere('title', 'like', "%{$term}%")
                        ->orWhere('body', 'like', "%{$term}%");
                }
            });
        } else {
            $articleQuery->where(function (Builder $builder) use ($rawQuery): void {
                $builder->where('title', 'like', "%{$rawQuery}%")
                    ->orWhere('body', 'like', "%{$rawQuery}%");
            });
        }

        return $articleQuery
            ->orderBy('helpful_count', 'desc')
            ->orderBy('views_count', 'desc')
            ->take($limit)
            ->get()
            ->map(fn (KnowledgeArticle $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'category' => $article->category,
                'excerpt' => Str::limit(strip_tags($article->body), 140),
                'helpful_count' => $article->helpful_count,
                'deflections_count' => $article->deflections_count,
                'url' => route('focal.help.show', $article->slug),
            ]);
    }
}
