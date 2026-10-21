<?php

namespace App\Http\Controllers;

use App\Models\DaerahIrigasi;
use App\Models\Foto;
use App\Models\PintuAir;
use App\Models\SesiPintu;
use App\Models\User;
use App\Services\FotoService;
use App\Services\PintuService;
use App\Support\KodeIsian;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pembacaan debit & TMA pintu air. Satu kiriman = satu kunjungan (sesi) ke satu D.I.
 * Jadwal rutin setiap Senin; di luar itu boleh diisi untuk kejadian khusus (panen, pekerjaan, dll.).
 */
class PembacaanPintuController extends Controller
{
    public function __construct(private FotoService $foto, private PintuService $svc) {}

    public function index(Request $request)
    {
        $u = $request->user();
        $diIds = $u->diIdsTerjangkau();
        $sesi = SesiPintu::with('user:id,name', 'daerahIrigasi:id,nama')->withCount('pembacaan', 'foto')
            ->when($diIds !== null, fn ($q) => $q->whereIn('daerah_irigasi_id', $diIds))
            ->when($request->filled('di'), fn ($q) => $q->where('daerah_irigasi_id', $request->integer('di')))
            ->when($request->filled('jenis'), fn ($q) => $q->where('jenis', $request->input('jenis')))
            ->when($request->boolean('milik_saya'), fn ($q) => $q->where('user_id', $u->id))
            ->latest('tanggal')->latest('id')->paginate(30)->withQueryString();

        return view('pintu.pembacaan.index', [
            'sesi' => $sesi,
            'diList' => DaerahIrigasi::when($diIds !== null, fn ($q) => $q->whereIn('id', $diIds))->orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function create(Request $request)
    {
        $u = $request->user();
        abort_unless($u->boleh('isi-pintu'), 403);
        $diList = $this->diBisaDiisi($u);
        $di = $request->filled('di') ? $diList->firstWhere('id', $request->integer('di')) : ($diList->count() === 1 ? $diList->first() : null);
        if ($request->filled('di') && ! $di) {
            abort(403);
        }

        $sesi = new SesiPintu([
            'tanggal' => today(),
            'jam' => now()->format('H:i'),
            'jenis' => today()->dayOfWeekIso === SesiPintu::HARI_RUTIN ? 'rutin' : null,
            'daerah_irigasi_id' => $di?->id,
        ]);

        return view('pintu.pembacaan.form', ['sesi' => $sesi, 'diList' => $diList, 'di' => $di]
            + ($di ? $this->dataForm($u, $di->id) : ['pintu' => collect(), 'nilai' => [], 'adaTugas' => false]));
    }

    public function store(Request $request)
    {
        $u = $request->user();
        abort_unless($u->boleh('isi-pintu'), 403);
        $di = $this->diBisaDiisi($u)->firstWhere('id', $request->integer('daerah_irigasi_id'));
        abort_unless($di, 403);
        [$data, $baris] = $this->validasi($request, $u, $di->id);

        $sesi = DB::transaction(function () use ($data, $baris, $u, $di) {
            $sesi = SesiPintu::create(collect($data)->only(['tanggal', 'jam', 'jenis', 'catatan'])->all() + ['daerah_irigasi_id' => $di->id, 'user_id' => $u->id]);
            $this->simpanBaris($sesi, $baris);

            return $sesi;
        });
        $this->foto->simpan($sesi, $request->file('foto', []), 'pintu');

        return redirect()->route('pembacaan-pintu.show', $sesi)->with('ok', count($baris).' pintu tercatat untuk D.I. '.$di->nama.'.');
    }

    public function show(Request $request, SesiPintu $sesi)
    {
        $this->bolehLihat($request->user(), $sesi);
        $sesi->load('user:id,name,no_hp', 'daerahIrigasi:id,nama', 'foto', 'pembacaan.pintu.subDaerahIrigasi:id,nama');

        // pembacaan sebelumnya per pintu, untuk melihat naik/turun
        $sebelum = [];
        foreach ($sesi->pembacaan as $b) {
            $sebelum[$b->pintu_air_id] = $b->pintu->pembacaan()->where(fn ($q) => $q->where('tanggal', '<', $sesi->tanggal->toDateString())
                ->orWhere(fn ($w) => $w->where('tanggal', $sesi->tanggal->toDateString())->where('sesi_pintu_id', '<', $sesi->id)))
                ->orderByDesc('tanggal')->orderByDesc('id')->first();
        }

        return view('pintu.pembacaan.show', [
            'sesi' => $sesi,
            'baris' => $sesi->pembacaan->sortBy(fn ($b) => [$b->pintu->sub_daerah_irigasi_id === null ? 0 : 1, $b->pintu->sub_daerah_irigasi_id, $b->pintu->urutan, $b->pintu->kode])->values(),
            'sebelum' => $sebelum,
            'bolehUbah' => $this->bolehUbah($request->user(), $sesi),
        ]);
    }

    public function edit(Request $request, SesiPintu $sesi)
    {
        $u = $request->user();
        abort_unless($this->bolehUbah($u, $sesi), 403);
        $sesi->load('foto', 'pembacaan');
        $di = $sesi->daerahIrigasi()->first(['id', 'nama']);
        $form = $this->dataForm($u, $di->id, $sesi);

        return view('pintu.pembacaan.form', ['sesi' => $sesi, 'diList' => collect([$di]), 'di' => $di] + $form);
    }

    public function update(Request $request, SesiPintu $sesi)
    {
        $u = $request->user();
        abort_unless($this->bolehUbah($u, $sesi), 403);
        [$data, $baris] = $this->validasi($request, $u, $sesi->daerah_irigasi_id, $sesi);

        DB::transaction(function () use ($sesi, $data, $baris) {
            $sesi->update(collect($data)->only(['tanggal', 'jam', 'jenis', 'catatan'])->all());
            $sesi->pembacaan()->delete();
            $this->simpanBaris($sesi, $baris);
        });
        $sisa = SesiPintu::MAKS_FOTO - $sesi->foto()->count();
        $this->foto->simpan($sesi, array_slice($request->file('foto', []), 0, max(0, $sisa)), 'pintu');

        return redirect()->route('pembacaan-pintu.show', $sesi)->with('ok', 'Pembacaan diperbarui.');
    }

    public function destroy(Request $request, SesiPintu $sesi)
    {
        abort_unless($this->bolehUbah($request->user(), $sesi), 403);
        $di = $sesi->daerah_irigasi_id;
        $sesi->delete();

        return redirect()->route('pintu.index', ['di' => $di])->with('ok', 'Pembacaan dihapus.');
    }

    public function hapusFoto(Request $request, SesiPintu $sesi, Foto $foto)
    {
        abort_unless($this->bolehUbah($request->user(), $sesi), 403);
        abort_unless($foto->fotoable_type === $sesi->getMorphClass() && $foto->fotoable_id === $sesi->id, 404);
        $foto->delete();

        return back()->with('ok', 'Foto dihapus.');
    }

    // ---------- aturan ----------

    /** D.I. yang punya pintu aktif dalam jangkauan isian user. */
    private function diBisaDiisi(User $u)
    {
        $ids = $this->svc->bolehIsi($u)->where('aktif', true)->distinct()->pluck('daerah_irigasi_id');

        return DaerahIrigasi::whereIn('id', $ids)->orderBy('nama')->get(['id', 'nama']);
    }

    /** Pintu yang boleh diisi user di D.I. ini + nilai awal form. */
    private function dataForm(User $u, int $diId, ?SesiPintu $sesi = null): array
    {
        $ada = $sesi ? $sesi->pembacaan->keyBy('pintu_air_id') : collect();
        $pintu = $this->svc->bolehIsi($u, $diId)->with('subDaerahIrigasi:id,nama', 'pembacaanTerakhir', 'petugas:id,name')
            ->where(fn ($q) => $q->where('aktif', true)->when($ada->isNotEmpty(), fn ($w) => $w->orWhereIn('id', $ada->keys())))
            ->urut()->get();
        $kelola = $u->bolehKelolaPintu($diId);
        $pintu->each(function (PintuAir $p) use ($u, $kelola) {
            $p->setAttribute('tugas_saya', $p->petugas->contains('id', $u->id));
            $p->setAttribute('boleh_titik', ! $p->lat || $kelola || $p->tugas_saya);
        });

        $nilai = [];
        foreach ($pintu as $p) {
            $b = $ada->get($p->id);
            $nilai[$p->id] = [
                'debit' => old("baca.{$p->id}.debit", $b?->debit_lps),
                'tma' => old("baca.{$p->id}.tma", $b?->tma_cm),
                'bukaan' => old("baca.{$p->id}.bukaan", $b?->bukaan_cm),
                'catatan' => old("baca.{$p->id}.catatan", $b?->catatan),
                'kondisi_pintu' => old("baca.{$p->id}.kondisi_pintu", $b?->kondisi_pintu),
                'kondisi_saluran' => old("baca.{$p->id}.kondisi_saluran", $b?->kondisi_saluran),
            ];
        }

        return ['pintu' => $pintu, 'nilai' => $nilai, 'adaTugas' => $pintu->contains('tugas_saya', true)];
    }

    /** @return array{0: array, 1: array<int, array>} data sesi & baris pembacaan yang terisi */
    private function validasi(Request $request, User $u, int $diId, ?SesiPintu $sesi = null): array
    {
        $bolehId = $this->svc->bolehIsi($u, $diId)->pluck('id')->all();
        $sisaFoto = SesiPintu::MAKS_FOTO - ($sesi?->foto()->count() ?? 0);
        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.($u->boleh('kelola-laporan') ? '2000-01-01' : today()->subDays(30)->toDateString())],
            'jam' => ['nullable', 'date_format:H:i'],
            'jenis' => ['required', Rule::in(array_keys($sesi?->jenis === 'harian' ? SesiPintu::JENIS : SesiPintu::jenisPilihan()))],
            'catatan' => [Rule::requiredIf(fn () => $request->input('jenis') === 'lainnya'), 'nullable', 'string', 'max:2000'],
            'baca' => ['nullable', 'array'],
            'baca.*.debit' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'baca.*.tma' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'baca.*.bukaan' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'baca.*.catatan' => ['nullable', 'string', 'max:255'],
            'baca.*.kondisi_pintu' => ['nullable', Rule::in(array_keys(KodeIsian::KONDISI_PINTU))],
            'baca.*.kondisi_saluran' => ['nullable', Rule::in(array_keys(KodeIsian::KONDISI_SALURAN))],
            'baca.*.lat' => ['nullable', 'required_with:baca.*.lng', 'numeric', 'between:-12,-7'],
            'baca.*.lng' => ['nullable', 'required_with:baca.*.lat', 'numeric', 'between:118,126'],
            'baca.*.akurasi' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'foto' => ['nullable', 'array', 'max:'.max(0, $sisaFoto)],
            'foto.*' => ['image', 'max:8192'],
        ], [
            'tanggal.before_or_equal' => 'Tanggal tidak boleh melewati hari ini.',
            'tanggal.after_or_equal' => 'Pembacaan lebih dari 30 hari lalu tidak bisa diisi lagi.',
            'jenis.required' => 'Pilih alasan pembacaan.',
            'catatan.required' => 'Jelaskan alasan pembacaan di catatan.',
            'foto.max' => 'Maksimal '.SesiPintu::MAKS_FOTO.' foto per pembacaan.',
            'baca.*.debit.numeric' => 'Debit harus angka.',
            'baca.*.tma.numeric' => 'TMA harus angka.',
            'baca.*.lat.between' => 'Titik GPS di luar wilayah NTT.',
            'baca.*.lng.between' => 'Titik GPS di luar wilayah NTT.',
        ]);

        $baris = [];
        $titik = [];
        foreach ($data['baca'] ?? [] as $id => $r) {
            $isi = collect($r)->only(['debit', 'tma', 'bukaan'])->filter(fn ($v) => $v !== null && $v !== '');
            $adaTitik = is_numeric($r['lat'] ?? null) && is_numeric($r['lng'] ?? null);
            if ($isi->isEmpty() && ! $adaTitik) {
                continue;
            }
            abort_unless(in_array((int) $id, $bolehId, true), 403, 'Pintu di luar tugas Anda.');
            if ($isi->isNotEmpty()) {
                $baris[(int) $id] = $r;
            }
            if ($adaTitik) {
                $titik[(int) $id] = $r;
            }
        }
        if (! $baris && ! $titik) {
            throw ValidationException::withMessages(['baca' => 'Isi debit atau TMA minimal satu pintu.']);
        }
        $this->simpanTitik($u, $titik);
        if (! $baris) {
            throw new HttpResponseException(
                redirect()->route('pintu.index', ['di' => $diId])->with('ok', count($titik).' titik pintu disimpan (tanpa pembacaan).'));
        }

        return [$data, $baris];
    }

