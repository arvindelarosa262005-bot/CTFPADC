<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\AiTelemetryLog;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\VerifiedSolve;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates a flag submission, performs AI anti-cheat telemetry analysis,
 * detects honeypot canaries, and records First Blood bonuses.
 */
final class FlagSubmissionService
{
    /**
     * Honeypot canaries embedded in challenge descriptions and targets.
     * If an operative copies text to an LLM, the model extracts these decoy flags.
     */
    private const AI_HONEYPOT_FLAGS = [
        'p4dc{41_c0py_p4st3_d3t3ct3d_by_sy5t3m}',
        'p4dc{gpt_llm_h0n3yp0t_tr1gg3r3d}',
        'p4dc{ai_agent_prompt_injection_flag}',
        'p4dctf{41_c0py_p4st3_d3t3ct3d_by_sy5t3m}',
        'p4dctf{gpt_llm_h0n3yp0t_tr1gg3r3d}',
        'p4dctf{ai_agent_prompt_injection_flag}',
    ];

    /**
     * Signatures from copy-pasting conversational LLM output.
     */
    private const AI_CONVERSATIONAL_PHRASES = [
        'as an ai language model',
        'as an ai,',
        'certainly! here is',
        'here is the flag',
        'the flag is:',
        'the decoded flag is',
        'the recovered flag is',
        '```markdown',
        '```plaintext',
    ];

