<?php

namespace App\Http\Controllers;

use App\Models\DaerahIrigasi;
use App\Models\PintuAir;
use App\Models\SesiPintu;
use App\Services\PintuService;
use Illuminate\Http\Request;

/** Pemantauan pintu air: debit & TMA terakhir tiap pintu dibanding rentang normal dan pembacaan sebelumnya. */
class PintuAirController extends Controller
{
    public function __construct(private PintuService $svc) {}

    public function index(Request $request)
    {
        $u = $request->user();
        $diIds = $u->diIdsTerjangkau();
        $diList = $this->svc->diList($diIds);
        $di = $request->filled('di') ? $diList->firstWhere('id', $request->integer('di')) : null;
        abort_if($request->filled('di') && ! $di, 403);
        if ($di) {
            $di = DaerahIrigasi::with('skemaJaringan')->find($di->id);
        }

        $pintu = $this->svc->daftar($u->subIdsTerjangkau(), $di?->id);
        $hitung = $this->svc->hitung($pintu);
        $tampil = match ($request->input('saring')) {
            'belum' => $pintu->where('minggu_ini', false),
            'kurang' => $pintu->filter(fn ($p) => $p->status_debit === 'kurang' || $p->status_tma === 'rendah'),
            'lebih' => $pintu->filter(fn ($p) => $p->status_debit === 'berlebih' || $p->status_tma === 'tinggi'),
            default => $pintu,
        };

        return view('pintu.index', [
            'di' => $di,
            'diList' => $diList,
            'hitung' => $hitung,
            'pintu' => $tampil,
            'perDi' => $di ? collect() : $this->svc->perDi($pintu),
            'kelola' => $di && $u->bolehKelolaPintu($di->id),
            'nonaktif' => $di ? PintuAir::where('daerah_irigasi_id', $di->id)->where('aktif', false)->urut()->get() : collect(),
            'senin' => SesiPintu::seninIni(),
            'sesiTerbaru' => SesiPintu::with('user:id,name', 'daerahIrigasi:id,nama')->withCount('pembacaan')
                ->when($diIds !== null, fn ($q) => $q->whereIn('daerah_irigasi_id', $diIds))
                ->when($di, fn ($q) => $q->where('daerah_irigasi_id', $di->id))
                ->latest('tanggal')->latest('id')->limit(8)->get(),
        ]);
    }

    public function neraca(Request $request)
    {
        $u = $request->user();
        $diList = $this->svc->diList($u->diIdsTerjangkau())->where('pintu_air_count', '>', 0)->values();
        $di = $diList->firstWhere('id', $request->integer('di')) ?? ($request->filled('di') ? abort(403) : $diList->first());
        $senin = rescue(fn () => \Carbon\Carbon::parse($request->input('minggu'))->startOfWeek(\Carbon\Carbon::MONDAY), SesiPintu::seninIni(), false);
        if (! $request->filled('minggu') || $senin->gt(today())) {
            $senin = SesiPintu::seninIni();
        }

        return view('pintu.neraca', [
            'di' => $di, 'diList' => $diList,
            'n' => $di ? $this->svc->neraca($di->id, $senin, $u->subIdsTerjangkau()) : null,
            'mingguList' => collect(range(0, 11))->map(fn ($i) => SesiPintu::seninIni()->subWeeks($i)),
            'senin' => $senin,
        ]);
    }

    public function show(Request $request, PintuAir $pintu)
    {
        $u = $request->user();
        $ids = $u->diIdsTerjangkau();
        abort_unless($ids === null || in_array($pintu->daerah_irigasi_id, $ids, true), 403);
        if ($pintu->sub_daerah_irigasi_id && ! $u->bolehLihatSub($pintu->sub_daerah_irigasi_id) && ! $u->bolehKelolaPintu($pintu->daerah_irigasi_id)) {
            abort(403);
        }
        $pintu->load('daerahIrigasi:id,nama', 'subDaerahIrigasi:id,nama', 'bangunan:id,nama');
        $riwayat = $pintu->pembacaan()->with('sesi:id,jenis,user_id,jam', 'sesi.user:id,name')
            ->orderByDesc('tanggal')->orderByDesc('id')->limit(104)->get();

        return view('pintu.show', [
            'p' => $pintu,
            'riwayat' => $riwayat,
            'grafik' => $riwayat->take(26)->reverse()->values(),
            'kelola' => $u->bolehKelolaPintu($pintu->daerah_irigasi_id),
        ]);
    }
}
