@php use App\Support\StatusKondisi as SK; @endphp
<x-layouts.app title="Neraca air & pemerataan">
<x-judul judul="Neraca air & pemerataan{{ $di ? ' · D.I. '.$di->nama : '' }}" sub="Mencocokkan debit bendung → primer → sekunder → tersier, dan membandingkan air yang diterima tiap petak tersier.">
    @if($di)<a href="{{ route('pintu.index', ['di' => $di->id]) }}" class="btn-ghost"><x-ikon n="pintu" class="h-4 w-4"/> Pemantauan pintu</a>@endif
</x-judul>

<form method="GET" class="panel mb-4 flex flex-wrap items-end gap-3 p-3">
    <label class="min-w-[14rem] flex-1"><span class="label">D.I.</span>
        <select name="di" class="input" onchange="this.form.submit()">@foreach($diList as $d)<option value="{{ $d->id }}" @selected($di?->id === $d->id)>{{ $d->nama }} ({{ $d->pintu_air_count }} pintu)</option>@endforeach</select></label>
    <label class="min-w-[14rem]"><span class="label">Minggu</span>
        <select name="minggu" class="input" onchange="this.form.submit()">
            @foreach($mingguList as $m)<option value="{{ $m->toDateString() }}" @selected($m->isSameDay($senin))>{{ $m->translatedFormat('d M') }} – {{ $m->copy()->addDays(6)->translatedFormat('d M Y') }}{{ $loop->first ? ' (minggu ini)' : '' }}</option>@endforeach
        </select></label>
    <noscript><button class="btn-primary">Tampilkan</button></noscript>
</form>

@if(! $di)
    <div class="panel"><x-kosong judul="Belum ada D.I. dengan pintu air">Tambahkan pintu air beserta tingkat dan pintu hulunya terlebih dahulu.</x-kosong></div>
@else
<p class="mb-4 text-sm text-basalt-soft">Memakai pembacaan <b>terakhir</b> tiap pintu pada {{ $n['senin']->translatedFormat('d M') }} – {{ $n['akhir']->translatedFormat('d M Y') }} · {{ $n['dibaca'] }} dari {{ $n['total'] }} pintu sudah dibaca.
    Angka ditampilkan apa adanya; batas selisih yang masih wajar ditetapkan Bidang OP.</p>

<section class="panel mb-6 overflow-hidden">
    <h2 class="border-b border-savana-line px-4 py-3 text-lg font-semibold">Neraca air per cabang</h2>
    @if(! $n['neraca'])
        <p class="px-4 py-6 text-sm text-basalt-soft">Belum ada pintu yang punya pintu di bawahnya. Atur <b>pintu hulu</b> di halaman ubah pintu agar neraca bisa dihitung.</p>
    @else
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Pintu hulu</th><th class="text-right">Debit masuk</th><th>Pintu di bawahnya</th><th class="text-right">Jumlah debit keluar</th><th class="text-right">Selisih</th><th>Kelengkapan</th></tr></thead>
                <tbody>
                @foreach($n['neraca'] as $r)
                    <tr>
                        <td class="min-w-[9rem]"><a href="{{ route('pintu.show', $r['pintu']) }}" class="font-semibold text-saluran-600">{{ $r['pintu']->kode }}</a><span class="block text-xs text-basalt-soft">{{ $r['pintu']->tingkat_label }}</span></td>
                        <td class="whitespace-nowrap text-right font-semibold">{{ $r['debit'] !== null ? angka_r($r['debit']).' L/dt' : '–' }}</td>
                        <td class="min-w-[14rem] text-xs">
                            @foreach($r['anak'] as $a)
                                <span class="mr-2 inline-block whitespace-nowrap"><a href="{{ route('pintu.show', $a['pintu']) }}" class="font-medium text-saluran-600">{{ $a['pintu']->kode }}</a> {{ $a['debit'] !== null ? angka_r($a['debit']) : '–' }}</span>
                            @endforeach
                        </td>
                        <td class="whitespace-nowrap text-right">{{ $r['jumlah_anak'] !== null ? angka_r($r['jumlah_anak']).' L/dt' : '–' }}</td>
                        <td class="whitespace-nowrap text-right">
                            @if($r['selisih'] !== null)
                                <span class="font-semibold">{{ $r['selisih'] >= 0 ? '' : '−' }}{{ angka_r(abs($r['selisih'])) }} L/dt</span>
                                @if($r['persen'] !== null)<span class="block text-xs text-basalt-soft">{{ $r['persen'] >= 0 ? '' : '−' }}{{ angka_r(abs($r['persen'])) }}% dari debit masuk</span>@endif
                            @else – @endif
                        </td>
                        <td class="text-xs">@if($r['lengkap'])<span class="chip-aman">Lengkap</span>@else<span class="chip-waspada">{{ $r['debit'] === null ? 'Hulu belum dibaca' : $r['terbaca'].'/'.$r['total'].' pintu dibaca' }}</span>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="border-t border-savana-line px-4 py-2 text-xs text-basalt-mute">Selisih positif = air masuk lebih banyak daripada yang terbaca keluar (rembesan, penguapan, pengambilan tanpa pintu, atau salah baca). Selisih negatif biasanya menandakan salah baca atau pintu yang belum terdaftar. Bandingkan hanya bila kelengkapan "Lengkap".</p>
    @endif
