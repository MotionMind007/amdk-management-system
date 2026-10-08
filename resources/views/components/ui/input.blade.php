@props(['label', 'name', 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null])

<div class="grid gap-1.5">
    <label for="{{ $name }}" class="text-sm font-medium text-slate-700">
        {{ $label }} @if ($required)<span class="text-red-600">*</span>@endif
    </label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @required($required)
        {{ $attributes->merge(['class' => 'min-h-11 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400']) }}
    >
    @if ($hint)<p class="text-xs text-slate-500">{{ $hint }}</p>@endif
    @error($name)<p class="text-sm text-red-600">{{ $message }}</p>@enderror
</div>
