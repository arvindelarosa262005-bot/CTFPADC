<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Challenge;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\Models\VerifiedSolve;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $users = User::with('activeTeams:id,name')->orderBy('username')->get();
        $teams = Team::withCount('activeMembers as members_count')->with(['activeMembers:id,username,nickname', 'captain:id,username,nickname'])->orderBy('name')->get();
        $individualScorers = User::whereHas('verifiedSolves')
            ->withSum('verifiedSolves as total_verified_points', 'points_awarded')
            ->withCount('verifiedSolves as challenges_solved')
            ->with('activeTeams:id,name')
            ->orderByDesc('total_verified_points')
            ->orderBy('username')
            ->limit(20)
            ->get();
        $teamScorers = Team::whereHas('attributedSolves')
            ->withSum('attributedSolves as total_verified_points', 'points_awarded')
            ->withCount('attributedSolves as challenges_solved')
            ->withCount('activeMembers as members_count')
            ->with(['captain:id,username,nickname'])
            ->orderByDesc('total_verified_points')
            ->orderBy('name')
            ->limit(20)
            ->get();
        $auditLogs = AdminAuditLog::with(['actor:id,username', 'subject:id,username'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
        $membershipHistory = TeamMembership::with(['team:id,name', 'user:id,username,nickname'])
            ->orderByDesc('joined_at')
            ->limit(50)
            ->get();

        $totalPointsEarned = (int) VerifiedSolve::sum('points_awarded');
        $totalFlagsCaptured = (int) VerifiedSolve::count();
        $recentSolves = VerifiedSolve::with(['user:id,username,nickname', 'teamAtSolve:id,name', 'challenge:id,title,points'])
            ->orderByDesc('verified_at')
            ->limit(10)
            ->get();

        return view('admin.dashboard', [
            'registeredUsers' => User::count(),
            'existingTeams' => Team::count(),
            'liveChallengesCount' => Challenge::where('is_active', true)->count(),
            'totalChallengesCount' => Challenge::count(),
            'totalPointsEarned' => $totalPointsEarned,
            'totalFlagsCaptured' => $totalFlagsCaptured,
            'recentSolves' => $recentSolves,
            'users' => $users,
            'teams' => $teams,
            'individualScorers' => $individualScorers,
            'teamScorers' => $teamScorers,
            'auditLogs' => $auditLogs,
            'membershipHistory' => $membershipHistory,
        ]);
    }

    public function liveScores(): JsonResponse
    {
        $individualScorers = User::whereHas('verifiedSolves')
            ->withSum('verifiedSolves as total_verified_points', 'points_awarded')
            ->withCount('verifiedSolves as challenges_solved')
            ->with('activeTeams:id,name')
            ->orderByDesc('total_verified_points')
            ->orderBy('username')
            ->limit(20)
            ->get()
            ->map(function ($scorer, $index) {
                return [
                    'rank' => $index + 1,
                    'id' => $scorer->id,
                    'username' => $scorer->username,
                    'nickname' => $scorer->nickname,
                    'callsign' => $scorer->nickname ?: $scorer->username,
                    'team_name' => $scorer->activeTeams->first()?->name ?? 'Solo / No team',
                    'points' => (int) ($scorer->total_verified_points ?? 0),
                    'solves_count' => (int) $scorer->challenges_solved,
                    'is_admin' => $scorer->isAdmin(),
                ];
            });

        $teamScorers = Team::whereHas('attributedSolves')
            ->withSum('attributedSolves as total_verified_points', 'points_awarded')
            ->withCount('attributedSolves as challenges_solved')
            ->withCount('activeMembers as members_count')
            ->with(['captain:id,username,nickname'])
            ->orderByDesc('total_verified_points')
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(function ($team, $index) {
                return [
                    'rank' => $index + 1,
                    'id' => $team->id,
                    'name' => $team->name,
                    'points' => (int) ($team->total_verified_points ?? 0),
                    'solves_count' => (int) $team->challenges_solved,
                    'members_count' => (int) $team->members_count,
                    'max_members' => Team::MAX_MEMBERS,
                    'captain_callsign' => $team->captain?->nickname ?: $team->captain?->username ?? 'Unknown',
                ];
            });

        $recentSolves = VerifiedSolve::with(['user:id,username,nickname', 'teamAtSolve:id,name', 'challenge:id,title,points'])
            ->orderByDesc('verified_at')
            ->limit(10)
            ->get()
            ->map(function ($solve) {
                return [
                    'user_callsign' => $solve->user?->nickname ?: $solve->user?->username ?? 'Operative',
                    'username' => $solve->user?->username ?? '',
                    'team_name' => $solve->teamAtSolve?->name ?? 'Independent',
                    'challenge_title' => $solve->challenge?->title ?? 'Classified Target',
                    'points' => (int) $solve->points_awarded,
                    'verified_at' => $solve->verified_at?->format('H:i:s') ?? '',
                ];
            });

        return response()->json([
            'teams' => $teamScorers,
            'individuals' => $individualScorers,
            'recent_solves' => $recentSolves,
            'stats' => [
                'total_points' => (int) VerifiedSolve::sum('points_awarded'),
                'total_flags' => (int) VerifiedSolve::count(),
                'registered_users' => User::count(),
                'existing_teams' => Team::count(),
            ],
            'timestamp' => now()->format('H:i:s'),
        ]);
    }
}
