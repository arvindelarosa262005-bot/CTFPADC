<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

use Illuminate\Http\Request;

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
        $user = auth()->user()->load('activeTeams');
        $team = $user->activeTeams->first();

        $points = (int) $user->verifiedSolves()->sum('points_awarded');
        $flags = (int) $user->verifiedSolves()->count();

        $rank = null;
        if (! $user->isAdmin() && $flags > 0) {
            $rank = User::query()
                ->whereHas('verifiedSolves')
                ->withSum('verifiedSolves as total_points', 'points_awarded')
                ->get()
                ->filter(fn (User $other) => (int) $other->total_points > $points)
                ->count() + 1;
        }

        $recentSolves = $user->verifiedSolves()
            ->with('challenge:id,title,slug,category')
            ->orderByDesc('verified_at')
            ->limit(5)
            ->get();

        return view('profile', [
            'user' => $user,
            'team' => $team,
            'teamMemberCount' => $team?->activeMembers()->count() ?? 0,
            'points' => $points,
            'flags' => $flags,
            'rank' => $rank,
            'recentSolves' => $recentSolves,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nickname' => ['nullable', 'string', 'max:50', 'regex:/\A[a-zA-Z0-9_.|-]+\z/'],
        ], [
            'nickname.regex' => 'The callsign/nickname may only contain letters, numbers, dashes, underscores, pipes, and periods.',
        ]);

        $request->user()->update([
            'nickname' => $validated['nickname'] ?? null,
        ]);

        return back()->with('status', 'Operative callsign updated successfully.');
    }
}