    /** Titik pintu dari GPS petugas di lapangan. */
    private function simpanTitik(User $u, array $titik): void
    {
        if (! $titik) {
            return;
        }
        foreach (PintuAir::whereIn('id', array_keys($titik))->get() as $p) {
            if (! $this->svc->bolehTitik($u, $p)) {
                continue;
            }
            $r = $titik[$p->id];
            $p->update([
                'lat' => round((float) $r['lat'], 7), 'lng' => round((float) $r['lng'], 7),
                'titik_oleh' => $u->id, 'titik_pada' => now(),
                'titik_akurasi_m' => is_numeric($r['akurasi'] ?? null) ? (int) min(65535, round($r['akurasi'])) : null,
            ]);
        }
    }

    private function simpanBaris(SesiPintu $sesi, array $baris): void
    {
        $angka = fn ($v) => $v === null || $v === '' ? null : (float) $v;
        foreach ($baris as $id => $r) {
            $sesi->pembacaan()->create([
                'pintu_air_id' => $id, 'tanggal' => $sesi->tanggal,
                'debit_lps' => $angka($r['debit'] ?? null), 'tma_cm' => $angka($r['tma'] ?? null), 'bukaan_cm' => $angka($r['bukaan'] ?? null),
                'catatan' => $r['catatan'] ?? null,
                'kondisi_pintu' => ($r['kondisi_pintu'] ?? null) ?: null, 'kondisi_saluran' => ($r['kondisi_saluran'] ?? null) ?: null,
            ]);
        }
    }

    private function bolehLihat(User $u, SesiPintu $sesi): void
    {
        $ids = $u->diIdsTerjangkau();
        abort_unless($sesi->user_id === $u->id || $ids === null || in_array($sesi->daerah_irigasi_id, $ids, true), 403);
    }

    /** Pengisi sendiri selama belum lewat batas ubah, atau pengelola pintu D.I. tersebut. */
    private function bolehUbah(User $u, SesiPintu $sesi): bool
    {
        if ($sesi->dariLaporan()) {
            return false; // diubah lewat laporan PPA / Manager Air-nya
        }

        $diIds = $u->diIdsTerjangkau();
        $pengelolaLaporan = $u->boleh('kelola-laporan') && ($diIds === null || in_array($sesi->daerah_irigasi_id, $diIds, true));

        return $pengelolaLaporan || $u->bolehKelolaPintu($sesi->daerah_irigasi_id)
            || ($sesi->user_id === $u->id && $u->boleh('isi-pintu') && ! $sesi->terkunci());
    }
}
