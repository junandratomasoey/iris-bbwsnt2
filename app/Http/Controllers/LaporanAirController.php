<?php

namespace App\Http\Controllers;

use App\Models\DaerahIrigasi;
use App\Models\Foto;
use App\Models\LaporanAir;
use App\Models\LaporanHarian;
use App\Models\PintuAir;
use App\Models\SesiPintu;
use App\Models\SubDaerahIrigasi;
use App\Models\User;
use App\Services\FotoService;
use App\Support\KodeIsian;
use App\Support\Role;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Laporan Manager Air (Format Pengisian Manager Air): bangunan utama (bendung/intake), titik ukur saluran primer,
 * rekap laporan PPA, permasalahan. Rutin tiap Senin atau insidental; satu per D.I. per tanggal.
 */
class LaporanAirController extends Controller
{
    public function __construct(private FotoService $foto) {}

    public function index(Request $request)
    {
        $u = $request->user();
        $diIds = $u->diIdsTerjangkau();
        $laporan = LaporanAir::with('daerahIrigasi:id,nama', 'user:id,name', 'sesiPintu.pembacaan')->withCount('foto')
            ->when($diIds !== null, fn ($q) => $q->whereIn('daerah_irigasi_id', $diIds))
            ->when($request->filled('di'), fn ($q) => $q->where('daerah_irigasi_id', $request->integer('di')))
            ->when($request->filled('dari'), fn ($q) => $q->where('tanggal', '>=', $request->date('dari')->toDateString()))
            ->when($request->filled('sampai'), fn ($q) => $q->where('tanggal', '<=', $request->date('sampai')->toDateString()))
            ->latest('tanggal')->latest('id')->paginate(30)->withQueryString();
        $senin = today()->startOfWeek();

        return view('laporan-air.index', [
            'laporan' => $laporan,
            'diList' => DaerahIrigasi::when($diIds !== null, fn ($q) => $q->whereIn('id', $diIds))->orderBy('nama')->get(['id', 'nama']),
            'senin' => $senin,
            // laporan pekan ini (rutin tiap Senin) per D.I. tugas
            'pekanIni' => $u->boleh('lapor-air') ? $this->diBoleh($u)->map(fn ($d) => ['di' => $d, 'laporan' => LaporanAir::where('daerah_irigasi_id', $d->id)
                ->whereBetween('tanggal', [$senin->toDateString(), today()->toDateString()])->latest('tanggal')->first()]) : collect(),
        ]);
    }

    public function create(Request $request)
    {
        $u = $request->user();
        abort_unless($u->boleh('lapor-air'), 403);
        $diList = $this->diBoleh($u);
        abort_if($diList->isEmpty(), 403, 'Anda belum ditugaskan ke D.I. mana pun.');
        $di = $request->filled('di') ? $diList->firstWhere('id', $request->integer('di')) : ($diList->count() === 1 ? $diList->first() : null);

        $laporan = new LaporanAir([
            'tanggal' => today(), 'jam' => now()->format('H:i'), 'jenis' => today()->isMonday() ? 'rutin' : 'insidental',
            'daerah_irigasi_id' => $di?->id, 'mt' => $di ? DaerahIrigasi::find($di->id)?->mtBerjalan(today())?->mt : null,
        ]);
        $sebelumnya = $di ? LaporanAir::where('daerah_irigasi_id', $di->id)->latest('tanggal')->first() : null;
        if ($sebelumnya) {
            $laporan->fill($sebelumnya->only(['status_pemberian', 'atas_perintah', 'kondisi_bendung', 'kondisi_pintu_intake', 'kondisi_kantong_lumpur', 'lat', 'lng']));
        }

        return view('laporan-air.form', $this->dataForm($laporan, $diList) + ['sebelumnya' => $sebelumnya]);
    }

