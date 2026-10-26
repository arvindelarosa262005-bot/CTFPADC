<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_register_pages_render_google_auth_button(): void
    {
        $loginResponse = $this->get(route('login'));
        $loginResponse->assertOk();
        $loginResponse->assertSee(route('auth.google'));
        $loginResponse->assertSee('Continue with Google');

        $registerResponse = $this->get(route('register'));
        $registerResponse->assertOk();
        $registerResponse->assertSee(route('auth.google'));
        $registerResponse->assertSee('Sign up with Google');
    }

    public function test_google_redirect_warns_when_not_configured(): void
    {
        Config::set('services.google.client_id', null);
        Config::set('services.google.client_secret', null);

        $response = $this->get(route('auth.google'));
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('credential');
    }

    public function test_google_redirect_redirects_to_provider(): void
    {
        Config::set('services.google.client_id', 'mock-google-client-id');
        Config::set('services.google.client_secret', 'mock-google-client-secret');

        $response = $this->get(route('auth.google'));
        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', (string) $response->headers->get('Location'));
    }

    public function test_google_callback_creates_and_authenticates_new_contestant(): void
    {
        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('google-unique-id-9988');
        $abstractUser->shouldReceive('getEmail')->andReturn('newplayer@example.com');
        $abstractUser->shouldReceive('getNickname')->andReturn('cyber_hunter');
        $abstractUser->shouldReceive('getName')->andReturn('Cyber Hunter');
        $abstractUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar.jpg');

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::where('email', 'newplayer@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-unique-id-9988', $user->google_id);
        $this->assertSame('contestant', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame('cyber_hunter', $user->username);
    }

    public function test_google_callback_links_existing_user_by_email(): void
    {
        $existing = User::create([
            'username' => 'existing_player',
            'email' => 'existing@example.com',
            'password' => 'SecurePass123!',
            'role' => 'contestant',
            'is_active' => true,
        ]);

        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('google-id-5555');
        $abstractUser->shouldReceive('getEmail')->andReturn('existing@example.com');
        $abstractUser->shouldReceive('getNickname')->andReturn(null);
        $abstractUser->shouldReceive('getName')->andReturn('Existing Player');
        $abstractUser->shouldReceive('getAvatar')->andReturn(null);

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($existing);

        $existing->refresh();
        $this->assertSame('google-id-5555', $existing->google_id);
    }

    public function test_google_callback_blocks_deactivated_accounts(): void
    {
        $inactive = User::create([
            'username' => 'banned_player',
            'email' => 'banned@example.com',
            'google_id' => 'google-banned-000',
            'password' => 'SecurePass123!',
            'role' => 'contestant',
            'is_active' => false,
        ]);

        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('google-banned-000');
        $abstractUser->shouldReceive('getEmail')->andReturn('banned@example.com');
        $abstractUser->shouldReceive('getNickname')->andReturn(null);
        $abstractUser->shouldReceive('getName')->andReturn('Banned');
        $abstractUser->shouldReceive('getAvatar')->andReturn(null);

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('credential');
        $this->assertGuest();
    }
}
