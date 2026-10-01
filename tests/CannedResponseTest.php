<?php

declare(strict_types=1);

namespace Focal\Service\Tests;

use App\Models\User;
use Focal\Service\Models\CannedResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CannedResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_and_manage_canned_responses(): void
    {
        $agent = User::factory()->create();

        $canned = CannedResponse::create([
            'title' => 'Request More Information',
            'shortcut' => '!moreinfo',
            'category' => 'Troubleshooting',
            'content' => 'Could you please share your browser version and relevant screenshots?',
            'user_id' => $agent->id,
            'is_shared' => true,
        ]);

        $this->assertSame('!moreinfo', $canned->shortcut);
        $this->assertTrue($canned->is_shared);
        $this->assertSame($agent->id, $canned->user_id);
    }
}
