<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Administrative challenge management.
 *
 * Only administrators may create, edit, activate, deactivate or
 * delete challenges. The canonical flag is write-only: it is never
 * rendered back into any admin view, so it cannot leak through the
 * challenge management HTML or JavaScript.
 */
class AdminChallengeController extends Controller
{
    // Authorization is enforced by the `role:admin` middleware on the
    // /admin route group in routes/web.php (Laravel 12 base controllers
    // do not provide a $this->middleware() helper).

    public function index(): View
    {
        $challenges = Challenge::query()
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get();

        $activeCount = $challenges->where('is_active', true)->count();
        $hiddenCount = $challenges->where('is_active', false)->count();

        return view('admin.challenges.index', [
            'challenges' => $challenges,
            'activeCount' => $activeCount,
            'hiddenCount' => $hiddenCount,
            'categories' => config('ctf.categories'),
            'difficulties' => config('ctf.difficulties'),
        ]);
    }

    public function create(): View
    {
        return view('admin.challenges.create', [
            'categories' => config('ctf.categories'),
            'difficulties' => config('ctf.difficulties'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateChallenge($request);

        $data = $this->prepareChallengeData($validated);

        // A newly active challenge is published immediately.
        if ($data['is_active'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        Challenge::create($data);

        return redirect()
            ->route('admin.challenges.index')
            ->with('status', 'Challenge created.');
    }

    public function edit(Challenge $challenge): View
    {
        return view('admin.challenges.edit', [
            'challenge' => $challenge,
            'categories' => config('ctf.categories'),
            'difficulties' => config('ctf.difficulties'),
        ]);
    }

    public function update(Request $request, Challenge $challenge): RedirectResponse
    {
        $validated = $this->validateChallenge($request, $challenge);

        $data = $this->prepareChallengeData($validated, $challenge);

        // An active challenge must be published. Keep any existing
        // published_at timestamp so solve ordering is preserved.
        if ($data['is_active'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $challenge->update($data);

        return redirect()
            ->route('admin.challenges.index')
            ->with('status', 'Challenge updated.');
    }

    public function toggle(Challenge $challenge): RedirectResponse
    {
        $newActive = ! (bool) $challenge->is_active;

        $challenge->update([
            'is_active' => $newActive,
            'published_at' => $newActive ? ($challenge->published_at ?? now()) : $challenge->published_at,
        ]);

        if ($newActive) {
            return back()->with('status', "🚀 '{$challenge->title}' has been RELEASED and is now LIVE for all contestants!");
        }

        return back()->with('status', "🔒 '{$challenge->title}' has been HIDDEN from contestants.");
    }

    public function bulkRelease(): RedirectResponse
    {
        Challenge::query()->update([
            'is_active' => true,
            'published_at' => now(),
        ]);

        return back()->with('status', '🚀 All challenges have been RELEASED and are now LIVE to all contestants!');
    }

    public function bulkHide(): RedirectResponse
    {
        Challenge::query()->update([
            'is_active' => false,
        ]);

        return back()->with('status', '🔒 All challenges have been HIDDEN from contestants (Staged mode).');
    }

    public function destroy(Challenge $challenge): RedirectResponse
    {
        // Deleting cascades to verified solves and would silently remove
        // awarded points, so challenges with solves must be deactivated.
        if ($challenge->verifiedSolves()->exists()) {
            return redirect()
                ->route('admin.challenges.index')
                ->withErrors(['challenge' => 'This challenge has verified solves and cannot be deleted. Deactivate it instead.']);
        }

        $challenge->delete();

        return redirect()
            ->route('admin.challenges.index')
            ->with('status', 'Challenge deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateChallenge(Request $request, ?Challenge $challenge = null): array
    {
        $slugRule = $challenge === null
            ? 'unique:challenges,slug'
            : 'unique:challenges,slug,' . $challenge->id;

        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:140', 'alpha_dash', $slugRule],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'in:' . implode(',', array_keys(config('ctf.categories')))],
            'difficulty' => ['required', 'string', 'in:' . implode(',', array_keys(config('ctf.difficulties')))],
            'points' => ['required', 'integer', 'min:0'],
            'target_type' => ['required', 'string', 'in:internal,external'],
            'target_url' => [
                $request->input('target_type') === 'external' ? 'required' : 'nullable',
                'string',
                'max:500',
                'url',
                'regex:/^https?:\/\//i',
            ],
            'is_active' => ['sometimes', 'boolean'],
            'flag' => ['nullable', 'string', 'max:255', 'regex:/^[^\{\}\s]*\{[^\{\}\r\n]+\}$/'],
            'hints' => ['nullable', 'array', 'max:3'],
            'hints.*' => ['nullable', 'string', 'max:500'],
        ], [
            'target_url.regex' => 'The target URL must use the http or https scheme.',
            'flag.regex' => 'The flag must use the PREFIX{value} format.',
        ]);
    }

    /**
     * Normalise validated input into model data. The canonical flag
     * is handled separately so it is only ever written when a value
     * is supplied — it is never read back into a form.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function prepareChallengeData(array $validated, ?Challenge $challenge = null): array
    {
        $hints = [];
        if (isset($validated['hints']) && is_array($validated['hints'])) {
            $hints = array_values(array_filter(array_map('strval', $validated['hints']), fn ($hint) => trim($hint) !== ''));
        }

        $data = [
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'difficulty' => $validated['difficulty'],
            'points' => (int) $validated['points'],
            'target_type' => $validated['target_type'],
            'target_url' => $validated['target_url'] ?? null,
            'is_active' => ! empty($validated['is_active']),
            'hints' => $hints,
        ];

        // Write-only flag handling: set it on create, or replace it
        // on update only when a new value is provided.
        if ($challenge === null) {
            $data['flag'] = $validated['flag'] ?? null;
        } elseif (filled($validated['flag'])) {
            $data['flag'] = $validated['flag'];
        }

        return $data;
    }
}
