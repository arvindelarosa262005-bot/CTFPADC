<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\User;
use Database\Seeders\CtfChallengesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallengeReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_contestants_only_see_released_challenges_and_cannot_access_hidden_ones(): void
    {
        $this->seed(CtfChallengesSeeder::class);

        $contestant = User::create([
            'username' => 'field_agent',
            'email' => 'agent@example.test',
            'password' => 'secret123',
            'role' => 'contestant',
            'is_active' => true,
        ]);

        $this->actingAs($contestant);

        // By default with CtfChallengesSeeder, Source Trail is active, others are hidden
        $activeChal = Challenge::where('slug', 'source-trail')->firstOrFail();
        $this->assertTrue($activeChal->is_active);

        $hiddenChal = Challenge::where('slug', 'caesar-cipher-intercept')->firstOrFail();
        $this->assertFalse($hiddenChal->is_active);

        // Contestant challenges list contains active challenge, but NOT hidden challenge
        $response = $this->get('/challenges');
        $response->assertOk();
        $response->assertSee('Source Trail');
        $response->assertDontSee("Caesar's Secret Transmission");

        // Visiting active challenge works
        $this->get('/challenges/' . $activeChal->slug)->assertOk();

        // Visiting hidden challenge returns 404
        $this->get('/challenges/' . $hiddenChal->slug)->assertNotFound();

        // Attempting to submit to hidden challenge returns 404
        $this->post('/challenges/' . $hiddenChal->slug . '/submissions', [
            'flag' => 'P4DC{rot_cipher_is_not_encryption_for_secrets}',
        ])->assertNotFound();
    }

    public function test_admin_can_toggle_and_bulk_control_challenge_release(): void
    {
        $this->seed(CtfChallengesSeeder::class);

        $admin = User::create([
            'username' => 'range_commander',
            'email' => 'commander@example.test',
            'password' => 'AdminPass123!',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $chal = Challenge::where('slug', 'caesar-cipher-intercept')->firstOrFail();
        $this->assertFalse($chal->is_active);

        // Toggle from hidden to active (Release)
        $toggleResp = $this->post(route('admin.challenges.toggle', ['challenge' => $chal]));
        $toggleResp->assertRedirect();
        $toggleResp->assertSessionHas('status');

        $chal->refresh();
        $this->assertTrue($chal->is_active);
        $this->assertNotNull($chal->published_at);

        // Bulk hide all
        $hideResp = $this->post(route('admin.challenges.bulk_hide'));
        $hideResp->assertRedirect();
        $this->assertEquals(0, Challenge::where('is_active', true)->count());

        // Bulk release all
        $releaseResp = $this->post(route('admin.challenges.bulk_release'));
        $releaseResp->assertRedirect();
        $this->assertEquals(Challenge::count(), Challenge::where('is_active', true)->count());
    }

    public function test_contestants_cannot_toggle_challenges(): void
    {
        $this->seed(CtfChallengesSeeder::class);

        $contestant = User::create([
            'username' => 'curious_player',
            'email' => 'curious@example.test',
            'password' => 'secret123',
            'role' => 'contestant',
            'is_active' => true,
        ]);

        $this->actingAs($contestant);

        $chal = Challenge::where('slug', 'caesar-cipher-intercept')->firstOrFail();

        $response = $this->post(route('admin.challenges.toggle', ['challenge' => $chal]));
        $response->assertForbidden();
    }
}
