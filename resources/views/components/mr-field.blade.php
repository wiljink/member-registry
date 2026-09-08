@props([
    'name',
    'label',
    'value' => null,
    'type' => 'text',
    'options' => [],
    'hint' => null,
    'readonly' => false,
    'placeholder' => null,
    'step' => null,
])

@php
    $current = old($name, $value);
    $id = $name . '-' . \Illuminate\Support\Str::random(4);
@endphp

<div class="mr-f">
    <label class="mr-f-label" for="{{ $id }}">{{ $label }}</label>

    @if ($readonly)
        <div class="mr-input mr-readonly">{{ $current !== null && $current !== '' ? $current : '—' }}</div>
    @elseif ($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" class="mr-select @error($name) mr-invalid @enderror">
            <option value="">— select —</option>
            @foreach ($options as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $current === (string) $optValue)>{{ $optLabel }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="2" class="mr-input @error($name) mr-invalid @enderror" placeholder="{{ $placeholder }}">{{ $current }}</textarea>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}"
               value="{{ $type === 'date' && $current ? \Illuminate\Support\Str::of($current)->before('T')->before(' ') : $current }}"
               @if ($step) step="{{ $step }}" @endif
               placeholder="{{ $placeholder }}"
               class="mr-input @error($name) mr-invalid @enderror">
    @endif

    @error($name)
        <div class="mr-f-err">{{ $message }}</div>
    @enderror
    @if ($hint)
        <div class="mr-f-hint">{{ $hint }}</div>
    @endif
</div>
