<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Bangunan;
use App\Models\DaerahIrigasi;
use App\Models\PintuAir;
use App\Models\SkemaJaringan;
use App\Models\SubDaerahIrigasi;
use App\Models\User;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Daftar pintu air per D.I. (tambah banyak sekaligus, ubah, hapus) dan file skema jaringan. */
class PintuAirController extends Controller
{
    public function create(Request $request)
    {
        $di = DaerahIrigasi::findOrFail($request->integer('di'));
        $this->izinkan($request, $di->id);

        return view('pintu.master.tambah', [
            'di' => $di,
            'subList' => $di->subDaerahIrigasi()->get(['id', 'nama']),
            'kodeAda' => $di->pintuAir()->orderBy('kode')->pluck('kode'),
            'huluList' => $this->huluList($di->id),
            'urutanBerikut' => (int) $di->pintuAir()->max('urutan') + 1,
        ]);
    }

    public function store(Request $request)
    {
        $di = DaerahIrigasi::findOrFail($request->integer('daerah_irigasi_id'));
        $this->izinkan($request, $di->id);

        // baris kosong (tanpa kode) diabaikan
        $baris = collect($request->input('pintu', []))->filter(fn ($r) => filled($r['kode'] ?? null))->values()->all();
        $request->merge(['pintu' => $baris]);
        $aturan = ['pintu' => ['required', 'array', 'min:1', 'max:100']];
        foreach ($this->aturanPintu($di) as $k => $v) {
            $aturan['pintu.*.'.$k] = $v;
        }
        $aturan['pintu.*.kode'][] = 'distinct:ignore_case';
        $data = $request->validate($aturan, $this->pesan() + [
            'pintu.required' => 'Isi minimal satu pintu (kolom kode wajib).',
            'pintu.*.kode.distinct' => 'Kode pintu ganda di isian.',
            'pintu.*.kode.unique' => 'Kode :input sudah dipakai di D.I. ini.',
        ]);

        foreach ($data['pintu'] as $i => $r) {
            if (! empty($r['hulu_id']) && ! $this->huluSah((int) $r['hulu_id'], $r['tingkat'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(["pintu.$i.hulu_id" => 'Pintu hulu harus setingkat atau lebih ke hulu.']);
            }
        }
        $urutan = (int) $di->pintuAir()->max('urutan');
        foreach ($data['pintu'] as $r) {
            PintuAir::create($this->rapikan($r) + ['daerah_irigasi_id' => $di->id, 'urutan' => ++$urutan]);
        }

        return redirect()->route('pintu.index', ['di' => $di->id])->with('ok', count($data['pintu']).' pintu air ditambahkan ke D.I. '.$di->nama.'.');
    }

    public function edit(Request $request, PintuAir $pintu)
    {
        $this->izinkan($request, $pintu->daerah_irigasi_id);
        $pintu->load('daerahIrigasi:id,nama', 'titikOleh:id,name');

        return view('pintu.master.ubah', [
            'p' => $pintu,
            'subList' => SubDaerahIrigasi::where('daerah_irigasi_id', $pintu->daerah_irigasi_id)->orderBy('nama')->get(['id', 'nama']),
            'bangunanList' => Bangunan::where('daerah_irigasi_id', $pintu->daerah_irigasi_id)->orderBy('nama')->get(['id', 'nama', 'jenis']),
            'huluList' => $this->huluList($pintu->daerah_irigasi_id, $pintu),
            'petugasList' => $this->calonPetugas($pintu->daerah_irigasi_id),
            'tugas' => $pintu->petugas()->get()->mapWithKeys(fn ($u) => [$u->pivot->peran => $u->id]),
            'jumlahBacaan' => $pintu->pembacaan()->count(),
        ]);
    }

    public function update(Request $request, PintuAir $pintu)
    {
        $this->izinkan($request, $pintu->daerah_irigasi_id);
        $di = $pintu->daerahIrigasi;
        $data = $request->validate($this->aturanPintu($di, $pintu) + [
            'bangunan_id' => ['nullable', Rule::exists('bangunan', 'id')->where('daerah_irigasi_id', $di->id)],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['boolean'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-12,-7'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:118,126'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'tugas_utama' => ['nullable', Rule::in($this->calonPetugas($di->id)->pluck('id'))],
            'tugas_pengganti' => ['nullable', 'different:tugas_utama', Rule::in($this->calonPetugas($di->id)->pluck('id'))],
        ], $this->pesan() + ['tugas_utama.in' => 'Petugas tidak bertugas di D.I. ini.', 'tugas_pengganti.in' => 'Petugas tidak bertugas di D.I. ini.', 'tugas_pengganti.different' => 'Pengganti harus orang lain.']);
        $this->cekHulu($request, $pintu, $data['hulu_id'] ?? null);
        $isi = $this->rapikan($data);
        if (($isi['lat'] ?? null) != $pintu->lat || ($isi['lng'] ?? null) != $pintu->lng) {
            $isi += ['titik_oleh' => $request->user()->id, 'titik_pada' => now(), 'titik_akurasi_m' => null];
        }
        $pintu->update($isi + ['aktif' => $request->boolean('aktif'), 'urutan' => (int) ($data['urutan'] ?? $pintu->urutan)]);
        $this->simpanTugas($pintu, $data['tugas_utama'] ?? null, $data['tugas_pengganti'] ?? null);

        return redirect()->route('pintu.show', $pintu)->with('ok', "Pintu {$pintu->kode} diperbarui.");
    }

    public function destroy(Request $request, PintuAir $pintu)
    {
        $this->izinkan($request, $pintu->daerah_irigasi_id);
        if ($pintu->pembacaan()->exists()) {
            $pintu->update(['aktif' => false]);

            return redirect()->route('pintu.index', ['di' => $pintu->daerah_irigasi_id])
                ->with('ok', "Pintu {$pintu->kode} sudah punya data pembacaan, jadi dinonaktifkan (riwayatnya tetap tersimpan).");
        }
        $pintu->delete();

        return redirect()->route('pintu.index', ['di' => $pintu->daerah_irigasi_id])->with('ok', "Pintu {$pintu->kode} dihapus.");
    }

    // ---------- penugasan petugas per pintu ----------

    public function penugasan(Request $request)
    {
        $di = DaerahIrigasi::findOrFail($request->integer('di'));
        $this->izinkan($request, $di->id);

        return view('pintu.master.penugasan', [
            'di' => $di,
            'pintu' => PintuAir::with('subDaerahIrigasi:id,nama', 'hulu:id,kode', 'petugas:id,name')->where('daerah_irigasi_id', $di->id)->where('aktif', true)->urut()->get(),
            'petugasList' => $this->calonPetugas($di->id),
        ]);
    }

    public function simpanPenugasan(Request $request)
    {
        $di = DaerahIrigasi::findOrFail($request->integer('di'));
        $this->izinkan($request, $di->id);
        $calon = $this->calonPetugas($di->id)->pluck('id')->all();
        $data = $request->validate([
            'tugas' => ['array'],
            'tugas.*.utama' => ['nullable', Rule::in($calon)],
            'tugas.*.pengganti' => ['nullable', Rule::in($calon)],
        ], ['tugas.*.utama.in' => 'Petugas tidak bertugas di D.I. ini.', 'tugas.*.pengganti.in' => 'Petugas tidak bertugas di D.I. ini.']);

        $pintu = PintuAir::where('daerah_irigasi_id', $di->id)->whereIn('id', array_keys($data['tugas'] ?? []))->get()->keyBy('id');
        $n = 0;
        foreach ($data['tugas'] ?? [] as $id => $t) {
            if ($p = $pintu->get($id)) {
                $pengganti = ($t['pengganti'] ?? null) == ($t['utama'] ?? null) ? null : ($t['pengganti'] ?? null);
                $n += $this->simpanTugas($p, $t['utama'] ?? null, $pengganti) ? 1 : 0;
            }
        }

        return redirect()->route('master.pintu-air.penugasan', ['di' => $di->id])->with('ok', $n ? "Penugasan $n pintu diperbarui." : 'Tidak ada perubahan penugasan.');
    }

    /** Petugas lapangan aktif yang bertugas di D.I. ini (Manager, POB, PPA/petugas). */
    private function calonPetugas(int $diId)
    {
        return User::where('is_active', true)->whereIn('role', [Role::MANAGER, Role::POB, Role::PETUGAS])
            ->where(fn ($q) => $q->where('daerah_irigasi_id', $diId)
                ->orWhereHas('subDaerahIrigasi', fn ($s) => $s->where('daerah_irigasi_id', $diId)))
            ->orderByRaw("case role when 'manager' then 0 when 'pob' then 1 else 2 end")->orderBy('name')
            ->get(['id', 'name', 'role', 'jabatan']);
    }

    /** @return bool berubah atau tidak */
    private function simpanTugas(PintuAir $p, $utama, $pengganti): bool
    {
        $baru = collect(array_filter([$utama ? [(int) $utama, 'utama'] : null, $pengganti ? [(int) $pengganti, 'pengganti'] : null]))
            ->mapWithKeys(fn ($x) => [$x[0] => ['peran' => $x[1]]])->all();
        $lama = $p->petugas()->get()->mapWithKeys(fn ($u) => [$u->id => ['peran' => $u->pivot->peran]])->all();
        ksort($baru);
        ksort($lama);
        if ($baru == $lama) {
            return false;
        }
        $p->petugas()->sync($baru);

        return true;
    }

    private function huluList(int $diId, ?PintuAir $kecuali = null)
    {
        return PintuAir::where('daerah_irigasi_id', $diId)->where('tingkat', '!=', 'tersier')
            ->when($kecuali, fn ($q) => $q->where('id', '!=', $kecuali->id))->urut()->get(['id', 'kode', 'nama', 'tingkat', 'sub_daerah_irigasi_id']);
    }

    private function huluSah(int $huluId, string $tingkat): bool
    {
        $h = PintuAir::find($huluId);

        return $h && $h->tingkat !== 'tersier' && PintuAir::URUTAN_TINGKAT[$h->tingkat] <= PintuAir::URUTAN_TINGKAT[$tingkat];
    }

    /** Hulu harus lebih ke hulu dan tidak membentuk lingkaran. */
    private function cekHulu(Request $request, PintuAir $p, $huluId): void
    {
        if (! $huluId) {
            return;
        }
        $pesan = null;
        if (! $this->huluSah((int) $huluId, $request->input('tingkat'))) {
            $pesan = 'Pintu hulu harus setingkat atau lebih ke hulu (bukan pintu tersier).';
        } else {
            $cek = PintuAir::find($huluId);
            for ($i = 0; $cek && $i < 50; $i++) {
                if ($cek->id === $p->id) {
                    $pesan = 'Pintu hulu tidak boleh berada di hilir pintu ini (aliran melingkar).';
                    break;
                }
                $cek = $cek->hulu_id ? PintuAir::find($cek->hulu_id) : null;
            }
        }
        if ($pesan) {
            throw \Illuminate\Validation\ValidationException::withMessages(['hulu_id' => $pesan]);
        }
    }

    // ---------- skema jaringan ----------

    public function skemaStore(Request $request, DaerahIrigasi $di)
    {
        $this->izinkan($request, $di->id);
        $sisa = SkemaJaringan::MAKS_FILE - $di->skemaJaringan()->count();
        $request->validate([
            'judul' => ['nullable', 'string', 'max:150'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.SkemaJaringan::MAKS_KB],
        ], [
            'file.required' => 'Pilih file skema (JPG/PNG/PDF).',
            'file.mimes' => 'Skema harus berupa gambar (JPG/PNG/WEBP) atau PDF.',
            'file.max' => 'Ukuran file maksimal 20 MB.',
        ]);
        if ($sisa < 1) {
            return back()->withErrors(['file' => 'Sudah ada '.SkemaJaringan::MAKS_FILE.' file skema. Hapus yang lama dulu.']);
        }
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = $file->storeAs('skema/'.$di->id, Str::uuid().'.'.$ext, 'uploads');
        $di->skemaJaringan()->create([
            'judul' => $request->input('judul') ?: Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 150, ''),
            'path' => $path,
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'ukuran' => $file->getSize(),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('ok', 'Skema jaringan diunggah.');
    }

    public function skemaDestroy(Request $request, SkemaJaringan $skema)
    {
        $this->izinkan($request, $skema->daerah_irigasi_id);
        $skema->delete();

        return back()->with('ok', 'File skema dihapus.');
    }

    // ---------- bantuan ----------

    private function izinkan(Request $request, int $diId): void
    {
        abort_unless($request->user()->bolehKelolaPintu($diId), 403, 'Anda tidak punya izin mengelola pintu air di D.I. ini.');
    }

    private function aturanPintu(DaerahIrigasi $di, ?PintuAir $p = null): array
    {
        $angka = fn ($maks) => ['nullable', 'numeric', 'min:0', 'max:'.$maks];

        return [
            'kode' => ['required', 'string', 'max:30', Rule::unique('pintu_air', 'kode')->where('daerah_irigasi_id', $di->id)->ignore($p?->id)],
            'nama' => ['nullable', 'string', 'max:255'],
            'jenis' => ['required', Rule::in(array_keys(PintuAir::JENIS))],
            'tingkat' => ['required', Rule::in(array_keys(PintuAir::TINGKAT))],
            'hulu_id' => ['nullable', Rule::exists('pintu_air', 'id')->where('daerah_irigasi_id', $di->id)],
            'sub_daerah_irigasi_id' => ['nullable', Rule::exists('sub_daerah_irigasi', 'id')->where('daerah_irigasi_id', $di->id)],
            'saluran' => ['nullable', 'string', 'max:255'],
            'luas_layanan_ha' => $angka(100000),
            'debit_rencana_lps' => $angka(1000000),
            'debit_min_lps' => $angka(1000000),
            'debit_maks_lps' => [...$angka(1000000), $this->tidakDiBawah('debit_min_lps')],
            'tma_min_cm' => $angka(10000),
            'tma_maks_cm' => [...$angka(10000), $this->tidakDiBawah('tma_min_cm')],
        ];
    }

    /** Batas atas tidak boleh di bawah batas bawah (bila keduanya diisi). Bekerja juga untuk isian banyak baris. */
    private function tidakDiBawah(string $kolomMin): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($kolomMin) {
            $min = data_get(request()->all(), preg_replace('/[^.]+$/', $kolomMin, $attribute));
            if (is_numeric($value) && is_numeric($min) && (float) $value < (float) $min) {
                $fail('Batas atas tidak boleh lebih kecil dari batas bawah.');
            }
        };
    }

    private function pesan(): array
    {
        return [
            'kode.unique' => 'Kode ini sudah dipakai di D.I. ini.',
            '*.sub_daerah_irigasi_id.exists' => 'Sub-D.I. tidak termasuk D.I. ini.',
            'sub_daerah_irigasi_id.exists' => 'Sub-D.I. tidak termasuk D.I. ini.',
            'hulu_id.exists' => 'Pintu hulu harus di D.I. yang sama.',
        ];
    }

    private function rapikan(array $r): array
    {
        $r['kode'] = trim($r['kode']);
        foreach (['luas_layanan_ha', 'debit_rencana_lps', 'debit_min_lps', 'debit_maks_lps', 'tma_min_cm', 'tma_maks_cm', 'hulu_id', 'sub_daerah_irigasi_id', 'bangunan_id', 'lat', 'lng'] as $k) {
            if (array_key_exists($k, $r) && $r[$k] === '') {
                $r[$k] = null;
            }
        }

        return collect($r)->only([
            'kode', 'nama', 'jenis', 'tingkat', 'hulu_id', 'sub_daerah_irigasi_id', 'saluran', 'luas_layanan_ha', 'debit_rencana_lps', 'debit_min_lps', 'debit_maks_lps',
            'tma_min_cm', 'tma_maks_cm', 'bangunan_id', 'lat', 'lng', 'keterangan',
        ])->all();
    }
}
