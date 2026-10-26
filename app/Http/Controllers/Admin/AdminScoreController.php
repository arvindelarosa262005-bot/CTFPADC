<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\Team;
use App\Models\User;
use App\Models\VerifiedSolve;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminScoreController extends Controller
{
    public function recalculate(Request $request): RedirectResponse
    {
        $solves = VerifiedSolve::with(['challenge:id,points', 'user:id,username'])->get();
        $totalSolves = $solves->count();
        $totalPoints = 0;
        $updatedSolves = 0;

        DB::transaction(function () use ($solves, &$totalPoints, &$updatedSolves, $request, $totalSolves): void {
            foreach ($solves as $solve) {
                $canonicalPoints = (int) ($solve->challenge?->points ?? $solve->points_awarded);
                if ($solve->points_awarded !== $canonicalPoints) {
                    $solve->update(['points_awarded' => $canonicalPoints]);
                    $updatedSolves++;
                }
                $totalPoints += $canonicalPoints;
            }

            $scorersCount = User::whereHas('verifiedSolves')->count();
            $teamsCount = Team::whereHas('attributedSolves')->count();

            AdminAuditLog::create([
                'actor_user_id' => $request->user()->id,
                'subject_user_id' => null,
                'action' => 'scores.recalculated',
                'details' => [
                    'solves_reviewed' => $totalSolves,
                    'solves_updated' => $updatedSolves,
                    'total_points' => $totalPoints,
                    'scorers_count' => $scorersCount,
                    'teams_count' => $teamsCount,
                    'timestamp' => now()->toIso8601String(),
                ],
                'created_at' => now(),
            ]);
        });

        $scorersCount = User::whereHas('verifiedSolves')->count();

        return redirect()->route('admin.dashboard')
            ->with('status', "Scores recalculated successfully from {$totalSolves} verified solve records ({$totalPoints} total points across {$scorersCount} individual scorers).");
    }

    public function reset(Request $request): RedirectResponse
    {
        $solvesCount = VerifiedSolve::count();
        $submissionsCount = ChallengeSubmission::count();
        $pointsSum = (int) VerifiedSolve::sum('points_awarded');

        DB::transaction(function () use ($request, $solvesCount, $submissionsCount, $pointsSum): void {
            VerifiedSolve::query()->delete();
            ChallengeSubmission::query()->delete();

            AdminAuditLog::create([
                'actor_user_id' => $request->user()->id,
                'subject_user_id' => null,
                'action' => 'competition.solves_reset',
                'details' => [
                    'solves_removed' => $solvesCount,
                    'submissions_removed' => $submissionsCount,
                    'points_removed' => $pointsSum,
                    'timestamp' => now()->toIso8601String(),
                ],
                'created_at' => now(),
            ]);
        });

        return redirect()->route('admin.dashboard')
            ->with('status', "Competition test solves reset successfully ({$solvesCount} solve records and {$submissionsCount} submissions removed, {$pointsSum} points cleared). Challenges, accounts, and teams preserved.");
    }
}