    public function store(Request $request)
    {
        $u = $request->user();
        abort_unless($u->boleh('lapor-air'), 403);
        [$data, $titik] = $this->validasi($request, $u);
        $laporan = DB::transaction(function () use ($data, $titik, $u) {
            $laporan = LaporanAir::create($data + ['user_id' => $u->id]);
            $this->simpanTitik($laporan, $titik);

            return $laporan;
        });
        $this->foto->simpan($laporan, $request->file('foto', []), 'laporan-air', $laporan->lat, $laporan->lng);

        return redirect()->route('laporan-air.show', $laporan)->with('ok', 'Laporan Manager Air '.$laporan->tanggal->translatedFormat('d M Y').' terkirim.');
    }

    public function show(Request $request, LaporanAir $laporan_air)
    {
        $l = $laporan_air;
        $this->bolehLihat($request->user(), $l);
        $l->load('daerahIrigasi:id,nama,kabupaten_id,luas_baku_ha,luas_permen_ha', 'daerahIrigasi.kabupaten:id,nama', 'user:id,name,no_hp', 'foto', 'sesiPintu.pembacaan.pintu');
        $riwayat = LaporanAir::where('daerah_irigasi_id', $l->daerah_irigasi_id)->where('tanggal', '<=', $l->tanggal->toDateString())
            ->latest('tanggal')->limit(14)->get()->reverse()->values();

        return view('laporan-air.show', [
            'laporan' => $l, 'riwayat' => $riwayat, 'bolehUbah' => $this->bolehUbah($request->user(), $l),
            'rekapPpa' => $this->rekapPpa($l->daerah_irigasi_id, $l->tanggal),
        ]);
    }

    public function edit(Request $request, LaporanAir $laporan_air)
    {
        abort_unless($this->bolehUbah($request->user(), $laporan_air), 403);
        $laporan_air->load('foto', 'sesiPintu.pembacaan.pintu', 'user');
        $diList = $this->diBoleh($request->user())
            ->push($laporan_air->daerahIrigasi()->with('kabupaten:id,nama')->first(['id', 'nama', 'lat', 'lng', 'kabupaten_id', 'luas_baku_ha', 'luas_permen_ha']))
            ->unique('id')->values();

        return view('laporan-air.form', $this->dataForm($laporan_air, $diList) + ['sebelumnya' => null]);
    }

    public function update(Request $request, LaporanAir $laporan_air)
    {
        $u = $request->user();
        abort_unless($this->bolehUbah($u, $laporan_air), 403);
        [$data, $titik] = $this->validasi($request, $u, $laporan_air);
        DB::transaction(function () use ($laporan_air, $data, $titik) {
            $laporan_air->update($data);
            $this->simpanTitik($laporan_air, $titik);
        });
        $sisa = LaporanAir::MAKS_FOTO - $laporan_air->foto()->count();
        $this->foto->simpan($laporan_air, array_slice($request->file('foto', []), 0, max(0, $sisa)), 'laporan-air', $laporan_air->lat, $laporan_air->lng);

        return redirect()->route('laporan-air.show', $laporan_air)->with('ok', 'Laporan Manager Air diperbarui.');
    }