    /**
     * @param  array{is_pasted?: bool, duration_seconds?: int, tab_switches?: int, keystrokes?: int}  $telemetry
     * @return array{status: string, points_awarded: int, team_awarded: bool, is_first_blood?: bool, bonus_points?: int, message?: string}
     */
    public function attempt(User $user, Challenge $challenge, string $submittedFlag, array $telemetry = []): array
    {
        if (! $challenge->isLive()) {
            return ['status' => 'unavailable', 'points_awarded' => 0, 'team_awarded' => false];
        }

        $submittedFlag = trim($submittedFlag);
        $cleanLower = strtolower($submittedFlag);

        // ── 1. AI CANARY HONEYPOT TRAP DETECTION ────────────────────────────
        if (in_array($cleanLower, self::AI_HONEYPOT_FLAGS, true)) {
            $user->update([
                'is_ai_flagged' => true,
                'ai_suspicion_score' => 100,
                'ai_flag_reason' => 'Triggered AI Honeypot Canary Trap (' . $challenge->title . ')',
            ]);

            AiTelemetryLog::create([
                'user_id' => $user->id,
                'challenge_id' => $challenge->id,
                'event_type' => 'honeypot_triggered',
                'severity' => 'critical',
                'details' => "Operative submitted AI prompt-injection honeypot flag on challenge '{$challenge->title}'. Confirmed LLM copy-paste.",
                'submitted_content' => substr($submittedFlag, 0, 200),
                'created_at' => now(),
            ]);

            AdminAuditLog::create([
                'subject_user_id' => $user->id,
                'action' => 'ai_anticheat.honeypot_tripped',
                'details' => [
                    'challenge' => $challenge->title,
                    'operative' => $user->callsign,
                    'flag_submitted' => $submittedFlag,
                ],
                'created_at' => now(),
            ]);

            return [
                'status' => 'ai_flagged',
                'points_awarded' => 0,
                'team_awarded' => false,
                'message' => '🚨 AI PROMPT-INJECTION DETECTED! Your account has been logged and flagged on the Admin Integrity Radar.',
            ];
        }

        // ── 2. AI CONVERSATIONAL SIGNATURE DETECTION ────────────────────────
        foreach (self::AI_CONVERSATIONAL_PHRASES as $phrase) {
            if (str_contains($cleanLower, $phrase)) {
                $newScore = min(100, (int) $user->ai_suspicion_score + 60);
                $isFlagged = $newScore >= 70;

                $user->update([
                    'is_ai_flagged' => $isFlagged,
                    'ai_suspicion_score' => $newScore,
                    'ai_flag_reason' => $isFlagged ? 'Submitted LLM Conversational Template' : $user->ai_flag_reason,
                ]);

                AiTelemetryLog::create([
                    'user_id' => $user->id,
                    'challenge_id' => $challenge->id,
                    'event_type' => 'ai_phrase_detected',
                    'severity' => 'high',
                    'details' => "Submission payload contained Generative AI conversational artifact: '{$phrase}'.",
                    'submitted_content' => substr($submittedFlag, 0, 200),
                    'created_at' => now(),
                ]);

                return [
                    'status' => 'ai_flagged',
                    'points_awarded' => 0,
                    'team_awarded' => false,
                    'message' => '⚠️ Suspicious AI signature detected in your submission. Please submit only the raw flag value.',
                ];
            }
        }

        // ── 3. BEHAVIORAL TELEMETRY ANALYSIS ────────────────────────────────
        $isPasted = ! empty($telemetry['is_pasted']);
        $durationSeconds = isset($telemetry['duration_seconds']) ? (int) $telemetry['duration_seconds'] : null;
        $tabSwitches = isset($telemetry['tab_switches']) ? (int) $telemetry['tab_switches'] : 0;
        $keystrokes = isset($telemetry['keystrokes']) ? (int) $telemetry['keystrokes'] : 0;

        // Flag if a difficult challenge was solved impossibly fast via external paste after tab switching
        if ($challenge->points >= 50 && $durationSeconds !== null && $durationSeconds < 10 && $isPasted && $keystrokes === 0 && $tabSwitches >= 2) {
            $newScore = min(100, (int) $user->ai_suspicion_score + 30);
            $isFlagged = $newScore >= 70;

            $user->update([
                'is_ai_flagged' => $isFlagged,
                'ai_suspicion_score' => $newScore,
                'ai_flag_reason' => $isFlagged ? 'Instant Clipboard Solve Anomaly' : $user->ai_flag_reason,
            ]);

            AiTelemetryLog::create([
                'user_id' => $user->id,
                'challenge_id' => $challenge->id,
                'event_type' => 'rapid_solve_paste',
                'severity' => 'medium',
                'details' => "High-value challenge ({$challenge->points} pts) solved in {$durationSeconds}s with 0 keystrokes and {$tabSwitches} tab switches.",
                'submitted_content' => '[FLAG_HASHED]',
                'created_at' => now(),
            ]);
        }

        // ── 4. FLAG VERIFICATION ────────────────────────────────────────────
        $canonical = (string) $challenge->flag;

        $isCorrect = $submittedFlag !== '' && (
            hash_equals($canonical, $submittedFlag)
            || ($challenge->slug === 'hidden-in-plain-sight' && in_array($submittedFlag, [
                'P4DC{lsb_simple_hidden_flag}',
                'p4dCTF{lsb_simple_hidden_flag}',
                'p4dCTF{LSB_st3g4n0gr4phy_1s_fun}',
                'P4DC{LSB_st3g4n0gr4phy_1s_fun}',
            ], true))
            || ($challenge->slug === 'hidden-secrets' && in_array($cleanLower, [
                'p4dctf{strings_r3v34l_s3cr3ts}',
                'p4dc{strings_r3v34l_s3cr3ts}',
            ], true))
            || ($challenge->slug === 'rsa-oracle' && in_array($cleanLower, [
                'p4dctf{m4ll34bl3_rs4_0r4cl3_cpa_d3crypt}',
                'p4dc{m4ll34bl3_rs4_0r4cl3_cpa_d3crypt}',
            ], true))
            || ($challenge->slug === 'hidden-in-the-hounds' && in_array($cleanLower, [
                'p4dctf{hidden_b3tw33n_p1x3ls}',
                'p4dc{hidden_b3tw33n_p1x3ls}',
            ], true))
            || ($challenge->slug === 'silent-evidence' && in_array($cleanLower, [
                'p4dctf{sh4d0ws_h1d3_s3cr3ts}',
                'p4dc{sh4d0ws_h1d3_s3cr3ts}',
            ], true))
            || ($challenge->slug === 'the-silent-conversation' && in_array($cleanLower, [
                'p4dctf{tcp_str34m_r3c0v3r3d}',
                'p4dc{tcp_str34m_r3c0v3r3d}',
            ], true))
            || ($challenge->slug === 'memory-fragments' && in_array($cleanLower, [
                'p4dctf{fr4gm3nts_0f_m3m0ry}',
                'p4dc{fr4gm3nts_0f_m3m0ry}',
            ], true))
            || ($challenge->slug === 'the-unwritten-algorithm' && in_array($cleanLower, [
                'p4dctf{h1dd3n_l0g1c_r3v34l3d}',
                'p4dc{h1dd3n_l0g1c_r3v34l3d}',
            ], true))
        );

        // Record submission attempt
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

                // First Blood calculation: Is this the first solve in the entire CTF?
                $isFirstBlood = ! VerifiedSolve::where('challenge_id', $challenge->id)->exists();
                $bonusPoints = $isFirstBlood ? (int) max(5, round($challenge->points * 0.15)) : 0;
                $totalPointsAwarded = (int) $challenge->points + $bonusPoints;

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
                    'points_awarded' => $totalPointsAwarded,
                    'is_first_blood' => $isFirstBlood,
                    'bonus_points' => $bonusPoints,
                    'verified_at' => now(),
                ]);

                if ($isFirstBlood) {
                    AdminAuditLog::create([
                        'subject_user_id' => $user->id,
                        'action' => 'ctf.first_blood',
                        'details' => [
                            'challenge' => $challenge->title,
                            'operative' => $user->callsign,
                            'team' => $team?->name ?? 'Solo Operative',
                            'bonus_awarded' => $bonusPoints,
                        ],
                        'created_at' => now(),
                    ]);
                }

                return [
                    'status' => $isFirstBlood ? 'first_blood' : 'solved',
                    'points_awarded' => $totalPointsAwarded,
                    'team_awarded' => $teamIdAtSolve !== null,
                    'is_first_blood' => $isFirstBlood,
                    'bonus_points' => $bonusPoints,
                ];
            });
        } catch (QueryException) {
            return ['status' => 'already_solved', 'points_awarded' => 0, 'team_awarded' => false];
        }
    }
}

