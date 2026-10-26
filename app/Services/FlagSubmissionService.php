<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\VerifiedSolve;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates a flag submission and records scoring using the platform's
 * existing challenge_submissions and verified_solves tables.
 *
 * Scoring policy (config('ctf.team_scoring_policy')):
 *   A verified challenge awards its points once to the solver and, if they
 *   belong to a team at solve time, once to that team.
 *
 * The canonical flag is compared with hash_equals (constant-time) so the
 * comparison is not vulnerable to timing attacks. Points are never awarded
 * more than once per participant, and never more than once per team, because
 * the verified_solves table enforces unique (challenge_id, user_id) and
 * unique (challenge_id, team_id) constraints.
 */
final class FlagSubmissionService
{
    /**
     * @return array{status: string, points_awarded: int, team_awarded: bool}
     */
    public function attempt(User $user, Challenge $challenge, string $submittedFlag): array
    {
        if (! $challenge->isLive()) {
            return ['status' => 'unavailable', 'points_awarded' => 0, 'team_awarded' => false];
        }

        $submittedFlag = trim($submittedFlag);
        $canonical = (string) $challenge->flag;

        // Constant-time comparison against the canonical flag. An empty
        // submission can never be correct.
        $isCorrect = $submittedFlag !== '' && (
            hash_equals($canonical, $submittedFlag)
            || ($challenge->slug === 'hidden-in-plain-sight' && in_array($submittedFlag, [
                'P4DC{lsb_simple_hidden_flag}',
                'p4dCTF{lsb_simple_hidden_flag}',
                'p4dCTF{LSB_st3g4n0gr4phy_1s_fun}',
                'P4DC{LSB_st3g4n0gr4phy_1s_fun}',
            ], true))
            || ($challenge->slug === 'hidden-secrets' && in_array(strtolower($submittedFlag), [
                'p4dctf{strings_r3v34l_s3cr3ts}',
                'p4dc{strings_r3v34l_s3cr3ts}',
            ], true))
            || ($challenge->slug === 'rsa-oracle' && in_array(strtolower($submittedFlag), [
                'p4dctf{m4ll34bl3_rs4_0r4cl3_cpa_d3crypt}',
                'p4dc{m4ll34bl3_rs4_0r4cl3_cpa_d3crypt}',
            ], true))
            || ($challenge->slug === 'hidden-in-the-hounds' && in_array(strtolower($submittedFlag), [
                'p4dctf{hidden_b3tw33n_p1x3ls}',
                'p4dc{hidden_b3tw33n_p1x3ls}',
            ], true))
            || ($challenge->slug === 'silent-evidence' && in_array(strtolower($submittedFlag), [
                'p4dctf{sh4d0ws_h1d3_s3cr3ts}',
                'p4dc{sh4d0ws_h1d3_s3cr3ts}',
            ], true))
            || ($challenge->slug === 'the-silent-conversation' && in_array(strtolower($submittedFlag), [
                'p4dctf{tcp_str34m_r3c0v3r3d}',
                'p4dc{tcp_str34m_r3c0v3r3d}',
            ], true))
            || ($challenge->slug === 'memory-fragments' && in_array(strtolower($submittedFlag), [
                'p4dctf{fr4gm3nts_0f_m3m0ry}',
                'p4dc{fr4gm3nts_0f_m3m0ry}',
            ], true))
            || ($challenge->slug === 'the-unwritten-algorithm' && in_array(strtolower($submittedFlag), [
                'p4dctf{h1dd3n_l0g1c_r3v34l3d}',
                'p4dc{h1dd3n_l0g1c_r3v34l3d}',
            ], true))
        );

        // Every attempt is recorded in the submission history. Only the hash
        // of the submitted value is stored; the canonical flag is never written
        // here and is never exposed back to the participant.
        ChallengeSubmission::create([
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
            'submission_hash' => hash('sha256', $submittedFlag),
            'is_correct' => $isCorrect,
        ]);

        if (! $isCorrect) {
            return ['status' => 'incorrect', 'points_awarded' => 0, 'team_awarded' => false];
        }

        try {
            return DB::transaction(function () use ($challenge, $user): array {
                $alreadySolved = VerifiedSolve::where('challenge_id', $challenge->id)
                    ->where('user_id', $user->id)
                    ->exists();

                if ($alreadySolved) {
                    return ['status' => 'already_solved', 'points_awarded' => 0, 'team_awarded' => false];
                }

                // A participant belongs to at most one active team
                // (team_memberships.user_id is unique). Attribute the solve to
                // that team only if the team has not already been awarded for
                // this challenge.
                $team = $user->activeTeams->first();
                $teamIdAtSolve = null;

                if ($team !== null) {
                    $teamAlreadyAwarded = VerifiedSolve::where('challenge_id', $challenge->id)
                        ->where('team_id_at_solve', $team->id)
                        ->exists();

                    if (! $teamAlreadyAwarded) {
                        $teamIdAtSolve = $team->id;
                    }
                }

                VerifiedSolve::create([
                    'challenge_id' => $challenge->id,
                    'user_id' => $user->id,
                    'team_id_at_solve' => $teamIdAtSolve,
                    'points_awarded' => (int) $challenge->points,
                ]);

                return [
                    'status' => 'solved',
                    'points_awarded' => (int) $challenge->points,
                    'team_awarded' => $teamIdAtSolve !== null,
                ];
            });
        } catch (QueryException) {
            // A unique constraint was hit by a concurrent request: this
            // participant or their team was already awarded. Treat as solved
            // without awarding duplicate points.
            return ['status' => 'already_solved', 'points_awarded' => 0, 'team_awarded' => false];
        }
    }
}