</section>

<section class="panel overflow-hidden">
    <h2 class="border-b border-savana-line px-4 py-3 text-lg font-semibold">Pemerataan air ke petak tersier</h2>
    @if(! $n['merata'])
        <p class="px-4 py-6 text-sm text-basalt-soft">Belum ada pintu bertingkat <b>tersier</b> di D.I. ini.</p>
    @else
        <p class="px-4 pt-3 text-sm">Rata-rata D.I.: <b>{{ $n['rata_per_ha'] !== null ? angka_r($n['rata_per_ha']).' L/dt/Ha' : '–' }}</b> (jumlah debit petak terbaca ÷ jumlah luas layanannya). Diurutkan dari yang menerima paling sedikit.</p>
        <div class="overflow-x-auto">
            <table class="table-base mt-2">
                <thead><tr><th>Petak / pintu tersier</th><th class="text-right">Luas layanan</th><th class="text-right">Debit</th><th class="text-right">Dibanding rencana</th><th class="text-right">L/dt/Ha</th><th class="min-w-[12rem]">Dibanding rata-rata D.I.</th><th>Kondisi tanaman (laporan PPA)</th></tr></thead>
                <tbody>
                @foreach($n['merata'] as $r)
                    @php $p = $r['pintu']; $l = $r['laporan']; @endphp
                    <tr>
                        <td><a href="{{ route('pintu.show', $p) }}" class="font-semibold text-saluran-600">{{ $p->kode }}</a>
                            <span class="block text-xs text-basalt-soft">{{ $p->subDaerahIrigasi?->nama }}{{ $p->petugas->isNotEmpty() ? ' · '.$p->petugas->pluck('name')->implode(', ') : '' }}</span></td>
                        <td class="whitespace-nowrap text-right">{{ $r['luas'] ? angka_r($r['luas']).' Ha' : '–' }}</td>
                        <td class="whitespace-nowrap text-right">{{ $r['debit'] !== null ? angka_r($r['debit']).' L/dt' : '–' }} <x-status-pintu :s="$r['status_debit']"/></td>
                        <td class="whitespace-nowrap text-right">@if($r['persen_rencana'] !== null)<span class="font-semibold">{{ $r['persen_rencana'] }}%</span><span class="block text-[11px] text-basalt-mute">rencana {{ angka_r($r['rencana']) }} L/dt</span>@elseif($r['rencana'])<span class="text-xs text-basalt-mute">rencana {{ angka_r($r['rencana']) }} L/dt</span>@else – @endif</td>
                        <td class="text-right font-semibold">{{ $r['per_ha'] !== null ? angka_r($r['per_ha']) : '–' }}</td>
                        <td>
                            @if($r['rasio'] !== null)
                                <div class="flex items-center gap-2">
                                    <div class="relative h-2.5 w-28 rounded-full bg-savana-deep" aria-hidden="true">
                                        <div class="absolute inset-y-0 left-0 rounded-full {{ $r['rasio'] < 100 ? 'bg-[#D9622B]' : 'bg-[#3D7DB3]' }}" style="width: {{ min(100, $r['rasio'] / 2) }}%"></div>
                                        <div class="absolute inset-y-[-3px] left-1/2 w-px bg-basalt"></div>
                                    </div>
                                    <span class="text-xs font-semibold">{{ $r['rasio'] }}%</span>
                                </div>
                            @else<span class="text-xs text-basalt-mute">{{ $r['luas'] ? 'Belum dibaca minggu ini' : 'Luas layanan belum diisi' }}</span>@endif
                        </td>
                        <td class="text-xs">
                            @if($l)
                                <a href="{{ route('laporan.show', $l) }}" class="inline-flex items-center gap-1"><x-status :s="$r['status_lahan']"/></a>
                                @php $b = $r['petak']; @endphp
                                <span class="block text-basalt-soft">{{ $l->tanggal->translatedFormat('d M') }}{{ $b->kecukupan_air ? ' · air '.$b->kecukupan_air : '' }} · kering {{ angka_r($b->luas_kering_ha) }}/{{ angka_r($b->luas_tanam_ha) }} Ha{{ $b->luas_puso_ha > 0 ? ' · puso '.angka_r($b->luas_puso_ha).' Ha' : '' }}</span>
                            @else<span class="text-basalt-mute">Belum ada laporan minggu ini</span>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="border-t border-savana-line px-4 py-2 text-xs text-basalt-mute">100% = sama dengan rata-rata D.I. Petak dengan persentase rendah dan laporan tanaman kering adalah calon prioritas pengaturan bukaan pintu.</p>
    @endif
</section>
@endif
</x-layouts.app>
