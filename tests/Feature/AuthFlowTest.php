<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_and_authenticates_user_then_opens_dashboard(): void
    {
        $response = $this->post(route('register.store'), [
            'username' => 'NewOperative',
            'email' => 'New.Operative@example.test',
            'password' => 'a-long-password-123',
            'password_confirmation' => 'a-long-password-123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'username' => 'newoperative',
            'email' => 'new.operative@example.test',
        ]);
        $this->assertNotSame('a-long-password-123', User::firstOrFail()->password);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('newoperative')
            ->assertSee('Your P4DC account is ready.');
    }

    public function test_duplicate_username_and_email_are_rejected_case_insensitively(): void
    {
        User::create([
            'username' => 'operative',
            'email' => 'operative@example.test',
            'password' => 'existing-long-password',
        ]);

        $this->from(route('register'))
            ->post(route('register.store'), [
                'username' => 'OPERATIVE',
                'email' => 'different@example.test',
                'password' => 'another-long-password',
                'password_confirmation' => 'another-long-password',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('username');

        $this->from(route('register'))
            ->post(route('register.store'), [
                'username' => 'different',
                'email' => 'OPERATIVE@example.test',
                'password' => 'another-long-password',
                'password_confirmation' => 'another-long-password',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('email');
    }

    public function test_invalid_registration_fields_are_rejected(): void
    {
        $this->post(route('register.store'), [
            'username' => 'x',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'does-not-match',
        ])->assertSessionHasErrors(['username', 'email', 'password']);
    }

    public function test_registration_rejects_non_string_identity_fields(): void
    {
        $this->post(route('register.store'), [
            'username' => ['unexpected'],
            'email' => ['unexpected'],
            'password' => 'a-long-password-123',
            'password_confirmation' => 'a-long-password-123',
        ])->assertSessionHasErrors(['username', 'email']);
    }

    public function test_pipe_username_and_eight_character_password_work_for_registration_and_login(): void
    {
        $this->post(route('register.store'), [
            'username' => 'P4DC|lorenz',
            'email' => 'pipe@example.test',
            'password' => 'p|ssword',
            'password_confirmation' => 'p|ssword',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('username', 'p4dc|lorenz')->firstOrFail();
        $this->assertSame('contestant', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk()->assertSee('p4dc|lorenz');

        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'credential' => 'P4DC|lorenz',
            'password' => 'p|ssword',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_cannot_assign_admin_role_or_inactive_status(): void
    {
        $this->post(route('register.store'), [
            'username' => 'ordinary-user',
            'email' => 'ordinary@example.test',
            'password' => 'eight888',
            'password_confirmation' => 'eight888',
            'role' => 'admin',
            'is_active' => false,
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'username' => 'ordinary-user',
            'role' => 'contestant',
            'is_active' => true,
        ]);
    }

    public function test_passwords_shorter_than_eight_characters_are_rejected_with_clear_message(): void
    {
        $this->from(route('register'))
            ->post(route('register.store'), [
                'username' => 'shortpass',
                'email' => 'short@example.test',
                'password' => '1234567',
                'password_confirmation' => '1234567',
            ])
            ->assertSessionHasErrors([
                'password' => 'Password must be at least 8 characters long.',
            ]);
    }

    public function test_login_accepts_username_or_email_and_logout_ends_session(): void
    {
        $user = User::create([
            'username' => 'operative',
            'email' => 'operative@example.test',
            'password' => 'long-login-password',
        ]);

        $this->post(route('login.store'), [
            'credential' => 'OPERATIVE',
            'password' => 'long-login-password',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'credential' => 'OPERATIVE@EXAMPLE.TEST',
            'password' => 'long-login-password',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_login_uses_a_generic_error(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'credential' => 'missing@example.test',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['credential' => 'These credentials do not match our records.']);

        $this->assertGuest();
    }

    public function test_contestant_routes_are_protected_and_rankings_are_not_public(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('challenges'))->assertRedirect(route('login'));
        $this->get(route('profile'))->assertRedirect(route('login'));
        $this->get('/leaderboard')->assertNotFound();
    }

    public function test_contestant_cannot_access_admin_and_never_sees_admin_scoring(): void
    {
        $user = User::create([
            'username' => 'contestant',
            'email' => 'contestant@example.test',
            'password' => 'long-login-password',
        ]);
        $this->assertSame('contestant', $user->role);
        $this->assertTrue($user->is_active);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('POINTS EARNED')
            ->assertDontSee('Individual Top Scorers')
            ->assertDontSee('Team Scorers')
            ->assertDontSee('Leaderboard');
    }

    public function test_admin_sees_placeholder_card_and_empty_verified_leaderboards(): void
    {
        $admin = User::create([
            'username' => 'range-admin',
            'email' => 'admin@example.test',
            'password' => 'long-admin-password',
            'role' => 'admin',
        ]);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->is_active);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['POINTS EARNED', '000', 'Ready to be earned'])
            ->assertSee('Individual Top Scorers')
            ->assertSee('Team Scorers')
            ->assertSee('No scores available yet')
            ->assertSee('PLACEHOLDER · VERIFIED SCORING NOT ACTIVE');
    }

    public function test_admin_can_manage_roles_status_and_team_memberships_with_audit_records(): void
    {
        $admin = User::create([
            'username' => 'range-admin',
            'email' => 'admin@example.test',
            'password' => 'long-admin-password',
            'role' => 'admin',
        ]);
        $user = User::create([
            'username' => 'member-user',
            'email' => 'member@example.test',
            'password' => 'long-member-password',
        ]);
        $captain = User::create([
            'username' => 'team-captain',
            'email' => 'team-captain@example.test',
            'password' => 'long-captain-password',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.teams.store'), ['name' => 'Blue Cell', 'captain_id' => $captain->id])
            ->assertRedirect();
        $teamId = \App\Models\Team::where('name', 'Blue Cell')->value('id');

        $this->patch(route('admin.users.team', $user), ['team_id' => $teamId])->assertRedirect();
        $this->patch(route('admin.users.role', $user), ['role' => 'admin'])->assertRedirect();
        $this->patch(route('admin.users.status', $user), ['is_active' => '0'])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'admin', 'is_active' => false]);
        $this->assertDatabaseHas('team_memberships', ['team_id' => $teamId, 'user_id' => $user->id]);
        $this->assertDatabaseCount('admin_audit_logs', 4);

        $this->patch(route('admin.users.role', $admin), ['role' => 'contestant'])->assertForbidden();
    }

    public function test_inactive_accounts_cannot_access_protected_pages(): void
    {
        $user = User::create([
            'username' => 'paused-user',
            'email' => 'paused@example.test',
            'password' => 'long-login-password',
            'is_active' => false,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
        auth()->logout();
        $this->post(route('login.store'), [
            'credential' => 'paused-user',
            'password' => 'long-login-password',
        ])->assertSessionHasErrors('credential');
    }

    public function test_individual_and_team_rankings_use_verified_solves_only(): void
    {
        $admin = User::create([
            'username' => 'range-admin',
            'email' => 'admin@example.test',
            'password' => 'long-admin-password',
            'role' => 'admin',
        ]);
        $user = User::create([
            'username' => 'verified-player',
            'email' => 'player@example.test',
            'password' => 'long-player-password',
        ]);
        $team = \App\Models\Team::create(['name' => 'Verified Team']);
        $challenge = \App\Models\Challenge::create([
            'title' => 'Foundations',
            'slug' => 'foundations',
            'category' => 'miscellaneous',
            'difficulty' => 'beginner',
            'points' => 50,
        ]);
        $user->verifiedSolves()->create([
            'challenge_id' => $challenge->id,
            'team_id_at_solve' => $team->id,
            'points_awarded' => 50,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('verified-player')
            ->assertSee('Verified Team')
            ->assertSee('50');

        $this->expectException(\Illuminate\Database\QueryException::class);
        $user->verifiedSolves()->create([
            'challenge_id' => $challenge->id,
            'points_awarded' => 50,
        ]);
    }
}
