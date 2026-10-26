<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        foreach (['username', 'email'] as $field) {
            $value = $request->input($field);

            if (is_string($value)) {
                $request->merge([$field => strtolower($value)]);
            }
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/\A[a-zA-Z0-9_.|-]+\z/', 'unique:users,username'],
            'nickname' => ['nullable', 'string', 'max:50', 'regex:/\A[a-zA-Z0-9_.|-]+\z/'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.min' => 'Password must be at least 8 characters long.',
        ]);

        $user = User::create($validated);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Your P4DC account is ready.');
    }
}
