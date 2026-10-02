<?php

declare(strict_types=1);

namespace Focal\Service\Tests;

use Focal\Core\Models\Contact;
use Focal\Service\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Issue #15: one customer is one contact, whatever the case or surrounding whitespace of the
 * email address they type or send from.
 */
class ContactEmailLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_submission_reuses_a_contact_with_a_differently_cased_email(): void
    {
        $existing = Contact::factory()->create(['email' => 'alex@client.test']);

        $this->post('/support', $this->portalPayload('Alex@Client.TEST'))->assertSessionHasNoErrors();

        $this->assertSame(1, Contact::query()->count());
        $this->assertSame($existing->id, Ticket::query()->sole()->contact_id);
    }

    public function test_portal_submission_matches_a_legacy_mixed_case_contact(): void
    {
        $existing = Contact::factory()->create(['email' => 'Legacy.User@Client.test']);

        $this->post('/support', $this->portalPayload('legacy.user@client.test'))->assertSessionHasNoErrors();

        $this->assertSame(1, Contact::query()->count());
        $this->assertSame($existing->id, Ticket::query()->sole()->contact_id);
    }

    public function test_portal_submission_stores_new_contacts_lowercased(): void
    {
        $this->post('/support', $this->portalPayload('New.Person@Client.TEST'))->assertSessionHasNoErrors();

        $this->assertSame('new.person@client.test', Contact::query()->sole()->email);
    }

    public function test_inbound_email_reuses_a_contact_with_a_differently_cased_email(): void
    {
        $existing = Contact::factory()->create(['email' => 'dana@client.test']);

        $this->postJson('/api/service/inbound-email', [
            'from' => 'Dana <DANA@Client.test>',
            'subject' => 'Help',
            'body' => 'Please help.',
        ])->assertCreated();

        $this->assertSame(1, Contact::query()->count());
        $this->assertSame($existing->id, Ticket::query()->sole()->contact_id);
    }

    public function test_inbound_email_stores_new_contacts_lowercased_and_trimmed(): void
    {
        $this->postJson('/api/service/inbound-email', [
            'from' => ' Robin.Q@Client.TEST ',
            'subject' => 'Help',
            'body' => 'Please help.',
        ])->assertCreated();

        $this->assertSame('robin.q@client.test', Contact::query()->sole()->email);
    }

    public function test_chat_stores_new_contacts_lowercased_and_trimmed(): void
    {
        $this->postJson(route('focal.service.chat.start'), [
            'name' => 'Kim Lee',
            'email' => 'Kim.Lee@Client.TEST',
            'message' => 'Hi',
        ])->assertCreated();

        $this->assertSame('kim.lee@client.test', Contact::query()->sole()->email);
    }

    /**
     * @return array<string, string>
     */
    private function portalPayload(string $email): array
    {
        return [
            'name' => 'Alex Smith',
            'email' => $email,
            'subject' => 'Cannot log in',
            'priority' => 'medium',
            'description' => 'Locked out since this morning.',
        ];
    }
}