    public function destroy(Request $request, LaporanAir $laporan_air)
    {
        abort_unless($this->bolehUbah($request->user(), $laporan_air), 403);
        $laporan_air->delete();

        return redirect()->route('laporan-air.index')->with('ok', 'Laporan Manager Air dihapus.');
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

        return DaerahIrigasi::with('kabupaten:id,nama')->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('nama')
            ->get(['id', 'nama', 'lat', 'lng', 'kabupaten_id', 'luas_baku_ha', 'luas_permen_ha']);
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

    /** @return array{0: array, 1: array<int, array>} [data laporan, titik ukur primer per pintu] */
    private function validasi(Request $request, User $u, ?LaporanAir $l = null): array
    {
        $diIds = $this->diBoleh($u)->pluck('id')->push($l?->daerah_irigasi_id)->filter()->unique()->values()->all();
        $batasMundur = $u->boleh('kelola-laporan') ? '2000-01-01' : today()->subDays(7)->toDateString();
        $sisaFoto = LaporanAir::MAKS_FOTO - ($l?->foto()->count() ?? 0);
        $tgl = rescue(fn () => Carbon::parse($request->input('tanggal'))->toDateString(), (string) $request->input('tanggal'), false);
        $cm = ['nullable', 'numeric', 'min:-1000', 'max:100000'];
        $positif = ['nullable', 'numeric', 'min:0', 'max:1000000'];

        $data = $request->validate([
            'daerah_irigasi_id' => ['required', Rule::in($diIds),
                Rule::unique('laporan_air')->where(fn ($q) => $q->where('tanggal', $tgl))->ignore($l?->id)],
            'tanggal' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$batasMundur],
            'jam' => ['nullable', 'date_format:H:i'],
            'jenis' => ['required', Rule::in(array_keys(KodeIsian::JENIS_LAPOR))],
            'alasan' => ['nullable', 'required_if:jenis,insidental', Rule::in(array_keys(KodeIsian::ALASAN_MANAGER))],
            'alasan_lain' => ['nullable', 'required_if:alasan,lainnya', 'string', 'max:150'],
            'mt' => ['nullable', Rule::in(array_keys(KodeIsian::MT))],
            'cuaca' => ['nullable', Rule::in(array_keys(KodeIsian::CUACA))],
            'curah_hujan_mm' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'tma_sungai_cm' => ['required', 'numeric', 'min:-1000', 'max:100000'],
            'debit_sungai_m3s' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'melimpas' => ['nullable', 'boolean'],
            'tinggi_limpasan_cm' => $positif,
            'bukaan_intake_cm' => $positif,
            'tma_intake_cm' => $cm,
            'debit_intake_lps' => $positif,
            'bukaan_penguras_cm' => $positif,
            'kondisi_bendung' => ['nullable', Rule::in(array_keys(KodeIsian::KONDISI_BENDUNG))],
            'kondisi_pintu_intake' => ['nullable', Rule::in(array_keys(KodeIsian::KONDISI_PINTU))],
            'kondisi_kantong_lumpur' => ['nullable', Rule::in(array_keys(KodeIsian::KANTONG_LUMPUR))],
            'titik' => ['nullable', 'array'],
            'titik.*.tma' => $cm,
            'titik.*.debit' => $positif,
            'titik.*.kebutuhan' => $positif,
            'titik.*.kondisi_saluran' => ['nullable', Rule::in(array_keys(KodeIsian::KONDISI_SALURAN))],
            'titik.*.catatan' => ['nullable', 'string', 'max:255'],
            'status_pemberian' => ['nullable', Rule::in(array_keys(KodeIsian::PEMBERIAN_AIR))],
            'atas_perintah' => ['nullable', 'string', 'max:150'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'usulan' => ['nullable', 'string', 'max:2000'],
            'dokumentasi' => ['nullable', 'array'],
            'dokumentasi.*' => [Rule::in(array_keys(KodeIsian::DOKUMENTASI_MANAGER))],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-12,-7'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:118,126'],
            'foto' => ['nullable', 'array', 'max:'.max(0, $sisaFoto)],
            'foto.*' => ['image', 'max:8192'],
        ], [
            'daerah_irigasi_id.in' => 'D.I. ini bukan wilayah tugas Anda.',
            'daerah_irigasi_id.unique' => 'D.I. ini sudah punya laporan Manager Air pada tanggal tersebut. Buka laporan itu untuk mengubahnya.',
            'tanggal.after_or_equal' => 'Laporan hanya bisa diisi untuk 7 hari terakhir.',
            'alasan.required_if' => 'Pilih alasan laporan insidental.',
            'alasan_lain.required_if' => 'Tulis alasan lainnya.',
            'tma_sungai_cm.required' => 'Isi TMA hulu bendung (cm, dari papan duga).',
            'lat.between' => 'Lintang harus antara -12 dan -7 (wilayah NTT).',
            'lng.between' => 'Bujur harus antara 118 dan 126 (wilayah NTT).',
            'foto.max' => 'Maksimal '.LaporanAir::MAKS_FOTO.' foto per laporan.',
        ], [
            'daerah_irigasi_id' => 'D.I.', 'tma_sungai_cm' => 'TMA hulu bendung', 'debit_sungai_m3s' => 'debit sungai',
            'debit_intake_lps' => 'debit masuk saluran induk', 'tma_intake_cm' => 'TMA hilir intake', 'curah_hujan_mm' => 'curah hujan',
            'tinggi_limpasan_cm' => 'tinggi limpasan', 'bukaan_intake_cm' => 'bukaan pintu intake', 'bukaan_penguras_cm' => 'bukaan pintu penguras',
        ]);

        $data['mt'] = $data['mt'] ?? $this->mt($data);
        $data['alasan'] = $data['jenis'] === 'insidental' ? ($data['alasan'] ?? null) : null;
        $data['alasan_lain'] = $data['alasan'] === 'lainnya' ? ($data['alasan_lain'] ?? null) : null;
        $data['melimpas'] = $request->filled('melimpas') ? $request->boolean('melimpas') : null;
        $data['tinggi_limpasan_cm'] = $data['melimpas'] ? ($data['tinggi_limpasan_cm'] ?? null) : null;
        $data['dokumentasi'] = array_values($data['dokumentasi'] ?? []);

        // titik ukur: hanya pintu primer D.I. ini
        $boleh = PintuAir::where('daerah_irigasi_id', $data['daerah_irigasi_id'])->where('tingkat', 'primer')->pluck('id')->all();
        $angka = fn ($v) => $v === null || $v === '' ? null : (float) $v;
        $titik = [];
        foreach ($data['titik'] ?? [] as $id => $r) {
            if (! in_array((int) $id, $boleh, true)) {
                continue;
            }
            $b = [
                'tma_cm' => $angka($r['tma'] ?? null), 'debit_lps' => $angka($r['debit'] ?? null), 'debit_kebutuhan_lps' => $angka($r['kebutuhan'] ?? null),
                'kondisi_saluran' => ($r['kondisi_saluran'] ?? null) ?: null, 'catatan' => ($r['catatan'] ?? null) ?: null,
                'bukaan_cm' => null, 'kondisi_pintu' => null,
            ];
            if ($b['tma_cm'] === null && $b['debit_lps'] === null && ! $b['kondisi_saluran']) {
                continue; // titik belum dibaca (debit kebutuhan saja tidak dihitung)
            }
            $titik[(int) $id] = $b;
        }
        unset($data['titik'], $data['foto']);

        return [$data, $titik];
    }

