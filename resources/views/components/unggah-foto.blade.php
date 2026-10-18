@props(['maks' => 5, 'name' => 'foto'])
{{-- Pemilih foto: kamera/galeri, dikompres di HP (±300 KB/foto) sebelum dikirim --}}
<div x-data="unggahFoto({ maks: {{ (int) $maks }} })" {{ $attributes }}>
    <div class="flex flex-wrap gap-2">
        <template x-for="(ft, i) in foto" :key="ft.url">
            <div class="relative">
                <img :src="ft.url" alt="Pratinjau foto" class="h-20 w-24 rounded object-cover">
                <span class="absolute bottom-0.5 left-0.5 rounded bg-basalt/70 px-1 text-[10px] text-white" x-text="ft.ukuran + ' KB'"></span>
                <button type="button" @click="hapus(i)" class="absolute -right-1.5 -top-1.5 rounded-full bg-status-kritis p-0.5 text-white" aria-label="Batalkan foto ini"><x-ikon n="tutup" class="h-3.5 w-3.5"/></button>
            </div>
        </template>
        <label x-show="foto.length < maks" class="flex h-20 w-24 cursor-pointer flex-col items-center justify-center gap-1 rounded border-2 border-dashed border-savana-line text-center text-xs text-basalt-soft hover:border-saluran-500">
            <x-ikon n="kamera"/> <span x-text="memproses ? 'Memproses…' : 'Pilih / ambil foto'"></span>
            <input type="file" accept="image/*" multiple class="sr-only" @change="pilih">
        </label>
    </div>
    <input type="file" name="{{ $name }}[]" multiple x-ref="input" class="hidden" aria-hidden="true" tabindex="-1">
    <p class="mt-1 text-xs text-basalt-mute">Maks. <span x-text="maks"></span> foto. Foto otomatis diperkecil sebelum dikirim.</p>
    @error($name)<p class="mt-1 text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
    @error($name.'.*')<p class="mt-1 text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
</div>
