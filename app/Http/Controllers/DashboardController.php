<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (auth()->user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $user = auth()->user();

        $availableChallenges = Challenge::query()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->count();

        $solvedCount = $user->verifiedSolves()->count();

        $recentChallenges = Challenge::query()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'availableChallenges' => $availableChallenges,
            'solvedCount' => $solvedCount,
            'recentChallenges' => $recentChallenges,
        ]);
    }

    public function profile(): View
    {
        return view('profile');
    }
}