    /** Data form: titik ukur saluran primer & pintu intake per D.I., isian titik (ubah/old), rekap PPA. */
    private function dataForm(LaporanAir $laporan, $diList): array
    {
        $lama = $laporan->sesiPintu?->pembacaan->pluck('pintu_air_id')->all() ?? [];
        $pintu = PintuAir::whereIn('daerah_irigasi_id', $diList->pluck('id'))->whereIn('tingkat', ['utama', 'primer'])
            ->where(fn ($q) => $q->where('aktif', true)->orWhereIn('id', $lama))->urut()->get();
        $titik = $pintu->where('tingkat', 'primer')->groupBy('daerah_irigasi_id')->map(fn ($g) => $g->map(fn ($p) => [
            'id' => $p->id, 'kode' => $p->kode, 'nama' => $p->nama, 'rencana' => $p->debit_rencana_lps,
            'tmin' => $p->tma_min_cm, 'tmaks' => $p->tma_maks_cm, 'dmin' => $p->debit_min_lps, 'dmaks' => $p->debit_maks_lps,
        ])->values()->all())->all();
        $intake = $pintu->where('tingkat', 'utama')->groupBy('daerah_irigasi_id')->map(fn ($g) => $g->first()->kode)->all();
        $isi = $laporan->sesiPintu?->pembacaan->where('pintu.tingkat', '!=', 'utama')->mapWithKeys(fn ($b) => [$b->pintu_air_id => [
            'tma' => $b->tma_cm, 'debit' => $b->debit_lps, 'kebutuhan' => $b->debit_kebutuhan_lps, 'kondisi_saluran' => $b->kondisi_saluran, 'catatan' => $b->catatan,
        ]])->all() ?? [];
        $rekap = $diList->mapWithKeys(fn ($d) => [$d->id => $this->rekapPpa($d->id, $laporan->tanggal ?? today())])->all();

        return ['laporan' => $laporan, 'diList' => $diList, 'titik' => $titik, 'intake' => $intake, 'isiTitik' => $isi, 'rekap' => $rekap];
    }

