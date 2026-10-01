<?php

declare(strict_types=1);

namespace Focal\Service\Database\Seeders;

use Focal\Core\Models\Company;
use Focal\Core\Models\Contact;
use Focal\Core\Support\UserModel;
use Focal\Service\Enums\MessageSenderType;
use Focal\Service\Enums\TicketPriority;
use Focal\Service\Enums\TicketSource;
use Focal\Service\Enums\TicketStatus;
use Focal\Service\Models\CannedResponse;
use Focal\Service\Models\KnowledgeArticle;
use Focal\Service\Models\SlaPolicy;
use Focal\Service\Models\Ticket;
use Focal\Service\Models\TicketRoutingRule;
use Illuminate\Database\Seeder;

class ServiceDatabaseSeeder extends Seeder
{
    /**
     * Seed customer service, help desk, and knowledge base demo data.
     */
    public function run(): void
    {
        // 1. Support Agents
        $admin = UserModel::query()->firstOrCreate(
            ['email' => 'admin@focal.test'],
            ['name' => 'Focal Admin', 'password' => bcrypt('password')]
        );

        $agentAlex = UserModel::query()->firstOrCreate(
            ['email' => 'alex.mercer@focal.test'],
            ['name' => 'Alex Mercer', 'password' => bcrypt('password')]
        );

        $agentBeth = UserModel::query()->firstOrCreate(
            ['email' => 'beth.caldwell@focal.test'],
            ['name' => 'Beth Caldwell', 'password' => bcrypt('password')]
        );

        // 2. SLA Policies
        $standardSla = SlaPolicy::firstOrCreate(
            ['name' => 'Standard Customer Support SLA'],
            SlaPolicy::defaultPreset()
        );

        $enterpriseSla = SlaPolicy::firstOrCreate(
            ['name' => 'Enterprise 24/7 Mission-Critical SLA'],
            [
                'description' => 'Fast-track SLA for tier 1 enterprise clients with 15m urgent response guarantee.',
                'is_default' => false,
                'is_active' => true,
                'urgent_first_response_minutes' => 15,
                'urgent_resolution_minutes' => 60,
                'high_first_response_minutes' => 30,
                'high_resolution_minutes' => 120,
                'medium_first_response_minutes' => 60,
                'medium_resolution_minutes' => 240,
                'low_first_response_minutes' => 120,
                'low_resolution_minutes' => 480,
            ]
        );

        // 3. Knowledge Base Articles
        $articles = [
            [
                'title' => 'Configuring Google Workspace & Okta SAML 2.0 Single Sign-On',
                'slug' => 'configuring-saml-sso',
                'category' => 'Authentication',
                'body' => "## Single Sign-On Configuration Guide\n\nFocal supports standards-compliant SAML 2.0 identity providers.\n\n### Step 1: Obtain IdP Metadata\n1. In your Identity Provider (Okta, Google Workspace, Azure AD), create a new SAML App.\n2. Set ACS URL to `https://app.focal.test/auth/saml/callback`.\n3. Download the XML Metadata file.\n\n### Step 2: Configure Focal Settings\nUpload your Identity Provider metadata in **Settings -> Security -> SAML 2.0**.",
                'is_published' => true,
                'views_count' => 142,
                'helpful_count' => 28,
                'not_helpful_count' => 1,
                'user_id' => $agentAlex->getKey(),
            ],
            [
                'title' => 'Understanding Your Invoices and Seat Tier Breakdown',
                'slug' => 'invoicing-seat-tiers',
                'category' => 'Billing',
                'body' => "## Billing & Seat Allocation\n\nFocal charges based on assigned user seats. Active reps who have access to Deals and Cockpit count towards your licensed seat threshold.\n\n- Invoices generate on the 1st of each calendar month.\n- Prorated seat additions are calculated automatically upon user invitation.",
                'is_published' => true,
                'views_count' => 89,
                'helpful_count' => 15,
                'not_helpful_count' => 0,
                'user_id' => $admin->getKey(),
            ],
            [
                'title' => 'Setting Up Inbound Webhooks & HMAC Signature Verification',
                'slug' => 'webhooks-signature-verification',
                'category' => 'API & Integrations',
                'body' => "## Webhook Endpoints\n\nAll outbound webhooks from Focal include a `X-Focal-Signature` header computed with HMAC-SHA256.\n\n```python\n# Python verification snippet\nimport hmac, hashlib\nexpected = hmac.new(webhook_secret, payload, hashlib.sha256).hexdigest()\n```",
                'is_published' => true,
                'views_count' => 210,
                'helpful_count' => 45,
                'not_helpful_count' => 2,
                'user_id' => $agentAlex->getKey(),
            ],
            [
                'title' => 'Managing Team Permissions and Role-Based Access Control',
                'slug' => 'team-permissions-rbac',
                'category' => 'Administration',
                'body' => "## RBAC Overview\n\nFocal provides granular role definitions across Sales Reps, Support Specialists, and Global Admins. Team Scoping restricts pipeline visibility to your regional business unit.",
                'is_published' => true,
                'views_count' => 64,
                'helpful_count' => 11,
                'not_helpful_count' => 0,
                'user_id' => $agentBeth->getKey(),
            ],
        ];

        foreach ($articles as $art) {
            KnowledgeArticle::firstOrCreate(['slug' => $art['slug']], $art);
        }

        // 4. Canned Responses / Macros
        $cannedSnippets = [
            [
                'title' => 'Request Diagnostics & Browser Info',
                'shortcut' => '!moreinfo',
                'category' => 'Troubleshooting',
                'content' => "Hi there,\n\nThank you for reaching out to Focal Support. To help us reproduce and resolve this quickly, could you please provide:\n1. Your browser name and version\n2. A screenshot or screen recording of the error\n3. The exact URL where the issue occurred\n\nThank you!\nSupport Team",
                'user_id' => $agentAlex->getKey(),
                'is_shared' => true,
            ],
            [
                'title' => 'Issue Resolved Confirmation',
                'shortcut' => '!resolved',
                'category' => 'Resolution',
                'content' => "Hi there,\n\nWe have verified that this issue has been resolved. Please refresh your browser and test again.\n\nIf you experience any further trouble, simply reply to this email to reopen your ticket.\n\nBest regards,\nFocal Support",
                'user_id' => $agentAlex->getKey(),
                'is_shared' => true,
            ],
            [
                'title' => 'Escalation to Engineering',
                'shortcut' => '!escalate',
                'category' => 'Escalation',
                'content' => "Hello,\n\nWe have verified this behavior and escalated your ticket to our senior infrastructure engineering team. We are actively investigating and will share an update within 2 hours.\n\nThank you for your patience!",
                'user_id' => $agentBeth->getKey(),
                'is_shared' => true,
            ],
        ];

        foreach ($cannedSnippets as $cs) {
            CannedResponse::firstOrCreate(['shortcut' => $cs['shortcut']], $cs);
        }

        // 5. Realistic Support Tickets
        $contacts = Contact::take(4)->get();
        $companies = Company::take(4)->get();

        $firstContact = $contacts->first();
        $secondContact = $contacts->skip(1)->first();
        $thirdContact = $contacts->skip(2)->first();
        $firstCompany = $companies->first();
        $secondCompany = $companies->skip(1)->first();

        // Ticket 1: Urgent - Database Timeout (New, approaching SLA deadline)
        $ticket1 = Ticket::firstOrCreate(
            ['ticket_number' => 'TICK-2026-0001'],
            [
                'subject' => 'Production Database Timeout during Bulk Data Sync',
                'description' => 'Our hourly scheduled export script returned 504 Gateway Timeout on /api/v1/deals.',
                'status' => TicketStatus::New,
                'priority' => TicketPriority::Urgent,
                'source' => TicketSource::Api,
                'contact_id' => $firstContact?->id,
                'company_id' => $firstCompany?->id,
                'owner_id' => $agentAlex->getKey(),
                'sla_policy_id' => $enterpriseSla->id,
                'first_response_due_at' => now()->addMinutes(12),
                'resolution_due_at' => now()->addMinutes(48),
            ]
        );

        if ($ticket1->wasRecentlyCreated) {
            $ticket1->messages()->create([
                'body' => 'Our hourly scheduled export script returned 504 Gateway Timeout on /api/v1/deals. This is blocking our daily reporting run.',
                'sender_type' => MessageSenderType::Customer->value,
                'contact_id' => $firstContact?->id,
                'is_internal_note' => false,
            ]);
        }

        // Ticket 2: High - SAML SSO Redirect Loop (Open with back-and-forth messages)
        $ticket2 = Ticket::firstOrCreate(
            ['ticket_number' => 'TICK-2026-0002'],
            [
                'subject' => 'SAML 2.0 SSO Redirect Loop on Login',
                'description' => 'Users get stuck looping between Okta authentication and the Focal login screen.',
                'status' => TicketStatus::Open,
                'priority' => TicketPriority::High,
                'source' => TicketSource::Email,
                'contact_id' => $secondContact?->id,
                'company_id' => $secondCompany?->id,
                'owner_id' => $agentBeth->getKey(),
                'sla_policy_id' => $standardSla->id,
                'first_response_due_at' => now()->subHours(2),
                'first_responded_at' => now()->subHours(2)->addMinutes(15),
                'resolution_due_at' => now()->addHours(6),
            ]
        );

        if ($ticket2->wasRecentlyCreated) {
            $ticket2->messages()->create([
                'body' => 'Our team members cannot log in via Okta. They are redirected back to the login screen after entering 2FA.',
                'sender_type' => MessageSenderType::Customer->value,
                'contact_id' => $secondContact?->id,
                'is_internal_note' => false,
            ]);

            $ticket2->messages()->create([
                'body' => 'Checked tenant Okta logs; it looks like the entity ID was mismatched after domain renewal.',
                'sender_type' => MessageSenderType::Agent->value,
                'user_id' => $agentBeth->getKey(),
                'is_internal_note' => true,
            ]);

            $ticket2->messages()->create([
                'body' => 'Hi, thanks for reaching out. Could you please check if your Okta Application entity ID is configured with `https://app.focal.test`?',
                'sender_type' => MessageSenderType::Agent->value,
                'user_id' => $agentBeth->getKey(),
                'is_internal_note' => false,
            ]);

            $ticket2->messages()->create([
                'body' => 'We updated the entity ID in Okta, but we now see an invalid certificate signature error.',
                'sender_type' => MessageSenderType::Customer->value,
                'contact_id' => $secondContact?->id,
                'is_internal_note' => false,
            ]);
        }

        // Ticket 3: Medium - Invoice Breakdown (Waiting on Customer)
        $ticket3 = Ticket::firstOrCreate(
            ['ticket_number' => 'TICK-2026-0003'],
            [
                'subject' => 'Request for Tax Exemption Certificate Application',
                'description' => 'Please apply our 501(c)(3) sales tax exemption certificate to our annual invoice.',
                'status' => TicketStatus::WaitingOnCustomer,
                'priority' => TicketPriority::Medium,
                'source' => TicketSource::WebPortal,
                'contact_id' => $thirdContact?->id,
                'company_id' => $firstCompany?->id,
                'owner_id' => $agentAlex->getKey(),
                'sla_policy_id' => $standardSla->id,
                'first_response_due_at' => now()->subHours(4),
                'first_responded_at' => now()->subHours(3),
                'resolution_due_at' => now()->addHours(20),
            ]
        );

        if ($ticket3->wasRecentlyCreated) {
            $ticket3->messages()->create([
                'body' => 'Please apply our 501(c)(3) tax exemption certificate to invoice #INV-2026-09.',
                'sender_type' => MessageSenderType::Customer->value,
                'contact_id' => $thirdContact?->id,
                'is_internal_note' => false,
            ]);

            $ticket3->messages()->create([
                'body' => 'Hi, we received your request! Could you please attach a PDF copy of your state-issued exemption letter for our records?',
                'sender_type' => MessageSenderType::Agent->value,
                'user_id' => $agentAlex->getKey(),
                'is_internal_note' => false,
            ]);
        }

        // Ticket 4: Low - Dark Mode Theme (Resolved with 5-star CSAT)
        $ticket4 = Ticket::firstOrCreate(
            ['ticket_number' => 'TICK-2026-0004'],
            [
                'subject' => 'Dark Mode Theme option in User Preferences',
                'description' => 'Is there a setting for dark mode in the new interface?',
                'status' => TicketStatus::Resolved,
                'priority' => TicketPriority::Low,
                'source' => TicketSource::WebPortal,
                'contact_id' => $firstContact?->id,
                'company_id' => $firstCompany?->id,
                'owner_id' => $agentAlex->getKey(),
                'sla_policy_id' => $standardSla->id,
                'first_response_due_at' => now()->subDays(2),
                'first_responded_at' => now()->subDays(2)->addHours(1),
                'resolution_due_at' => now()->subDay(),
                'resolved_at' => now()->subDay()->subHours(2),
                'csat_rating' => 5,
                'csat_comment' => 'Alex explained where the toggle was immediately. Incredible support!',
            ]
        );

        if ($ticket4->wasRecentlyCreated) {
            $ticket4->messages()->create([
                'body' => 'Is there a setting for dark mode in the user preferences?',
                'sender_type' => MessageSenderType::Customer->value,
                'contact_id' => $firstContact?->id,
                'is_internal_note' => false,
            ]);

            $ticket4->messages()->create([
                'body' => 'Hi! Yes, you can click on your avatar in the top-right corner and select "Theme -> Dark" or set it to match your system preferences.',
                'sender_type' => MessageSenderType::Agent->value,
                'user_id' => $agentAlex->getKey(),
                'is_internal_note' => false,
            ]);
        }

        // Ticket 5: Urgent - SLA Response Breached
        $ticket5 = Ticket::firstOrCreate(
            ['ticket_number' => 'TICK-2026-0005'],
            [
                'subject' => 'Critical: Webhook Delivery Failing with Connection Refused',
                'description' => 'Outbound webhook dispatcher has not delivered events for 3 hours.',
                'status' => TicketStatus::New,
                'priority' => TicketPriority::Urgent,
                'source' => TicketSource::Api,
                'contact_id' => $secondContact?->id,
                'company_id' => $secondCompany?->id,
                'owner_id' => $agentAlex->getKey(),
                'sla_policy_id' => $standardSla->id,
                'first_response_due_at' => now()->subHours(2),
                'resolution_due_at' => now()->addHours(2),
                'is_sla_response_breached' => true,
            ]
        );

        if ($ticket5->wasRecentlyCreated) {
            $ticket5->messages()->create([
                'body' => 'Our webhook endpoint at `https://api.initech.test/webhooks` has stopped receiving Deal Won events.',
                'sender_type' => MessageSenderType::Customer->value,
                'contact_id' => $secondContact?->id,
                'is_internal_note' => false,
            ]);
        }

        // 6. Ticket Routing Rules
        TicketRoutingRule::firstOrCreate(
            ['name' => 'Critical Incidents Auto-Escalation'],
            [
                'is_active' => true,
                'sort_order' => 1,
                'criteria' => ['priority' => 'urgent'],
                'assigned_user_ids' => [$agentAlex->getKey(), $admin->getKey()],
            ]
        );

        TicketRoutingRule::firstOrCreate(
            ['name' => 'Billing & Invoice Inquiries'],
            [
                'is_active' => true,
                'sort_order' => 2,
                'criteria' => ['keyword' => 'invoice'],
                'assigned_user_ids' => [$agentBeth->getKey()],
            ]
        );

        TicketRoutingRule::firstOrCreate(
            ['name' => 'General Inbound Support Round-Robin'],
            [
                'is_active' => true,
                'sort_order' => 10,
                'criteria' => [],
                'assigned_user_ids' => [$agentAlex->getKey(), $agentBeth->getKey()],
            ]
        );
    }
}
