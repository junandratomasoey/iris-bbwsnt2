<?php

namespace App\Http\Controllers;

use App\Models\DaerahIrigasi;
use App\Models\Foto;
use App\Models\LaporanAir;
use App\Models\PembacaanPintu;
use App\Models\User;
use App\Services\FotoService;
use App\Support\Role;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Laporan air harian (tugas Manager D.I.): sungai, intake, dan saluran. Satu laporan per D.I. per hari. */
class LaporanAirController extends Controller
{
    public function __construct(private FotoService $foto) {}

    public function index(Request $request)
    {
        $u = $request->user();
        $diIds = $u->diIdsTerjangkau();
        $laporan = LaporanAir::with('daerahIrigasi:id,nama', 'user:id,name')->withCount('foto')
            ->when($diIds !== null, fn ($q) => $q->whereIn('daerah_irigasi_id', $diIds))
            ->when($request->filled('di'), fn ($q) => $q->where('daerah_irigasi_id', $request->integer('di')))
            ->when($request->filled('dari'), fn ($q) => $q->where('tanggal', '>=', $request->date('dari')->toDateString()))
            ->when($request->filled('sampai'), fn ($q) => $q->where('tanggal', '<=', $request->date('sampai')->toDateString()))
            ->latest('tanggal')->latest('id')->paginate(30)->withQueryString();

        return view('laporan-air.index', [
            'laporan' => $laporan,
            'diList' => DaerahIrigasi::when($diIds !== null, fn ($q) => $q->whereIn('id', $diIds))->orderBy('nama')->get(['id', 'nama']),
            'hariIni' => $u->boleh('lapor-air') ? $this->diBoleh($u)->map(fn ($d) => ['di' => $d, 'laporan' => LaporanAir::where('daerah_irigasi_id', $d->id)->whereDate('tanggal', today())->first()]) : collect(),
        ]);
    }

    public function create(Request $request)
    {
        $u = $request->user();
        abort_unless($u->boleh('lapor-air'), 403);
        $diList = $this->diBoleh($u);
        abort_if($diList->isEmpty(), 403, 'Anda belum ditugaskan ke D.I. mana pun.');
        $di = $request->filled('di') ? $diList->firstWhere('id', $request->integer('di')) : ($diList->count() === 1 ? $diList->first() : null);

        $laporan = new LaporanAir(['tanggal' => today(), 'daerah_irigasi_id' => $di?->id]);
        $sebelumnya = $di ? LaporanAir::where('daerah_irigasi_id', $di->id)->latest('tanggal')->first() : null;
        if ($sebelumnya) {
            $laporan->fill($sebelumnya->only(['debit_sungai_m3s', 'tma_sungai_cm', 'debit_intake_lps', 'tma_intake_cm', 'kondisi_saluran', 'bukaan_pintu_persen', 'lat', 'lng']));
        }

        return view('laporan-air.form', ['laporan' => $laporan, 'diList' => $diList, 'sebelumnya' => $sebelumnya, 'intake' => $this->bacaanIntake($diList)]);
    }

    public function store(Request $request)
    {
        $u = $request->user();
        abort_unless($u->boleh('lapor-air'), 403);
        $data = $this->validasi($request, $u);
        $laporan = LaporanAir::create($data + ['user_id' => $u->id, 'mt' => $this->mt($data)]);
        $this->foto->simpan($laporan, $request->file('foto', []), 'laporan-air', $laporan->lat, $laporan->lng);

        return redirect()->route('laporan-air.show', $laporan)->with('ok', 'Laporan air '.$laporan->tanggal->translatedFormat('d M Y').' terkirim.');
    }

    public function show(Request $request, LaporanAir $laporan_air)
    {
        $l = $laporan_air;
        $this->bolehLihat($request->user(), $l);
        $l->load('daerahIrigasi:id,nama', 'user:id,name,no_hp', 'foto');
        $riwayat = LaporanAir::where('daerah_irigasi_id', $l->daerah_irigasi_id)->where('tanggal', '<=', $l->tanggal->toDateString())
            ->latest('tanggal')->limit(14)->get()->reverse()->values();

        return view('laporan-air.show', ['laporan' => $l, 'riwayat' => $riwayat, 'bolehUbah' => $this->bolehUbah($request->user(), $l)]);
    }

    public function edit(Request $request, LaporanAir $laporan_air)
    {
        abort_unless($this->bolehUbah($request->user(), $laporan_air), 403);
        $laporan_air->load('foto');
        $diList = $this->diBoleh($request->user())->push($laporan_air->daerahIrigasi()->first(['id', 'nama', 'lat', 'lng']))->unique('id')->values();

        return view('laporan-air.form', ['laporan' => $laporan_air, 'diList' => $diList, 'sebelumnya' => null, 'intake' => $this->bacaanIntake($diList)]);
    }

    public function update(Request $request, LaporanAir $laporan_air)
    {
        $u = $request->user();
        abort_unless($this->bolehUbah($u, $laporan_air), 403);
        $data = $this->validasi($request, $u, $laporan_air);
        $laporan_air->update($data + ['mt' => $this->mt($data)]);
        $sisa = LaporanAir::MAKS_FOTO - $laporan_air->foto()->count();
        $this->foto->simpan($laporan_air, array_slice($request->file('foto', []), 0, max(0, $sisa)), 'laporan-air', $laporan_air->lat, $laporan_air->lng);

        return redirect()->route('laporan-air.show', $laporan_air)->with('ok', 'Laporan air diperbarui.');
    }

