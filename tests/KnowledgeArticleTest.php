<?php

declare(strict_types=1);

namespace Odden\Service\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Odden\Service\Models\KnowledgeArticle;
use Odden\Service\Tests\Fixtures\User;

class KnowledgeArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_and_interact_with_knowledge_articles(): void
    {
        $author = User::factory()->create(['name' => 'Support Lead']);

        $article = KnowledgeArticle::create([
            'title' => 'How to Configure SAML Single Sign-On',
            'slug' => 'configure-saml-sso',
            'category' => 'Authentication',
            'body' => 'Step-by-step instructions for Okta and Google Workspace SSO integration.',
            'is_published' => true,
            'user_id' => $author->id,
        ]);

        $this->assertSame('How to Configure SAML Single Sign-On', $article->title);
        $this->assertSame(0, $article->views_count);
        $this->assertSame(0, $article->helpful_count);
        $this->assertSame(0, $article->not_helpful_count);

        $article->recordView();
        $article->recordView();
        $article->voteHelpful();

        $article->refresh();
        $this->assertSame(2, $article->views_count);
        $this->assertSame(1, $article->helpful_count);
        $this->assertSame(0, $article->not_helpful_count);
    }
}
