@php
    $ukuran = fn ($b) => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.').' MB' : number_format($b / 1024, 0, ',', '.').' KB';
@endphp
<x-layouts.app title="Cadangan data">
<x-judul judul="Cadangan data" sub="Cadangan database dibuat otomatis setiap hari pukul 01.30 WITA (14 terakhir disimpan) dan foto setiap Minggu pukul 02.00 (4 terakhir disimpan). File disimpan di server pada folder yang tidak bisa dibuka dari web.">
    <form method="POST" action="{{ route('cadangan.store') }}">@csrf<input type="hidden" name="jenis" value="db"><button class="btn-primary"><x-ikon n="unduh" class="h-4 w-4"/> Buat cadangan database sekarang</button></form>
    <form method="POST" action="{{ route('cadangan.store') }}" onsubmit="return confirm('Cadangan foto bisa berukuran besar dan butuh beberapa menit. Lanjutkan?')">@csrf<input type="hidden" name="jenis" value="foto"><button class="btn-ghost">Cadangkan foto</button></form>
</x-judul>

@error('jenis')<div class="mb-4 rounded-md bg-[#FBECEA] px-4 py-3 text-sm text-[#7E1A14]">{{ $message }}</div>@enderror

<div class="mb-5 grid gap-4 md:grid-cols-2">
    <section class="panel p-5">
        <h2 class="text-lg font-semibold">Status</h2>
        @if($terakhir)
            <p class="mt-2 text-sm">Cadangan database terakhir: <b>{{ $terakhir['waktu']->translatedFormat('l, d M Y H:i') }}</b> ({{ $terakhir['waktu']->diffForHumans() }}), {{ $ukuran($terakhir['ukuran']) }}.</p>
        @else
            <p class="mt-2 text-sm">Belum ada cadangan database.</p>
        @endif
        @if($terlambat)
            <div class="mt-3 rounded-md border border-[#E9B7B3] bg-[#FBECEA] px-3 py-2 text-sm text-[#7E1A14]">
                <p class="font-semibold">Cadangan otomatis belum berjalan (lebih dari 36 jam).</p>
                <p>Pastikan <b>Cron Job</b> di cPanel sudah dipasang seperti di samping.</p>
            </div>
        @else
            <p class="mt-3 chip-aman">Cadangan otomatis berjalan</p>
        @endif
    </section>
    <section class="panel p-5 text-sm">
        <h2 class="text-lg font-semibold">Memasang jadwal otomatis (sekali saja)</h2>
        <p class="mt-1 text-basalt-soft">cPanel → <b>Cron Jobs</b> → Common Settings: <i>Once Per Minute</i>, lalu isi Command:</p>
        <pre class="mt-2 overflow-x-auto rounded-md bg-basalt px-3 py-2 text-xs text-white">cd {{ dirname($artisan) }} && php artisan schedule:run >> /dev/null 2>&1</pre>
        <p class="mt-2 text-xs text-basalt-mute">Bila hosting tidak mengizinkan cron tiap menit, buat dua cron: harian 01:30 <code>php {{ $artisan }} iris:cadangan</code> dan mingguan <code>php {{ $artisan }} iris:cadangan --foto --tanpa-db</code>.</p>
    </section>
</div>

<section class="panel mb-5 overflow-x-auto">
    @if($daftar->isEmpty())
        <x-kosong judul="Belum ada file cadangan">Tekan "Buat cadangan database sekarang" untuk membuat yang pertama.</x-kosong>
    @else
        <table class="table-base">
            <thead><tr><th>File</th><th>Jenis</th><th>Dibuat</th><th class="text-right">Ukuran</th><th></th></tr></thead>
            <tbody>
            @foreach($daftar as $f)
                <tr>
                    <td class="font-mono text-xs">{{ $f['nama'] }}</td>
                    <td>{{ $f['jenis'] === 'db' ? 'Database' : 'Foto' }}</td>
                    <td class="whitespace-nowrap">{{ $f['waktu']->translatedFormat('d M Y H:i') }}</td>
                    <td class="whitespace-nowrap text-right">{{ $ukuran($f['ukuran']) }}</td>
                    <td class="whitespace-nowrap text-right">
                        <a href="{{ route('cadangan.unduh', $f['nama']) }}" class="font-semibold text-saluran-600">Unduh</a>
                        <form method="POST" action="{{ route('cadangan.destroy', $f['nama']) }}" class="ml-3 inline" onsubmit="return confirm('Hapus file cadangan ini?')">@csrf @method('DELETE')<button class="text-xs text-basalt-soft hover:text-status-kritis">Hapus</button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</section>

<div class="grid gap-4 md:grid-cols-2">
    <section class="panel p-5 text-sm">
        <h2 class="text-lg font-semibold">Salinan di luar server (disarankan)</h2>
        <p class="mt-1">Cadangan yang hanya ada di server ikut hilang bila hosting bermasalah. Simpan salinannya di laptop atau NAS kantor:</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Tambahkan baris <code>CADANGAN_TOKEN=</code> diikuti 40+ karakter acak ke file <b>.env</b> di server. Status: <b>{{ $adaToken ? 'token sudah diisi' : 'belum diisi (unduhan otomatis nonaktif)' }}</b>.</li>
            <li><a href="{{ route('cadangan.skrip') }}" class="font-semibold text-saluran-600">Unduh skrip PowerShell</a>, isi token dan folder tujuan (mis. folder NAS).</li>
            <li>Jadwalkan skrip lewat <b>Task Scheduler</b> Windows tiap pagi. Skrip menyimpan 60 salinan terakhir.</li>
        </ol>
        <p class="mt-2 text-xs text-basalt-mute">Atau unduh manual dari tabel di atas minimal seminggu sekali.</p>
    </section>
    <section class="panel p-5 text-sm">
        <h2 class="text-lg font-semibold">Memulihkan data</h2>
        <ol class="mt-1 list-decimal space-y-1 pl-5">
            <li><b>Database</b>: buka phpMyAdmin → pilih database IRIS (atau database kosong baru) → tab <b>Import</b> → pilih file <code>.sql.gz</code> → Go. Isi tabel diganti dengan isi cadangan.</li>
            <li><b>Foto</b>: ekstrak file <code>.zip</code>, salin isi folder <code>uploads</code> ke <code>public/uploads</code> di server.</li>
            <li>Setelah memulihkan: <code>php artisan cache:clear</code>.</li>
        </ol>
        <p class="mt-2 text-xs text-basalt-mute">Sebaiknya uji pemulihan ke database percobaan sesekali, agar yakin cadangan bisa dipakai.</p>
    </section>
</div>
</x-layouts.app>
