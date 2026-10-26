<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Challenge;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\Models\VerifiedSolve;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_contestant_creates_team_as_captain_with_private_invitation_code(): void
    {
        $captain = $this->makeUser('captain');

        $this->actingAs($captain)
            ->post(route('teams.store'), ['name' => 'Signal Crew'])
            ->assertRedirect();

        $team = Team::where('name', 'Signal Crew')->firstOrFail();
        $code = $team->invitation_code_encrypted;

        $this->assertSame($captain->id, $team->captain_id);
        $this->assertSame('active', $team->status);
        $this->assertSame(6, strlen($code));
        $this->assertMatchesRegularExpression('/\A[0-9]{6}\z/', $code);
        $this->assertSame(hash('sha256', $code), $team->invitation_code_hash);
        $this->assertNotSame($code, $team->invitation_code_hash);
        $this->assertDatabaseHas('team_memberships', [
            'team_id' => $team->id,
            'user_id' => $captain->id,
            'role' => 'captain',
            'left_at' => null,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'team.created',
            'subject_user_id' => $captain->id,
        ]);
        $this->assertStringNotContainsString($code, json_encode(AdminAuditLog::where('action', 'team.created')->firstOrFail()->details));

        $this->actingAs($captain)->get(route('teams.show', $team))->assertOk()->assertSee($code);
    }

    public function test_registered_contestant_can_join_and_invalid_or_regenerated_codes_fail_privately(): void
    {
        [$captain, $team, $oldCode] = $this->createTeam();
        $member = $this->makeUser('member');

        $this->actingAs($member)
            ->post(route('teams.join.store'), ['invitation_code' => $oldCode])
            ->assertRedirect(route('teams.show', $team));
        $this->assertDatabaseHas('team_memberships', [
            'team_id' => $team->id,
            'user_id' => $member->id,
            'role' => 'member',
            'left_at' => null,
        ]);

        $this->actingAs($member)->from(route('teams.join'))->post(route('teams.join.store'), ['invitation_code' => '999999'])
            ->assertRedirect(route('teams.join'))
            ->assertSessionHasErrors('invitation_code')
            ->assertDontSee('Signal Crew');

        $this->actingAs($captain)->post(route('teams.invitation.regenerate', $team))->assertRedirect();
        $newCode = $team->fresh()->invitation_code_encrypted;
        $this->assertNotSame($oldCode, $newCode);

        $anotherMember = $this->makeUser('another');
        $this->actingAs($anotherMember)->from(route('teams.join'))
            ->post(route('teams.join.store'), ['invitation_code' => $oldCode])
            ->assertRedirect(route('teams.join'))
            ->assertSessionHasErrors('invitation_code');

        $this->actingAs($anotherMember)->post(route('teams.join.store'), ['invitation_code' => $newCode])
            ->assertRedirect(route('teams.show', $team));
    }

    public function test_contestant_can_have_only_one_active_membership_but_can_rejoin_after_leaving(): void
    {
        [$captain, $team, $code] = $this->createTeam();
        [$otherCaptain, $otherTeam] = $this->createTeam('Other Signal');
        $member = $this->makeUser('member');

        $this->actingAs($member)->post(route('teams.join.store'), ['invitation_code' => $code])->assertRedirect();
        $otherCode = $otherTeam->fresh()->invitation_code_encrypted;
        $this->actingAs($member)
            ->from(route('teams.join'))
            ->post(route('teams.join.store'), ['invitation_code' => $otherCode])
            ->assertRedirect(route('teams.join'))
            ->assertSessionHasErrors('invitation_code');

        $membership = TeamMembership::where('team_id', $team->id)->where('user_id', $member->id)->whereNull('left_at')->firstOrFail();
        $this->actingAs($member)->post(route('teams.leave', $team))->assertRedirect(route('teams.index'));
        $this->assertNotNull($membership->fresh()->left_at);
        $this->assertSame(0, $member->fresh()->activeTeams()->count());
        $indexNames = collect(DB::select("PRAGMA index_list('team_memberships')"))->pluck('name')->all();
        $this->assertNotContains('team_memberships_user_id_unique', $indexNames);
        $activeUserIndex = collect(DB::select("PRAGMA index_list('team_memberships')"))->firstWhere('name', 'team_memberships_one_active_user');
        $this->assertNotNull($activeUserIndex);
        $indexSql = DB::table('sqlite_master')->where('name', 'team_memberships_one_active_user')->value('sql');
        $this->assertStringContainsString('WHERE left_at IS NULL', $indexSql);

        $this->actingAs($member)->post(route('teams.join.store'), ['invitation_code' => $otherCode])->assertRedirect(route('teams.show', $otherTeam));
        $this->assertSame(2, TeamMembership::where('user_id', $member->id)->count());
        $this->assertSame(1, TeamMembership::where('user_id', $member->id)->whereNull('left_at')->count());
    }

    public function test_only_captain_can_manage_invitation_or_remove_members(): void
    {
        [$captain, $team, $code] = $this->createTeam();
        $member = $this->makeUser('member');
        $this->actingAs($member)->post(route('teams.join.store'), ['invitation_code' => $code]);

        $this->actingAs($member)->post(route('teams.invitation.regenerate', $team))->assertForbidden();
        $this->actingAs($member)->patch(route('teams.rename', $team), ['name' => 'Hacked Name'])->assertForbidden();
        $this->actingAs($member)->delete(route('teams.members.remove', [$team, $captain]))->assertForbidden();
        $this->actingAs($captain)->delete(route('teams.members.remove', [$team, $captain]))->assertUnprocessable();

        $this->actingAs($captain)->delete(route('teams.members.remove', [$team, $member]))->assertRedirect();
        $this->assertNotNull(TeamMembership::where('team_id', $team->id)->where('user_id', $member->id)->firstOrFail()->left_at);
    }

    public function test_captain_must_transfer_captaincy_before_leaving_and_history_is_preserved(): void
    {
        [$captain, $team, $code] = $this->createTeam();
        $member = $this->makeUser('member');
        $this->actingAs($member)->post(route('teams.join.store'), ['invitation_code' => $code]);

        $this->actingAs($captain)->post(route('teams.leave', $team))->assertUnprocessable();
        $this->actingAs($captain)->post(route('teams.captain.transfer', $team), ['user_id' => $member->id])->assertRedirect();
        $this->assertDatabaseHas('teams', ['id' => $team->id, 'captain_id' => $member->id]);
        $this->assertDatabaseHas('team_memberships', ['team_id' => $team->id, 'user_id' => $member->id, 'role' => 'captain', 'left_at' => null]);

        $this->actingAs($captain)->post(route('teams.leave', $team))->assertRedirect(route('teams.index'));
        $this->assertDatabaseHas('team_memberships', ['team_id' => $team->id, 'user_id' => $captain->id]);
        $this->assertNotNull(TeamMembership::where('team_id', $team->id)->where('user_id', $captain->id)->firstOrFail()->left_at);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'team.captain.transferred']);
    }

    public function test_membership_history_keeps_solve_points_with_team_at_solve(): void
    {
        [$captain, $oldTeam, $code] = $this->createTeam();
        $member = $this->makeUser('member');
        $this->actingAs($member)->post(route('teams.join.store'), ['invitation_code' => $code]);
        $challenge = Challenge::create([
            'title' => 'Fixture Challenge',
            'slug' => 'fixture-challenge',
            'category' => 'miscellaneous',
            'difficulty' => 'beginner',
            'points' => 75,
        ]);
        $member->verifiedSolves()->create([
            'challenge_id' => $challenge->id,
            'team_id_at_solve' => $oldTeam->id,
            'points_awarded' => 75,
        ]);

        $this->actingAs($member)->post(route('teams.leave', $oldTeam));
        [, $newTeam, $newCode] = $this->createTeam('New Signal');
        $this->actingAs($member)->post(route('teams.join.store'), ['invitation_code' => $newCode]);

        $this->actingAs($captain)->get(route('teams.show', $oldTeam))
            ->assertOk()
            ->assertSee('member')
            ->assertSee('75');
        $this->actingAs($member)->get(route('teams.show', $newTeam))
            ->assertOk()
            ->assertSee('0');
        $this->assertSame(75, $oldTeam->attributedSolves()->sum('points_awarded'));
        $this->assertSame(0, $newTeam->attributedSolves()->sum('points_awarded'));
    }

    public function test_contestant_cannot_access_admin_team_management_or_scores(): void
    {
        $user = $this->makeUser('contestant');
        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('teams.index'))->assertOk()->assertSee('Create a team')->assertSee('Enter invitation code');
        $this->actingAs($user)->get(route('teams.join'))->assertOk();
        $this->actingAs($user)->get(route('profile'))->assertOk()->assertSee('ACTIVE TEAM')->assertSee('PERSONAL VERIFIED POINTS');
        [$captain, $team] = $this->createTeam();
        $this->actingAs($user)->get(route('teams.show', $team))->assertNotFound();
    }

    public function test_database_prevents_duplicate_active_membership(): void
    {
        [$captain, $team] = $this->createTeam();
        $member = $this->makeUser('member');
        $team->memberships()->create(['user_id' => $member->id, 'role' => 'member', 'joined_at' => now()]);

        $this->expectException(QueryException::class);
        TeamMembership::create([
            'team_id' => $team->id,
            'user_id' => $member->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);
    }

    public function test_active_captain_account_cannot_be_deleted(): void
    {
        [$captain] = $this->createTeam();

        $this->expectException(QueryException::class);
        $captain->delete();
    }

    public function test_admin_cannot_demote_or_deactivate_an_active_team_captain(): void
    {
        [$captain] = $this->createTeam();
        $admin = User::create([
            'username' => 'range-admin',
            'email' => 'range-admin@example.test',
            'password' => 'long-range-admin-password',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $captain), ['role' => 'admin'])
            ->assertUnprocessable();
        $this->patch(route('admin.users.status', $captain), ['is_active' => '0'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $captain->id, 'role' => 'contestant', 'is_active' => true]);
    }

    public function test_cli_admin_promotion_requires_team_captaincy_transfer(): void
    {
        [$captain] = $this->createTeam();

        $this->artisan('p4dc:make-admin', ['email' => $captain->email])
            ->expectsOutput('Transfer team captaincy before promoting this account.')
            ->assertExitCode(1);

        $this->assertDatabaseHas('users', ['id' => $captain->id, 'role' => 'contestant']);
    }

    public function test_unauthorized_users_cannot_delete_teams(): void
    {
        [$captain, $team] = $this->createTeam('Alpha Squad');
        $contestant = $this->makeUser('random-contestant');

        // Guest is redirected to login
        $this->delete(route('admin.teams.destroy', $team))
            ->assertRedirect(route('login'));

        // Contestant receives 403 Forbidden
        $this->actingAs($contestant)
            ->delete(route('admin.teams.destroy', $team))
            ->assertForbidden();

        // Team still exists
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    public function test_admin_can_delete_team_safely_preserving_users_and_solves(): void
    {
        [$captain, $team, $code] = $this->createTeam('Bravo Squad');
        $member = $this->makeUser('bravo-member');
        $this->actingAs($member)->post(route('teams.join.store'), ['invitation_code' => $code])->assertRedirect();

        $admin = User::create([
            'username' => 'superadmin',
            'email' => 'superadmin@example.test',
            'password' => 'superadminpass',
            'role' => 'admin',
        ]);

        $challenge = Challenge::create([
            'title' => 'Test Flag',
            'slug' => 'test-flag',
            'category' => 'Web',
            'difficulty' => 'easy',
            'points' => 100,
            'flag' => 'p4dCTF{flag_test}',
        ]);

        $solve = VerifiedSolve::create([
            'challenge_id' => $challenge->id,
            'user_id' => $member->id,
            'team_id_at_solve' => $team->id,
            'points_awarded' => 100,
            'verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.teams.destroy', $team))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status', "Team 'Bravo Squad' deleted successfully.");

        // Team is deleted
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);

        // Memberships for team are deleted
        $this->assertDatabaseMissing('team_memberships', ['team_id' => $team->id]);

        // Contestant accounts are preserved
        $this->assertDatabaseHas('users', ['id' => $captain->id]);
        $this->assertDatabaseHas('users', ['id' => $member->id]);

        // Solve is preserved with solver_id and points intact, team_id unlinked
        $this->assertDatabaseHas('verified_solves', [
            'id' => $solve->id,
            'user_id' => $member->id,
            'team_id_at_solve' => null,
            'points_awarded' => 100,
        ]);

        // Audit log recorded
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'team.deleted',
        ]);
        $log = AdminAuditLog::where('action', 'team.deleted')->latest()->first();
        $this->assertSame('Bravo Squad', $log->details['name']);
    }

    public function test_admin_dashboard_renders_delete_action_for_teams(): void
    {
        [$captain, $team] = $this->createTeam('Charlie Squad');
        $admin = User::create([
            'username' => 'control-admin',
            'email' => 'control-admin@example.test',
            'password' => 'controlpass',
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('<th>ACTIONS</th>', false);
        $response->assertSee(route('admin.teams.destroy', $team));
        $response->assertSee("Are you sure you want to delete team 'Charlie Squad'?", false);
        $response->assertSee('Delete</button>', false);
    }

    private function createTeam(string $name = 'Signal Crew'): array
    {
        $captain = $this->makeUser('captain-'.substr(sha1($name), 0, 8));
        $this->actingAs($captain)->post(route('teams.store'), ['name' => $name])->assertRedirect();
        $team = Team::where('name', $name)->firstOrFail();
        auth()->logout();

        return [$captain, $team, $team->invitation_code_encrypted];
    }

    private function makeUser(string $username): User
    {
        static $sequence = 0;
        $sequence++;

        return User::create([
            'username' => $username,
            'email' => 'team-user-'.$sequence.'@example.test',
            'password' => 'long-team-password',
        ]);
    }
}
