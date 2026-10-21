<?php

namespace App\Http\Controllers;

use App\Models\DaerahIrigasi;
use App\Models\Kabupaten;
use App\Models\Kejadian;
use App\Models\LaporanAir;
use App\Models\LaporanHarian;
use App\Models\PintuAir;
use App\Models\SubDaerahIrigasi;
use App\Models\WilayahSatgas;
use App\Services\KondisiService;
use App\Services\PengingatService;
use App\Services\PintuService;
use App\Services\RekapService;
use App\Support\Periode;
use App\Support\Role;
use App\Support\StatusKondisi;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, KondisiService $kondisi)
    {
        $user = $request->user();
        $hari = today();

        if ($user->hasRole(Role::PETUGAS, Role::POB)) {
            $subs = SubDaerahIrigasi::with('daerahIrigasi:id,nama')
                ->whereIn('id', $user->subIdsPelaporan() ?? [])->orderBy('daerah_irigasi_id')->orderBy('nama')->get();
            // laporan PPA pekan ini (rutin tiap Senin) milik petugas ini, per Sub-D.I.
            $senin = $hari->copy()->startOfWeek();
            $pekanIni = LaporanHarian::where('user_id', $user->id)->whereBetween('tanggal', [$senin->toDateString(), $hari->toDateString()])
                ->whereIn('sub_daerah_irigasi_id', $subs->pluck('id'))->orderBy('tanggal')->get()->groupBy('sub_daerah_irigasi_id');
            // PPA melapor per petak tersier yang ditugaskan kepadanya
            $petakSaya = $user->pintuTugas()->where('tingkat', 'tersier')->where('aktif', true)
                ->whereIn('sub_daerah_irigasi_id', $subs->pluck('id'))->orderBy('urutan')->orderBy('kode')->get(['pintu_air.id', 'kode', 'nama', 'sub_daerah_irigasi_id', 'luas_layanan_ha'])
                ->groupBy('sub_daerah_irigasi_id');
            $petakAdaDiSub = PintuAir::tersier()->where('aktif', true)->whereIn('sub_daerah_irigasi_id', $subs->pluck('id'))->distinct()->pluck('sub_daerah_irigasi_id')->flip();
            $riwayat = LaporanHarian::with('subDaerahIrigasi:id,nama')->withCount('petak')->where('user_id', $user->id)
                ->latest('tanggal')->latest('id')->limit(10)->get();

            $svc = app(PintuService::class);
            // pintu yang ditugaskan kepadanya; bila belum ada penugasan, pintu yang bisa ia isi
            $tugas = $user->pintuTugas()->where('aktif', true)->pluck('pintu_air.id')->all();
            $ids = $tugas ?: $svc->bolehIsi($user)->where('aktif', true)->pluck('id')->all();
            $pintu = $user->boleh('isi-pintu') && $ids ? $svc->perDi($svc->daftar(null, null, 2, $ids)) : collect();

            return view('dashboard.petugas', compact('subs', 'pekanIni', 'senin', 'riwayat', 'pintu', 'petakSaya', 'petakAdaDiSub'));
        }

        $subIds = $user->subIdsTerjangkau();
        $periode = Periode::dariRequest($request);
        $menunggu = LaporanHarian::where('status_verifikasi', 'menunggu')
            ->when($subIds !== null, fn ($q) => $q->whereIn('sub_daerah_irigasi_id', $subIds))->count();

        if ($periode->rekap()) {
            $mode = $request->input('warna') === 'tersering' ? 'tersering' : 'terberat';

            return view('dashboard.rekap', [
                'periode' => $periode, 'mode' => $mode, 'menunggu' => $menunggu,
                'r' => app(RekapService::class)->rekap($periode, $subIds),
            ]);
        }

        $ringkasan = $kondisi->ringkasanDi($hari, $subIds);
        $laporan = $kondisi->laporanTerkini($hari, $subIds);

        $perStatus = collect(StatusKondisi::URUTAN)->map(fn ($_, $s) => $ringkasan->where('status', $s)->count());
        $luasPerStatus = collect(StatusKondisi::URUTAN)->map(fn ($_, $s) => $ringkasan->where('status', $s)->sum('luas_ha'));

        $totalSub = $subIds === null ? SubDaerahIrigasi::count() : count($subIds);
        $senin = $hari->copy()->startOfWeek();
        $laporHariIni = LaporanHarian::whereBetween('tanggal', [$senin->toDateString(), $hari->toDateString()])
            ->when($subIds !== null, fn ($q) => $q->whereIn('sub_daerah_irigasi_id', $subIds))->distinct()->count('sub_daerah_irigasi_id');

        $kritis = $laporan->map(fn ($l) => ['laporan' => $l, 'status' => StatusKondisi::dariLaporan($l)])
            ->filter(fn ($x) => in_array($x['status'], [StatusKondisi::SIAGA, StatusKondisi::KRITIS, StatusKondisi::WASPADA]))
            ->sortByDesc(fn ($x) => [StatusKondisi::URUTAN[$x['status']], (float) $x['laporan']->luas_kering_ha])
            ->take(10);
        $kritis->each(fn ($x) => $x['laporan']->load('subDaerahIrigasi.daerahIrigasi:id,nama', 'subDaerahIrigasi.petugas:id,name,no_hp', 'user:id,name,no_hp'));

        // kepatuhan lapor per Satgas
        // kepatuhan lapor per Satgas (+ kabupaten yang belum masuk Satgas, agar jumlahnya sama dengan total)
        $hitungKepatuhan = function (?WilayahSatgas $w) use ($hari, $senin, $subIds) {
            $kab = Kabupaten::where('wilayah_satgas_id', $w?->id)->whereHas('daerahIrigasi')->orderBy('nama')->pluck('nama');
            $ids = SubDaerahIrigasi::whereHas('daerahIrigasi.kabupaten', fn ($k) => $k->where('wilayah_satgas_id', $w?->id))
                ->when($subIds !== null, fn ($q) => $q->whereIn('id', $subIds))->pluck('id');
            $masuk = $ids->isEmpty() ? 0 : LaporanHarian::whereBetween('tanggal', [$senin->toDateString(), $hari->toDateString()])->whereIn('sub_daerah_irigasi_id', $ids)->distinct()->count('sub_daerah_irigasi_id');

            return ['kode' => $w?->kode, 'cakupan' => $w?->cakupan, 'kabupaten' => $kab->implode(', '), 'total' => $ids->count(), 'masuk' => $masuk];
        };
        $kepatuhan = WilayahSatgas::orderBy('id')->get()->map($hitungKepatuhan)
            ->push($hitungKepatuhan(null))
            ->filter(fn ($w) => $w['total'] > 0)->values();

        $kejadian = Kejadian::selectRaw('status_penanganan, count(*) as jumlah, sum(luas_terdampak_ha) as luas, sum(luas_tertangani_ha) as tertangani')
            ->groupBy('status_penanganan')->get()->keyBy('status_penanganan');

        $diIds = $user->diIdsTerjangkau();
        $airHariIni = LaporanAir::whereBetween('tanggal', [$senin->toDateString(), $hari->toDateString()])->when($diIds !== null, fn ($q) => $q->whereIn('daerah_irigasi_id', $diIds))->distinct()->count('daerah_irigasi_id');
        $totalDi = $diIds === null ? DaerahIrigasi::count() : count($diIds);
        $svcPintu = app(PintuService::class);
        $pintu = $svcPintu->hitung($svcPintu->daftar($subIds, null, 2));

        $pengingat = $user->can('lihat-petugas') ? app(PengingatService::class)->pekan(today(), $subIds)['ringkas'] : null;

        return view('dashboard.index', compact(
            'pengingat', 'pintu', 'airHariIni', 'totalDi', 'ringkasan', 'perStatus', 'luasPerStatus', 'totalSub', 'laporHariIni', 'kritis', 'kepatuhan', 'menunggu', 'kejadian', 'hari', 'periode'
        ));
    }
}