    public function destroy(Request $request, LaporanAir $laporan_air)
    {
        abort_unless($this->bolehUbah($request->user(), $laporan_air), 403);
        $laporan_air->delete();

        return redirect()->route('laporan-air.index')->with('ok', 'Laporan air dihapus.');
    }

    public function hapusFoto(Request $request, LaporanAir $laporan_air, Foto $foto)
    {
        abort_unless($this->bolehUbah($request->user(), $laporan_air), 403);
        abort_unless($foto->fotoable_type === $laporan_air->getMorphClass() && $foto->fotoable_id === $laporan_air->id, 404);
        $foto->delete();

        return back()->with('ok', 'Foto dihapus.');
    }

    // ---------- aturan ----------

    /** D.I. yang boleh dilaporkan user: D.I. dalam jangkauannya. */
    private function diBoleh(User $u)
    {
        if (! $u->boleh('lapor-air')) {
            return collect();
        }
        $ids = $u->diIdsTerjangkau();

        return DaerahIrigasi::when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('nama')->get(['id', 'nama', 'lat', 'lng']);
    }

    private function bolehLihat(User $u, LaporanAir $l): void
    {
        $ids = $u->diIdsTerjangkau();
        abort_unless($l->user_id === $u->id || $ids === null || in_array($l->daerah_irigasi_id, $ids, true), 403);
    }

    private function bolehUbah(User $u, LaporanAir $l): bool
    {
        if ($u->boleh('kelola-laporan')) {
            $ids = $u->diIdsTerjangkau();
            if ($ids === null || in_array($l->daerah_irigasi_id, $ids, true)) {
                return true;
            }
        }

        return $l->user_id === $u->id && $u->boleh('lapor-air') && ! $l->terkunci();
    }

    /** Pembacaan pintu intake (tingkat utama) hari ini per D.I., untuk mengisi debit intake tanpa mengetik ulang. */
    private function bacaanIntake($diList): array
    {
        return PembacaanPintu::with('pintu:id,kode,daerah_irigasi_id,tingkat')
            ->whereHas('pintu', fn ($q) => $q->whereIn('daerah_irigasi_id', collect($diList)->pluck('id'))->where('tingkat', 'utama'))
            ->where('tanggal', '>=', today()->subDays(2)->toDateString())->orderBy('tanggal')->orderBy('id')->get()
            ->groupBy(fn ($b) => $b->pintu->daerah_irigasi_id)
            ->map(fn ($g) => $g->groupBy('pintu_air_id')->map->last()->values()->map(fn ($b) => [
                'kode' => $b->pintu->kode, 'debit' => $b->debit_lps, 'tma' => $b->tma_cm, 'tanggal' => $b->tanggal->translatedFormat('d M'),
            ])->all())->all();
    }

    private function validasi(Request $request, User $u, ?LaporanAir $l = null): array
    {
        $diIds = $this->diBoleh($u)->pluck('id')->push($l?->daerah_irigasi_id)->filter()->unique()->values()->all();
        $batasMundur = $u->boleh('kelola-laporan') ? '2000-01-01' : today()->subDays(7)->toDateString();
        $sisaFoto = LaporanAir::MAKS_FOTO - ($l?->foto()->count() ?? 0);
        $tgl = rescue(fn () => Carbon::parse($request->input('tanggal'))->toDateString(), (string) $request->input('tanggal'), false);

        return $request->validate([
            'daerah_irigasi_id' => ['required', Rule::in($diIds),
                Rule::unique('laporan_air')->where(fn ($q) => $q->where('tanggal', $tgl))->ignore($l?->id)],
            'tanggal' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$batasMundur],
            'debit_sungai_m3s' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'tma_sungai_cm' => ['nullable', 'numeric', 'min:-1000', 'max:100000'],
            'debit_intake_lps' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'tma_intake_cm' => ['nullable', 'numeric', 'min:-1000', 'max:100000'],
            'kondisi_saluran' => ['required', Rule::in(array_keys(\App\Models\LaporanHarian::KONDISI))],
            'bukaan_pintu_persen' => ['nullable', 'integer', 'between:0,100'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-12,-7'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:118,126'],
            'foto' => ['nullable', 'array', 'max:'.max(0, $sisaFoto)],
            'foto.*' => ['image', 'max:8192'],
        ], [
            'daerah_irigasi_id.in' => 'D.I. ini bukan wilayah tugas Anda.',
            'daerah_irigasi_id.unique' => 'D.I. ini sudah punya laporan air pada tanggal tersebut. Buka laporan itu untuk mengubahnya.',
            'tanggal.after_or_equal' => 'Laporan hanya bisa diisi untuk 7 hari terakhir.',
            'lat.between' => 'Lintang harus antara -12 dan -7 (wilayah NTT).',
            'lng.between' => 'Bujur harus antara 118 dan 126 (wilayah NTT).',
            'foto.max' => 'Maksimal '.LaporanAir::MAKS_FOTO.' foto per laporan.',
        ], [
            'daerah_irigasi_id' => 'D.I.', 'debit_sungai_m3s' => 'debit sungai', 'tma_sungai_cm' => 'TMA sungai',
            'debit_intake_lps' => 'debit intake', 'tma_intake_cm' => 'TMA intake', 'kondisi_saluran' => 'kondisi saluran',
        ]);
    }

    private function mt(array $data): ?int
    {
        return DaerahIrigasi::find($data['daerah_irigasi_id'])?->mtBerjalan(Carbon::parse($data['tanggal']))?->mt;
    }
}
