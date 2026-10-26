<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Team;
use App\Models\User;
use App\Models\VerifiedSolve;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealtimeLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_set_and_update_callsign_nickname(): void
    {
        $user = User::create([
            'username' => 'agent_smith',
            'email' => 'smith@matrix.test',
            'password' => 'secretpass123',
            'role' => 'contestant',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Your profile');

        // Update nickname
        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'nickname' => 'krypt',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('krypt', $user->fresh()->nickname);
        $this->assertSame('krypt', $user->fresh()->callsign);

        // View profile shows updated callsign
        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('krypt')
            ->assertSee('@agent_smith');
    }

    public function test_admin_can_access_live_scores_endpoint(): void
    {
        $admin = User::create([
            'username' => 'cyber-admin',
            'email' => 'admin@cyber.test',
            'password' => 'adminpass123',
            'role' => 'admin',
        ]);

        $contestant = User::create([
            'username' => 'neo_matrix',
            'nickname' => 'cyber',
            'email' => 'neo@matrix.test',
            'password' => 'neopass123',
            'role' => 'contestant',
        ]);

        $challenge = Challenge::create([
            'title' => 'Mainframe Bypass',
            'slug' => 'mainframe-bypass',
            'category' => 'web',
            'difficulty' => 'intermediate',
            'points' => 250,
            'flag' => 'P4DC{mainframe_breached}',
            'is_active' => true,
            'published_at' => now(),
        ]);

        // Record a verified solve
        VerifiedSolve::create([
            'challenge_id' => $challenge->id,
            'user_id' => $contestant->id,
            'points_awarded' => 250,
            'verified_at' => now(),
        ]);

        // Non-admin cannot access
        $this->actingAs($contestant)
            ->get(route('admin.scores.live'))
            ->assertForbidden();

        // Admin can access and receives live telemetry JSON
        $response = $this->actingAs($admin)
            ->getJson(route('admin.scores.live'));

        $response->assertOk();
        $response->assertJsonStructure([
            'teams',
            'individuals',
            'recent_solves',
            'stats' => ['total_points', 'total_flags', 'registered_users', 'existing_teams'],
            'timestamp',
        ]);

        $data = $response->json();
        $this->assertSame(250, $data['stats']['total_points']);
        $this->assertSame(1, $data['stats']['total_flags']);
        $this->assertNotEmpty($data['individuals']);
        $this->assertSame('cyber', $data['individuals'][0]['callsign']);
        $this->assertSame('neo_matrix', $data['individuals'][0]['username']);
        $this->assertSame(250, $data['individuals'][0]['points']);
        $this->assertSame(1, $data['individuals'][0]['solves_count']);
    }
}
