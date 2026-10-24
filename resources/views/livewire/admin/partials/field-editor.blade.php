@props([
    'path',
    'field',
    'schema' => [],
    'value' => null,
])

@php
    $isArray = is_array($value) || (($schema['type'] ?? null) === 'array');
    $jsonKey = hash('sha256', str($path)->after('payload.')->toString());
    $label = str($field)->headline();
@endphp

<div class="rounded-xl border border-border bg-surface p-4">
    <label class="admin-label" for="field-{{ $jsonKey }}">
        {{ $label }}
        @if ($schema['required'] ?? false)
            <span class="text-danger" aria-label="required">*</span>
        @endif
    </label>
    @if (filled($schema['description'] ?? null))
        <p class="mb-2 text-xs text-text-muted">{{ $schema['description'] }}</p>
    @endif

    @if ($isArray)
        <textarea
            id="field-{{ $jsonKey }}"
            class="admin-input min-h-40 font-mono text-xs"
            wire:model="jsonFields.{{ $jsonKey }}"
        ></textarea>
        <button type="button" class="admin-button admin-button-quiet mt-2" wire:click="applyJsonField('{{ $jsonKey }}')">
            Apply structured field
        </button>
        @if (isset($media) && $media->isNotEmpty() && in_array($field, ['image', 'images', 'before', 'after', 'poster', 'video'], true))
            <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                <select class="admin-input" wire:model="selectedMedia.{{ $jsonKey }}" aria-label="Select media for {{ $label }}">
                    <option value="">Select library media</option>
                    @foreach ($media as $item)
                        <option value="{{ $item->id }}">#{{ $item->id }} — {{ $item->title ?: $item->original_name }}</option>
                    @endforeach
                </select>
                <button type="button" class="admin-button admin-button-quiet" wire:click="insertMedia('{{ $jsonKey }}')">Insert media</button>
            </div>
            @error('selectedMedia.'.$jsonKey) <p class="admin-error">{{ $message }}</p> @enderror
        @endif
        @error('jsonFields.'.$jsonKey) <p class="admin-error">{{ $message }}</p> @enderror
    @elseif (($schema['type'] ?? null) === 'boolean')
        <input
            id="field-{{ $jsonKey }}"
            type="checkbox"
            wire:model="{{ $path }}"
        >
    @elseif (($schema['type'] ?? null) === 'integer')
        <input
            id="field-{{ $jsonKey }}"
            type="number"
            step="1"
            class="admin-input"
            wire:model.number.blur="{{ $path }}"
        >
    @elseif (in_array($field, ['body', 'description', 'summary', 'problem', 'work', 'outcome', 'transcript'], true))
        <textarea id="field-{{ $jsonKey }}" class="admin-input min-h-28" wire:model.blur="{{ $path }}"></textarea>
    @else
        <input id="field-{{ $jsonKey }}" class="admin-input" wire:model.blur="{{ $path }}">
    @endif
</div>
