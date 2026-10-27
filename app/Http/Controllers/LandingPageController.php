<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\Team;
use App\Models\User;
use App\Models\VerifiedSolve;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (auth()->check() && auth()->user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $stats = [
            'total_challenges' => Challenge::where('is_active', true)->count(),
            'total_players' => User::where('role', 'contestant')->count(),
            'total_teams' => Team::count(),
            'total_solves' => VerifiedSolve::count(),
        ];

        return view('landing', compact('stats'));
    }
}
