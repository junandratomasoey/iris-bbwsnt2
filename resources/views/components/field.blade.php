@props(['name', 'label', 'hint' => null, 'wajib' => false])
<div {{ $attributes }}>
    <label for="{{ $name }}" class="label">{{ $label }}@if($wajib)<span class="text-status-kritis"> *</span>@endif</label>
    {{ $slot }}
    @if($hint)<p class="mt-1 text-xs text-basalt-mute">{{ $hint }}</p>@endif
    @error($name)<p class="mt-1 text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
</div>
