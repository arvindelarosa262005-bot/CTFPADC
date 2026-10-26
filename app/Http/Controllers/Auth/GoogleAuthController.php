<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect(): RedirectResponse
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');

        if (empty($clientId) || empty($clientSecret)) {
            return redirect()->route('login')->withErrors([
                'credential' => 'Google Authentication is not yet configured. Please set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in .env.',
            ]);
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google and authenticate.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors([
                'credential' => 'Unable to authenticate with Google. Please try again or sign in with your credentials.',
            ]);
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));
        $googleId = (string) $googleUser->getId();
        $avatar = $googleUser->getAvatar();

        if ($email === '' || $googleId === '') {
            return redirect()->route('login')->withErrors([
                'credential' => 'Google account did not provide a valid email or user identifier.',
            ]);
        }

        // 1. Check if user already exists by google_id
        $user = User::where('google_id', $googleId)->first();

        if (! $user) {
            // 2. Check if user exists by email to link account
            $user = User::where('email', $email)->first();

            if ($user) {
                $user->update([
                    'google_id' => $googleId,
                    'avatar' => $avatar ?: $user->avatar,
                ]);
            } else {
                // 3. Create a new user as a contestant
                $username = $this->generateUniqueUsername($googleUser->getNickname() ?: $googleUser->getName(), $email);

                $user = User::create([
                    'username' => $username,
                    'email' => $email,
                    'google_id' => $googleId,
                    'avatar' => $avatar,
                    'password' => Hash::make(Str::random(32)),
                    'role' => 'contestant',
                    'is_active' => true,
                ]);
            }
        } else {
            // Update avatar if provided
            if ($avatar && $user->avatar !== $avatar) {
                $user->update(['avatar' => $avatar]);
            }
        }

        if (! $user->is_active) {
            return redirect()->route('login')->withErrors([
                'credential' => 'This account is deactivated. Please contact an administrator.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($user->isAdmin() ? 'admin.dashboard' : 'dashboard')
            ->with('status', 'Signed in with Google successfully.');
    }

    /**
     * Generate a unique, validated username complying with P4DC rules:
     * - min: 3, max: 30
     * - regex: /^[a-zA-Z0-9_.|-]+$/
     */
    protected function generateUniqueUsername(?string $candidate, string $email): string
    {
        $base = $candidate ? trim($candidate) : explode('@', $email)[0];
        // Normalize characters: keep only letters, numbers, dot, underscore, dash, pipe
        $cleaned = preg_replace('/[^a-zA-Z0-9_.|-]/', '', str_replace(' ', '_', $base)) ?: '';

        if (strlen($cleaned) < 3) {
            $prefix = explode('@', $email)[0];
            $cleaned = preg_replace('/[^a-zA-Z0-9_.|-]/', '', $prefix) ?: '';
        }

        if (strlen($cleaned) < 3) {
            $cleaned = 'player_' . strtolower(Str::random(4));
        }

        $cleaned = strtolower(substr($cleaned, 0, 24));

        $username = $cleaned;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $suffix = '-' . $counter;
            $username = substr($cleaned, 0, 30 - strlen($suffix)) . $suffix;
            $counter++;
        }

        return $username;
    }
}
