{{--
    Shared challenge form for create / edit.

    The canonical flag field is intentionally write-only. It is
    never pre-filled with the stored flag, so the canonical flag
    is never rendered into admin HTML or JavaScript. On edit,
    leaving it blank keeps the current flag unchanged.
--}}
<div class="challenge-form">
    <div class="form-field">
        <label for="title">Title</label>
        <input id="title" name="title" type="text" maxlength="120" required value="{{ old('title', $challenge->title ?? '') }}">
        @error('title')<span class="field-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-field">
        <label for="slug">Slug</label>
        <input id="slug" name="slug" type="text" maxlength="140" required value="{{ old('slug', $challenge->slug ?? '') }}">
        <small class="field-hint">URL-friendly identifier. Internal challenges are served at /challenges/{slug}/target.</small>
        @error('slug')<span class="field-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4">{{ old('description', $challenge->description ?? '') }}</textarea>
        <small class="field-hint">Shown to participants. Do not reveal the flag or the solution here.</small>
        @error('description')<span class="field-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-row-2">
        <div class="form-field">
            <label for="category">Category</label>
            <select id="category" name="category" required>
                @foreach ($categories as $key => $label)
                    <option value="{{ $key }}" @selected(old('category', $challenge->category ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('category')<span class="field-error">{{ $message }}</span>@enderror
        </div>

        <div class="form-field">
            <label for="difficulty">Difficulty</label>
            <select id="difficulty" name="difficulty" required>
                @foreach ($difficulties as $key => $label)
                    <option value="{{ $key }}" @selected(old('difficulty', $challenge->difficulty ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('difficulty')<span class="field-error">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="form-row-2">
        <div class="form-field">
            <label for="points">Points</label>
            <input id="points" name="points" type="number" min="0" step="1" required value="{{ old('points', $challenge->points ?? 0) }}">
            @error('points')<span class="field-error">{{ $message }}</span>@enderror
        </div>

        <div class="form-field">
            <label for="target_type">Target type</label>
            <select id="target_type" name="target_type" required>
                <option value="internal" @selected(old('target_type', $challenge->target_type ?? 'internal') === 'internal')>Internal (served by P4DC)</option>
                <option value="external" @selected(old('target_type', $challenge->target_type ?? '') === 'external')>External (https URL)</option>
            </select>
            @error('target_type')<span class="field-error">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="form-field">
        <label for="target_url">Target URL <small>(external only)</small></label>
        <input id="target_url" name="target_url" type="url" maxlength="500" placeholder="https://" value="{{ old('target_url', $challenge->target_url ?? '') }}">
        <small class="field-hint">Required when the target type is external. Must use http or https. The application never fetches this URL.</small>
        @error('target_url')<span class="field-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-field">
        <label for="flag">Canonical flag <small>(write-only)</small></label>
        <input id="flag" name="flag" type="text" maxlength="255" autocomplete="off" spellcheck="false" placeholder="{{ isset($challenge) && $challenge->exists ? 'Leave blank to keep the current flag' : 'PREFIX{value}' }}" value="{{ old('flag') }}">
        <small class="field-hint">The exact flag participants must submit. It is stored encrypted and is never shown again after saving.</small>
        @error('flag')<span class="field-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-field">
        <label>Hints <small>(up to 3, revealed progressively)</small></label>
        @for ($i = 0; $i < 3; $i++)
            @php
                $existingHints = old('hints', $challenge->hints ?? []);
                $existingHints = is_array($existingHints) ? array_values($existingHints) : [];
            @endphp
            <textarea name="hints[]" rows="2" maxlength="500" placeholder="Hint {{ $i + 1 }} (optional)">{{ $existingHints[$i] ?? '' }}</textarea>
        @endfor
        @error('hints')<span class="field-error">{{ $message }}</span>@enderror
        @error('hints.*')<span class="field-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-field form-field-check">
        <label>
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $challenge->is_active ?? false))>
            Active (publish to participants)
        </label>
        @error('is_active')<span class="field-error">{{ $message }}</span>@enderror
    </div>
</div>
