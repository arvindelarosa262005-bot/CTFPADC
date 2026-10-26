<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $users = User::with('activeTeams:id,name')->orderBy('username')->get();
        $teams = Team::withCount('activeMembers as members_count')->with(['activeMembers:id,username', 'captain:id,username'])->orderBy('name')->get();
        $individualScorers = User::whereHas('verifiedSolves')
            ->withSum('verifiedSolves as total_verified_points', 'points_awarded')
            ->withCount('verifiedSolves as challenges_solved')
            ->orderByDesc('total_verified_points')
            ->orderBy('username')
            ->limit(10)
            ->get();
        $teamScorers = Team::whereHas('attributedSolves')
            ->withSum('attributedSolves as total_verified_points', 'points_awarded')
            ->withCount('attributedSolves as challenges_solved')
            ->withCount('activeMembers as members_count')
            ->orderByDesc('total_verified_points')
            ->orderBy('name')
            ->limit(10)
            ->get();
        $auditLogs = AdminAuditLog::with(['actor:id,username', 'subject:id,username'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
        $membershipHistory = TeamMembership::with(['team:id,name', 'user:id,username'])
            ->orderByDesc('joined_at')
            ->limit(50)
            ->get();

        return view('admin.dashboard', [
            'registeredUsers' => User::count(),
            'existingTeams' => Team::count(),
            'users' => $users,
            'teams' => $teams,
            'individualScorers' => $individualScorers,
            'teamScorers' => $teamScorers,
            'auditLogs' => $auditLogs,
            'membershipHistory' => $membershipHistory,
        ]);
    }
}
