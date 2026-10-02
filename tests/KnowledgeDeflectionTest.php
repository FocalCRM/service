<?php

declare(strict_types=1);

namespace Focal\Service\Tests;

use Focal\Service\Actions\DeflectTicketAction;
use Focal\Service\Models\KnowledgeArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KnowledgeDeflectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deflect_ticket_action_finds_relevant_published_articles(): void
    {
        $relevantArticle = KnowledgeArticle::create([
            'title' => 'How to Reset Your User Password',
            'slug' => 'how-to-reset-password',
            'category' => 'Account & Security',
            'body' => 'To reset your password, visit the login page and click Forgot Password.',
            'is_published' => true,
            'helpful_count' => 15,
        ]);

        $billingArticle = KnowledgeArticle::create([
            'title' => 'Updating Credit Card and Billing Invoices',
            'slug' => 'updating-credit-card-billing',
            'category' => 'Billing',
            'body' => 'Manage payment methods directly in company billing settings.',
            'is_published' => true,
            'helpful_count' => 5,
        ]);

        $unpublishedArticle = KnowledgeArticle::create([
            'title' => 'Internal Password Reset Runbook',
            'slug' => 'internal-password-reset-runbook',
            'category' => 'Internal',
            'body' => 'Steps for IT staff to manually rotate credentials.',
            'is_published' => false,
            'helpful_count' => 50,
        ]);

        $action = new DeflectTicketAction;
        $results = $action->execute('I forgot my password and cannot sign in');

        $this->assertNotEmpty($results);
        $this->assertSame($relevantArticle->id, $results->first()['id']);
        $this->assertFalse($results->contains('id', $unpublishedArticle->id));
    }

    public function test_knowledge_suggest_endpoint_returns_json(): void
    {
        KnowledgeArticle::create([
            'title' => 'Configuring SMTP and Custom Domain Email',
            'slug' => 'configuring-smtp-email',
            'category' => 'Email Settings',
            'body' => 'Enter your SPF and DKIM DNS records to authenticate outgoing email.',
            'is_published' => true,
            'helpful_count' => 10,
        ]);

        $response = $this->getJson(route('focal.service.knowledge.suggest', ['q' => 'SMTP DNS configuration']));

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonFragment([
                'title' => 'Configuring SMTP and Custom Domain Email',
            ]);
    }

    public function test_knowledge_deflect_endpoint_increments_article_deflections(): void
    {
        $article = KnowledgeArticle::create([
            'title' => 'Connecting Your Slack Workspace',
            'slug' => 'connecting-slack-workspace',
            'category' => 'Integrations',
            'body' => 'Install the Focal Slack app from the integrations directory.',
            'is_published' => true,
            'deflections_count' => 2,
        ]);

        $response = $this->postJson(route('focal.service.knowledge.deflect'), [
            'article_id' => $article->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('deflections_count', 3);

        $article->refresh();
        $this->assertSame(3, $article->deflections_count);
    }

    public function test_knowledge_suggest_endpoint_encodes_article_url_slug(): void
    {
        KnowledgeArticle::create([
            'title' => 'Webhook signature guide',
            'slug' => 'webhook"><img src=x onerror=alert(1)>',
            'category' => 'Developers',
            'body' => 'Verify webhook signatures.',
            'is_published' => true,
        ]);

        $response = $this->getJson(route('focal.service.knowledge.suggest', ['q' => 'webhook']));

        $url = (string) $response->assertOk()->json('data.0.url');
        $this->assertStringNotContainsString('"', $url);
        $this->assertStringNotContainsString('<', $url);
        $this->assertStringNotContainsString(' ', $url);
        $this->assertStringContainsString('/help/webhook%22%3E%3Cimg%20src=x', $url);
    }
}
