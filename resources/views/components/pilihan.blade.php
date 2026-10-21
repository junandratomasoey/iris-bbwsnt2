@props(['name', 'opsi', 'model' => null, 'jenis' => 'radio', 'kode' => false, 'kolom' => null])
{{-- Pilihan bergaya tombol. $opsi = [nilai => label]. $kode = tampilkan kunci sebagai kode isian. --}}
<div {{ $attributes->merge(['class' => $kolom ? 'grid gap-1.5 '.$kolom : 'flex flex-wrap gap-1.5']) }} role="group">
    @foreach($opsi as $nilai => $label)
        <label class="opsi">
            <input type="{{ $jenis }}" name="{{ $name }}{{ $jenis === 'checkbox' ? '[]' : '' }}" value="{{ $nilai }}" @if($model) x-model="{{ $model }}" @endif>
            @if($kode)<span class="opsi-kode">{{ $nilai }}</span>@endif<span>{{ $label }}</span>
        </label>
    @endforeach
</div>
