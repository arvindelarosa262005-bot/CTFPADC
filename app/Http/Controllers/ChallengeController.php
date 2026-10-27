<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\VerifiedSolve;
use App\Services\FlagSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\View\View;

class ChallengeController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $challenges = Challenge::query()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('published_at')
            ->orderBy('title')
            ->get();

        $solvedIds = $user->verifiedSolves()->pluck('challenge_id')->all();

        // Get map of challenge_id => first blood details
        $firstBloods = VerifiedSolve::where('is_first_blood', true)
            ->with(['user:id,username,nickname', 'teamAtSolve:id,name'])
            ->get()
            ->keyBy('challenge_id');

        return view('challenges.index', [
            'challenges' => $challenges,
            'solvedIds' => $solvedIds,
            'firstBloods' => $firstBloods,
        ]);
    }

    /**
     * AJAX requests receive the modal markup only; a direct visit to the
     * challenge URL renders the challenges list with the modal open over it.
     */
    public function show(Challenge $challenge, Request $request): View
    {
        abort_unless($challenge->isLive(), 404, 'This challenge is not available.');

        $firstBlood = VerifiedSolve::where('challenge_id', $challenge->id)
            ->where('is_first_blood', true)
            ->with(['user:id,username,nickname', 'teamAtSolve:id,name'])
            ->first();

        $modalData = [
            'challenge' => $challenge,
            'isSolved' => auth()->user()->verifiedSolves()->where('challenge_id', $challenge->id)->exists(),
            'firstBlood' => $firstBlood,
            'targetUrl' => $challenge->resolveTargetUrl(),
            'hints' => $challenge->hintList(),
        ];

        if ($request->ajax()) {
            return view('challenges._modal', $modalData);
        }

        return $this->index()->with(['openChallenge' => $challenge, 'modalData' => $modalData]);
    }

    public function download(Challenge $challenge): BinaryFileResponse
    {
        abort_unless($challenge->isLive() && $challenge->hasAttachment(), 404, 'This file is not available.');

        $path = Storage::disk('public')->path($challenge->attachment_path);
        abort_unless(is_file($path), 404, 'This file is not available.');

        return response()->download($path, $challenge->downloadFilename());
    }

    public function submit(Challenge $challenge, Request $request, FlagSubmissionService $service): RedirectResponse|JsonResponse
    {
        abort_unless($challenge->isLive(), 404, 'This challenge is not available.');

        $validated = $request->validate([
            'flag' => ['required', 'string', 'max:255'],
        ], [
            'flag.required' => 'Enter a flag to submit.',
        ]);

        $telemetry = [
            'is_pasted' => (bool) $request->input('is_pasted', false),
            'duration_seconds' => $request->has('duration_seconds') ? (int) $request->input('duration_seconds') : null,
            'tab_switches' => (int) $request->input('tab_switches', 0),
            'keystrokes' => (int) $request->input('keystrokes', 0),
        ];

        $result = $service->attempt(auth()->user(), $challenge, (string) $validated['flag'], $telemetry);

        if ($request->expectsJson()) {
            $message = match ($result['status']) {
                'first_blood' => "🩸 FIRST BLOOD! You were the first operative to capture this flag! Earned {$result['points_awarded']} points (+{$result['bonus_points']} bonus)!" . ($result['team_awarded'] ? ' Your team was awarded too.' : ''),
                'solved' => "Correct! You earned {$result['points_awarded']} points." . ($result['team_awarded'] ? ' Your team was awarded too.' : ''),
                'already_solved' => 'You already solved this challenge. Points are only awarded once.',
                'ai_flagged' => $result['message'] ?? '🚨 AI violation flagged.',
                'unavailable' => 'This challenge is not currently accepting submissions.',
                default => 'That flag is not correct. Keep investigating.',
            };

            return response()->json(['status' => $result['status'], 'message' => $message]);
        }

        return match ($result['status']) {
            'first_blood' => redirect()
                ->route('challenges.show', ['challenge' => $challenge])
                ->with('status', "🩸 FIRST BLOOD! You were the first operative to capture this flag! Earned {$result['points_awarded']} points (+{$result['bonus_points']} bonus)!" . ($result['team_awarded'] ? ' Your team was awarded too.' : '')),
            'solved' => redirect()
                ->route('challenges.show', ['challenge' => $challenge])
                ->with('status', "Correct! You earned {$result['points_awarded']} points." . ($result['team_awarded'] ? ' Your team was awarded too.' : '')),
            'already_solved' => redirect()
                ->route('challenges.show', ['challenge' => $challenge])
                ->with('status', 'You already solved this challenge. Points are only awarded once.'),
            'ai_flagged' => redirect()
                ->route('challenges.show', ['challenge' => $challenge])
                ->withErrors(['flag' => $result['message'] ?? '🚨 AI violation flagged.']),
            'unavailable' => redirect()
                ->route('challenges.show', ['challenge' => $challenge])
                ->withErrors(['flag' => 'This challenge is not currently accepting submissions.']),
            default => redirect()
                ->route('challenges.show', ['challenge' => $challenge])
                ->withErrors(['flag' => 'That flag is not correct. Keep investigating.']),
        };
    }
}
