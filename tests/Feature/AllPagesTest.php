<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\SourceTrailChallengeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_access_auth_pages_and_is_redirected_from_protected_pages(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
        $this->get('/register')->assertOk()->assertSee('Create your account');
        $this->get('/auth/google')->assertRedirect();

        $this->get('/')->assertRedirect(route('login'));
        $this->get('/challenges')->assertRedirect(route('login'));
        $this->get('/teams')->assertRedirect(route('login'));
        $this->get('/teams/join')->assertRedirect(route('login'));
        $this->get('/profile')->assertRedirect(route('login'));
        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/admin/challenges')->assertRedirect(route('login'));
    }

    public function test_contestant_can_access_all_contestant_pages_and_views_render_without_errors(): void
    {
        $this->seed(SourceTrailChallengeSeeder::class);

        $contestant = User::create([
            'username' => 'cyber_player',
            'email' => 'player@example.test',
            'password' => 'password123',
            'role' => 'contestant',
            'is_active' => true,
        ]);

        $this->actingAs($contestant);

        // Dashboard
        $this->get('/')->assertOk()->assertSee('cyber_player');

        // Challenges
        $this->get('/challenges')->assertOk()->assertSee('Source Trail');
        $this->get('/challenges/')->assertOk()->assertSee('Source Trail');
        $this->get('/challenges/source-trail')->assertOk()->assertSee('Source Trail');
        $this->get('/challenges/source-trail/')->assertOk()->assertSee('Source Trail');
        $this->get('/challenges/source-trail/target')->assertOk();

        // Teams
        $this->get('/teams')->assertOk()->assertSee('Create a team');
        $this->get('/teams/join')->assertOk()->assertSee('Join a team');

        // Create a team and visit its page
        $this->post('/teams', ['name' => 'Elite Squad'])->assertRedirect();
        $team = Team::where('name', 'Elite Squad')->firstOrFail();
        $this->get('/teams/' . $team->id)->assertOk()->assertSee('Elite Squad');

        // Profile
        $this->get('/profile')->assertOk()->assertSee('cyber_player');

        // Contestant cannot access admin
        $this->get('/admin')->assertForbidden();
        $this->get('/admin/challenges')->assertForbidden();
        $this->get('/admin/challenges/create')->assertForbidden();
    }

    public function test_admin_can_access_all_admin_pages_and_views_render_without_errors(): void
    {
        $this->seed(SourceTrailChallengeSeeder::class);

        $admin = User::create([
            'username' => 'cyber_admin',
            'email' => 'admin@example.test',
            'password' => 'adminpassword',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        // Admin Dashboard
        $this->get('/admin')->assertOk()->assertSee('Control room');

        // Admin Challenges
        $this->get('/admin/challenges')->assertOk()->assertSee('Source Trail');
        $this->get('/admin/challenges/create')->assertOk()->assertSee('Create challenge');
        $this->get('/admin/challenges/source-trail/edit')->assertOk()->assertSee('Source Trail');

        // Admin profile, home dashboard (redirects to admin console) & target
        $this->get('/profile')->assertOk()->assertSee('cyber_admin');
        $this->get('/')->assertRedirect(route('admin.dashboard'));
        $this->get('/challenges')->assertOk()->assertSee('Source Trail');
        $this->get('/challenges/source-trail')->assertOk()->assertSee('Source Trail');
        $this->get('/challenges/source-trail/target')->assertOk();
    }
}