    /**
     * Bagian D: per Sub-D.I. — PPA yang bertugas, laporan masuk pekan itu (Senin–Minggu), jumlah pintu bermasalah
     * (rusak/hilang atau saluran tidak normal) dan petak kekurangan air (KR/KS/KB).
     */
    private function rekapPpa(int $diId, Carbon $tanggal): array
    {
        $senin = $tanggal->copy()->startOfWeek();
        $akhir = $senin->copy()->addDays(6);
        $subs = SubDaerahIrigasi::where('daerah_irigasi_id', $diId)
            ->with(['petugas' => fn ($q) => $q->where('role', Role::PETUGAS)])->orderBy('nama')->get();
        $laporan = LaporanHarian::with('petak', 'sesiPintu.pembacaan', 'user:id,name')->whereIn('sub_daerah_irigasi_id', $subs->pluck('id'))
            ->whereBetween('tanggal', [$senin->toDateString(), $akhir->toDateString()])->get()->groupBy('sub_daerah_irigasi_id');

        return [
            'senin' => $senin->translatedFormat('d M'), 'akhir' => $akhir->translatedFormat('d M Y'),
            'sub' => $subs->map(function ($s) use ($laporan) {
                $l = $laporan->get($s->id, collect());
                $baca = $l->flatMap(fn ($x) => $x->sesiPintu?->pembacaan ?? collect());

                return [
                    'nama' => $s->nama,
                    'ppa' => $s->petugas->pluck('name')->merge($l->pluck('user.name'))->filter()->unique()->values()->implode(', '),
                    'masuk' => $l->count(),
                    'pintu_bermasalah' => $baca->filter->bermasalah()->pluck('pintu_air_id')->unique()->count(),
                    'petak_kurang_air' => $l->flatMap->petak->whereIn('kecukupan_air', ['KR', 'KS', 'KB'])->pluck('pintu_air_id')->unique()->count(),
                    'url' => $l->isNotEmpty() ? route('laporan.show', $l->sortBy(fn ($x) => $x->tanggal->toDateString())->last()) : null,
                ];
            })->values()->all(),
        ];
    }

    /** Titik ukur primer + pintu intake → pembacaan pintu (sesi jenis "air") yang terhubung ke laporan; lalu hitung status. */
    private function simpanTitik(LaporanAir $l, array $titik): void
    {
        $intake = PintuAir::where('daerah_irigasi_id', $l->daerah_irigasi_id)->where('tingkat', 'utama')->where('aktif', true)->urut()->first();
        if ($intake && ($l->debit_intake_lps !== null || $l->tma_intake_cm !== null || $l->bukaan_intake_cm !== null)) {
            $titik[$intake->id] = [
                'debit_lps' => $l->debit_intake_lps, 'tma_cm' => $l->tma_intake_cm, 'bukaan_cm' => $l->bukaan_intake_cm,
                'kondisi_pintu' => $l->kondisi_pintu_intake, 'debit_kebutuhan_lps' => null, 'kondisi_saluran' => null, 'catatan' => null,
            ];
        }
        $sesi = $l->sesiPintu()->first();
        if (! $titik) {
            $sesi?->delete();
        } else {
            $sesi ??= new SesiPintu(['laporan_air_id' => $l->id, 'user_id' => $l->user_id, 'jenis' => 'air']);
            $sesi->fill(['daerah_irigasi_id' => $l->daerah_irigasi_id, 'tanggal' => $l->tanggal, 'jam' => $l->jam ?? now()->format('H:i'), 'lat' => $l->lat, 'lng' => $l->lng])->save();
            $sesi->pembacaan()->delete();
            foreach ($titik as $id => $r) {
                $sesi->pembacaan()->create(['pintu_air_id' => $id, 'tanggal' => $l->tanggal] + $r);
            }
        }
        $l->unsetRelation('sesiPintu');
        $l->forceFill(['status' => $l->hitungStatus()])->save();
    }

    private function mt(array $data): ?int
    {
        return DaerahIrigasi::find($data['daerah_irigasi_id'])?->mtBerjalan(Carbon::parse($data['tanggal']))?->mt;
    }
}
