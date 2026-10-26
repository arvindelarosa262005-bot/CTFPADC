<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Serves the target website for internal challenges.
 *
 * Each internal challenge has a dedicated target view keyed by its
 * slug (resources/views/challenges/targets/{slug}.blade.php). The
 * canonical flag is never passed to the target view; the flag
 * fragments live only inside the target's HTML, CSS and JavaScript
 * source, which is exactly what participants are meant to inspect.
 */
class ChallengeTargetController extends Controller
{
    public function show(Challenge $challenge): View
    {
        abort_unless($challenge->isLive(), 404, 'This challenge is not available.');
        abort_unless($challenge->target_type === 'internal', 404, 'This challenge does not have an internal target.');

        $view = "challenges.targets.{$challenge->slug}";

        abort_unless(view()->exists($view), 404, 'This challenge target is not available.');

        return view($view, ['challenge' => $challenge]);
    }

    public function verify(Challenge $challenge, Request $request): JsonResponse
    {
        abort_unless($challenge->isLive(), 404, 'This challenge is not available.');

        if ($challenge->slug === 'the-unwritten-algorithm') {
            $candidate = trim((string) $request->input('candidate_output', ''));
            $expected = '}63676__zAcA7Ugk1_fm0w8c84O{wQuS1';

            if (hash_equals($expected, $candidate)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Transformation verified! All algorithm stages and parity conditions satisfied.',
                    'flag' => 'p4dCTF{h1dd3n_l0g1c_r3v34l3d}',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Verification failed. Output mismatch for the challenge sequence.',
            ], 422);
        }

        return response()->json(['success' => false, 'message' => 'Verification not supported for this challenge.'], 400);
    }
}
