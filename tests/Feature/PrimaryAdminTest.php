<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrimaryAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.primary_admin.email' => 'padcadmin@example.test',
            'auth.primary_admin.username' => 'padcadmin',
        ]);
    }

    public function test_primary_admin_bootstrap_creates_once_then_preserves_password_and_profile(): void
    {
        $this->artisan('p4dc:bootstrap-admin')
            ->expectsQuestion('Choose a password for the primary administrator (input hidden)', 'secure-test-password')
            ->expectsQuestion('Confirm the primary administrator password (input hidden)', 'secure-test-password')
            ->assertExitCode(0);

        $admin = User::where('email', 'padcadmin@example.test')->firstOrFail();
        $hash = $admin->password;
        $this->assertSame('padcadmin', $admin->username);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('secure-test-password', $hash));

        $this->artisan('p4dc:bootstrap-admin')->assertExitCode(0);

        $admin->refresh();
        $this->assertSame($hash, $admin->password);
        $this->assertSame('padcadmin', $admin->username);
        $this->assertSame('padcadmin@example.test', $admin->email);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_primary_admin_can_login_with_email_or_username_and_bad_password_is_generic(): void
    {
        $admin = User::create([
            'username' => 'padcadmin',
            'email' => 'padcadmin@example.test',
            'password' => 'secure-test-password',
            'role' => 'admin',
        ]);

        $this->post(route('login.store'), [
            'credential' => ' PADCADMIN@EXAMPLE.TEST ',
            'password' => 'secure-test-password',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);

        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'credential' => 'PADCADMIN',
            'password' => 'secure-test-password',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);

        $this->post(route('logout'));
        $this->from(route('login'))->post(route('login.store'), [
            'credential' => 'padcadmin',
            'password' => 'incorrect-password',
        ])->assertRedirect(route('login'))
          ->assertSessionHasErrors(['credential' => 'These credentials do not match our records.']);
        $this->assertGuest();
    }

    public function test_inactive_primary_admin_cannot_login(): void
    {
        User::create([
            'username' => 'padcadmin',
            'email' => 'padcadmin@example.test',
            'password' => 'secure-test-password',
            'role' => 'admin',
            'is_active' => false,
        ]);

        $this->from(route('login'))->post(route('login.store'), [
            'credential' => 'padcadmin@example.test',
            'password' => 'secure-test-password',
        ])->assertRedirect(route('login'))
          ->assertSessionHasErrors(['credential' => 'These credentials do not match our records.']);
        $this->assertGuest();
    }

    public function test_primary_admin_seeder_preserves_existing_password_and_account_fields(): void
    {
        $admin = User::create([
            'username' => 'padcadmin',
            'email' => 'padcadmin@example.test',
            'password' => 'existing-secure-password',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $passwordHash = $admin->password;

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $admin->refresh();
        $this->assertSame($passwordHash, $admin->password);
        $this->assertSame('padcadmin', $admin->username);
        $this->assertSame('padcadmin@example.test', $admin->email);
        $this->assertSame(1, User::count());
    }

    public function test_standard_seeder_does_not_create_an_account_or_password_when_primary_is_absent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_bootstrap_promotes_existing_identity_without_changing_password_or_profile(): void
    {
        $user = User::create([
            'username' => 'padcadmin',
            'email' => 'padcadmin@example.test',
            'password' => 'existing-secure-password',
            'role' => 'contestant',
            'is_active' => false,
        ]);
        $hash = $user->password;

        $this->artisan('p4dc:bootstrap-admin')->assertExitCode(0);

        $user->refresh();
        $this->assertSame('padcadmin', $user->username);
        $this->assertSame('padcadmin@example.test', $user->email);
        $this->assertSame($hash, $user->password);
        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_primary_admin_privileges_are_protected_from_admin_ui_changes(): void
    {
        $primary = User::create([
            'username' => 'padcadmin',
            'email' => 'padcadmin@example.test',
            'password' => 'existing-secure-password',
            'role' => 'admin',
        ]);
        $otherAdmin = User::create([
            'username' => 'backup-admin',
            'email' => 'backup@example.test',
            'password' => 'another-secure-password',
            'role' => 'admin',
        ]);

        $this->actingAs($otherAdmin)
            ->patch(route('admin.users.role', $primary), ['role' => 'contestant'])
            ->assertForbidden();
        $this->patch(route('admin.users.status', $primary), ['is_active' => '0'])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $primary->id, 'role' => 'admin', 'is_active' => true]);
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_password_recovery_is_explicit_and_audited(): void
    {
        $admin = User::create([
            'username' => 'padcadmin',
            'email' => 'padcadmin@example.test',
            'password' => 'old-secure-password',
            'role' => 'admin',
        ]);
        $oldHash = $admin->password;

        $this->artisan('p4dc:recover-admin')
            ->expectsConfirmation('Reset the password for primary administrator padcadmin? This is an explicit recovery operation.', 'yes')
            ->expectsQuestion('New password (input hidden)', 'new-secure-password')
            ->expectsQuestion('Confirm new password (input hidden)', 'new-secure-password')
            ->assertExitCode(0);

        $admin->refresh();
        $this->assertNotSame($oldHash, $admin->password);
        $this->assertTrue(Hash::check('new-secure-password', $admin->password));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'primary_admin.password_recovered']);
        $this->assertStringNotContainsString('new-secure-password', json_encode(AdminAuditLog::firstOrFail()->details));
    }
}
