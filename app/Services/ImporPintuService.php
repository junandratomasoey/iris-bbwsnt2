<?php

namespace App\Services;

use App\Models\DaerahIrigasi;
use App\Models\PintuAir;
use App\Models\User;
use App\Support\Role;
use App\Support\Xlsx;
use Illuminate\Support\Facades\DB;

/**
 * Template Excel data pintu air per D.I. (terisi data saat ini) dan impornya:
 * kode, tingkat, jenis, Sub-D.I., pintu hulu, luas layanan, debit rencana, rentang normal, urutan, aktif, penugasan petugas.
 * Baris dicocokkan dengan pintu yang ada berdasarkan KODE; pintu yang tidak ada di file tidak dihapus.
 */
class ImporPintuService
{
    /** kunci => [judul kolom, lebar] */
    public const KOLOM = [
        'kode' => ['Kode pintu *', 14],
        'nama' => ['Nama / keterangan singkat', 26],
        'tingkat' => ['Tingkat *', 11],
        'jenis' => ['Jenis *', 11],
        'sub' => ['Sub-D.I.', 18],
        'hulu' => ['Kode pintu hulu', 14],
        'luas_layanan_ha' => ['Luas layanan / baku petak (Ha)', 14],
        'debit_rencana_lps' => ['Debit rencana / kebutuhan (L/dt)', 14],
        'debit_min_lps' => ['Debit normal min (L/dt)', 12],
        'debit_maks_lps' => ['Debit normal maks (L/dt)', 12],
        'tma_min_cm' => ['TMA normal min (cm)', 11],
        'tma_maks_cm' => ['TMA normal maks (cm)', 11],
        'saluran' => ['Saluran', 18],
        'urutan' => ['Urutan (hulu ke hilir)', 10],
        'aktif' => ['Aktif (Ya/Tidak)', 9],
        'utama' => ['Petugas utama (No. HP)', 16],
        'pengganti' => ['Petugas pengganti (No. HP)', 16],
        'keterangan' => ['Catatan', 24],
    ];

    private const ANGKA = ['luas_layanan_ha', 'debit_rencana_lps', 'debit_min_lps', 'debit_maks_lps', 'tma_min_cm', 'tma_maks_cm'];

    public function template(DaerahIrigasi $di): string
    {
        $pintu = PintuAir::where('daerah_irigasi_id', $di->id)->with('subDaerahIrigasi:id,nama', 'hulu:id,kode', 'petugas:id,no_hp,username')->urut()->get();
        $subs = $di->subDaerahIrigasi()->orderBy('nama')->pluck('nama');
        $calon = $this->calon($di->id);

        $baris = [array_column(self::KOLOM, 0)];
        foreach ($pintu as $p) {
            $hp = fn ($peran) => $p->petugas->first(fn ($u) => $u->pivot->peran === $peran)?->no_hp;
            $baris[] = [
                $p->kode, $p->nama, $p->tingkat, $p->jenis, $p->subDaerahIrigasi?->nama, $p->hulu?->kode,
                ...array_map(fn ($k) => $p->$k === null ? null : (float) $p->$k, self::ANGKA),
                $p->saluran, (int) $p->urutan, $p->aktif ? 'Ya' : 'Tidak', $hp('utama'), $hp('pengganti'), $p->keterangan,
            ];
        }
        $daftar = [['Tingkat', 'Arti', 'Jenis', 'Arti', 'Sub-D.I.', 'Petugas', 'No. HP']];
        $tingkat = array_keys(PintuAir::TINGKAT);
        $jenis = array_keys(PintuAir::JENIS);
        $orang = $calon->values();
        for ($i = 0; $i < max(count($tingkat), count($jenis), $subs->count(), $orang->count()); $i++) {
            $daftar[] = [$tingkat[$i] ?? null, isset($tingkat[$i]) ? PintuAir::TINGKAT[$tingkat[$i]] : null, $jenis[$i] ?? null, isset($jenis[$i]) ? PintuAir::JENIS[$jenis[$i]] : null,
                $subs[$i] ?? null, isset($orang[$i]) ? $orang[$i]->name.' ('.Role::label($orang[$i]->role).')' : null, $orang[$i]->no_hp ?? null];
        }
        $n = 500;
        $akhir = fn ($jml) => '$'.max(2, $jml + 1);
        $petunjuk = [
            ['Petunjuk pengisian data pintu air — D.I. '.$di->nama],
            [''],
            ['1. Isi lembar "Pintu", satu baris per pintu/bangunan bagi, urut dari hulu ke hilir sesuai skema jaringan.'],
            ['2. Kode pintu dipakai untuk mencocokkan: baris dengan kode yang sudah ada akan MENGUBAH pintu itu; kode baru akan MENAMBAH pintu. Pintu yang tidak ada di file TIDAK dihapus.'],
            ['3. Tingkat: utama (bendung/intake), primer, sekunder, tersier (petak tersier). Jenis: lihat lembar "Daftar". Pilih dari daftar pilihan di sel.'],
            ['4. Kode pintu hulu: kode pintu tempat air berasal (harus setingkat atau lebih ke hulu, bukan tersier). Boleh pintu yang ditulis di baris lain file ini.'],
            ['5. Luas layanan: untuk pintu tersier = luas baku petak tersier (Ha); dipakai untuk memeriksa luas tanam di laporan PPA dan pemerataan L/dt/Ha.'],
            ['6. Debit rencana: rencana pemberian air MT berjalan dari Bidang OP; menjadi debit kebutuhan awal di laporan Manager Air.'],
            ['7. Debit/TMA normal min–maks: batas untuk tanda ▼ di bawah normal / ▲ di atas normal saat pintu dibaca. Kosongkan bila belum ditetapkan.'],
            ['8. Petugas utama/pengganti: No. HP petugas yang terdaftar di D.I. ini (lihat lembar "Daftar"). Petugas utama wajib mengisi pintu/petak itu di laporan PPA. Kosongkan sel untuk menghapus penugasan.'],
            ['9. Angka memakai titik atau koma desimal. Kolom bertanda * wajib.'],
            ['10. Simpan sebagai .xlsx, lalu unggah di IRIS: Pintu air → Impor Excel. Perubahan ditampilkan dulu untuk diperiksa sebelum disimpan.'],
        ];

        $path = tempnam(sys_get_temp_dir(), 'iris').'.xlsx';
        Xlsx::tulis($path, [
            ['nama' => 'Pintu', 'baris' => $baris, 'lebar' => array_column(self::KOLOM, 1), 'pilihan' => [
                "C2:C$n" => 'Daftar!$A$2:$A$'.(count($tingkat) + 1),
                "D2:D$n" => 'Daftar!$C$2:$C$'.(count($jenis) + 1),
                "E2:E$n" => 'Daftar!$E$2:$E'.$akhir($subs->count()),
                "O2:O$n" => '"Ya,Tidak"',
            ]],
            ['nama' => 'Daftar', 'baris' => $daftar, 'lebar' => [11, 26, 11, 28, 22, 34, 16]],
            ['nama' => 'Petunjuk', 'baris' => $petunjuk, 'lebar' => [150], 'judul' => false],
        ]);

        return $path;
    }

    /**
     * Baca & periksa file. @return array{baris: array, galat: int, baru: int, ubah: int, sama: int, kolom_tugas: bool}
     */
    public function periksa(DaerahIrigasi $di, string $path): array
    {
        $isi = Xlsx::baca($path, 'Pintu');
        $judul = $isi[1] ?? [];
        // petakan kolom berdasarkan judul (urutan kolom boleh berubah)
        $peta = [];
        foreach (self::KOLOM as $k => [$teks]) {
            foreach ($judul as $i => $j) {
                if ($this->sama($j, $teks)) {
                    $peta[$k] = $i;
                }
            }
        }
        if (! isset($peta['kode'], $peta['tingkat'], $peta['jenis'])) {
            throw new \RuntimeException('Kolom "Kode pintu", "Tingkat", dan "Jenis" tidak ditemukan di lembar "Pintu". Gunakan template dari IRIS.');
        }

        $ada = PintuAir::where('daerah_irigasi_id', $di->id)->with('petugas:id', 'hulu:id,kode')->get()->keyBy(fn ($p) => mb_strtolower($p->kode));
        $subs = $di->subDaerahIrigasi()->get(['id', 'nama'])->keyBy(fn ($s) => mb_strtolower(trim($s->nama)));
        $calon = $this->calon($di->id);
        $cariHp = fn (string $hp) => $calon->first(fn ($u) => $this->hp($u->no_hp) === $this->hp($hp) || $this->hp($u->username) === $this->hp($hp));
        $tingkatLabel = collect(PintuAir::TINGKAT)->mapWithKeys(fn ($l, $k) => [mb_strtolower($l) => $k, $k => $k])->all();
        $jenisLabel = collect(PintuAir::JENIS)->mapWithKeys(fn ($l, $k) => [mb_strtolower($l) => $k, $k => $k])->all();

        $baris = [];
        foreach ($isi as $no => $sel) {
            if ($no < 2) {
                continue;
            }
            $v = fn ($k) => isset($peta[$k]) ? trim((string) ($sel[$peta[$k]] ?? '')) : null;
            if (collect(array_keys(self::KOLOM))->every(fn ($k) => in_array($v($k), [null, ''], true))) {
                continue; // baris kosong
            }
            $g = [];
            $kode = $v('kode');
            $d = ['kode' => $kode, 'nama' => $v('nama') ?: null, 'saluran' => $v('saluran') ?: null, 'keterangan' => $v('keterangan') ?: null];
            if ($kode === '' || mb_strlen($kode) > 30) {
                $g[] = 'Kode pintu wajib diisi (maks. 30 karakter).';
            }
            $d['tingkat'] = $tingkatLabel[mb_strtolower($v('tingkat'))] ?? null;
            if (! $d['tingkat']) {
                $g[] = 'Tingkat harus salah satu: '.implode(', ', array_keys(PintuAir::TINGKAT)).'.';
            }
            $d['jenis'] = $jenisLabel[mb_strtolower($v('jenis'))] ?? null;
            if (! $d['jenis']) {
                $g[] = 'Jenis harus salah satu: '.implode(', ', array_keys(PintuAir::JENIS)).'.';
            }
            $d['sub_daerah_irigasi_id'] = null;
            if (($sub = $v('sub')) !== null && $sub !== '') {
                $d['sub_daerah_irigasi_id'] = $subs[mb_strtolower($sub)]->id ?? null;
                if (! $d['sub_daerah_irigasi_id']) {
                    $g[] = "Sub-D.I. \"$sub\" tidak ada di D.I. ini.";
                }
            }
            foreach (self::ANGKA as $k) {
                $x = str_replace(',', '.', (string) $v($k));
                $d[$k] = $x === '' ? null : (is_numeric($x) && $x >= 0 ? round((float) $x, 2) : false);
                if ($d[$k] === false) {
                    $g[] = self::KOLOM[$k][0].' harus angka ≥ 0.';
                    $d[$k] = null;
                }
            }
            foreach ([['debit_min_lps', 'debit_maks_lps', 'Debit'], ['tma_min_cm', 'tma_maks_cm', 'TMA']] as [$min, $maks, $lbl]) {
                if ($d[$min] !== null && $d[$maks] !== null && $d[$maks] < $d[$min]) {
                    $g[] = "$lbl normal maks lebih kecil dari min.";
                }
            }
            $urut = $v('urutan');
            $d['urutan'] = is_numeric($urut) ? max(0, min(65000, (int) $urut)) : null;
            $aktif = mb_strtolower((string) $v('aktif'));
            $d['aktif'] = in_array($aktif, ['', 'ya', 'y', '1', 'aktif', 'true'], true) ? true : (in_array($aktif, ['tidak', 't', '0', 'nonaktif', 'false'], true) ? false : null);
            if ($d['aktif'] === null) {
                $g[] = 'Aktif harus Ya atau Tidak.';
                $d['aktif'] = true;
            }
            $d['hulu_kode'] = $v('hulu') ?: null;
            if ($d['hulu_kode'] !== null && mb_strtolower($d['hulu_kode']) === mb_strtolower($kode)) {
                $g[] = 'Pintu hulu tidak boleh dirinya sendiri.';
            }
            $tugas = null;
            if (isset($peta['utama']) || isset($peta['pengganti'])) {
                $tugas = [];
                foreach (['utama', 'pengganti'] as $peran) {
                    $hp = (string) $v($peran);
                    if ($hp === '') {
                        continue;
                    }
                    $u = $cariHp($hp);
                    if (! $u) {
                        $g[] = "Petugas $peran ($hp) tidak ditemukan di antara petugas D.I. ini.";
                    } else {
                        $tugas[$peran] = ['id' => $u->id, 'nama' => $u->name];
                    }
                }
                if (isset($tugas['utama'], $tugas['pengganti']) && $tugas['utama']['id'] === $tugas['pengganti']['id']) {
                    $g[] = 'Petugas utama dan pengganti tidak boleh orang yang sama.';
                }
            }
            $baris[] = ['no' => $no, 'data' => $d, 'tugas' => $tugas, 'galat' => $g];
        }
        if (! $baris) {
            throw new \RuntimeException('Lembar "Pintu" tidak berisi data.');
        }

        // kode ganda di file
        $hitung = collect($baris)->countBy(fn ($b) => mb_strtolower($b['data']['kode']));
        foreach ($baris as &$b) {
            if (($hitung[mb_strtolower($b['data']['kode'])] ?? 0) > 1) {
                $b['galat'][] = 'Kode ini muncul lebih dari sekali di file.';
            }
        }
        unset($b);

        // hulu: kode di file atau di database, tingkat lebih hulu, tanpa lingkaran
        $tingkatAkhir = $ada->map(fn ($p) => $p->tingkat)->all();
        $huluAkhir = $ada->map(fn ($p) => $p->hulu?->kode ? mb_strtolower($p->hulu->kode) : null)->all();
        foreach ($baris as $b) {
            if ($b['data']['tingkat']) {
                $tingkatAkhir[mb_strtolower($b['data']['kode'])] = $b['data']['tingkat'];
                $huluAkhir[mb_strtolower($b['data']['kode'])] = $b['data']['hulu_kode'] ? mb_strtolower($b['data']['hulu_kode']) : null;
            }
        }
        foreach ($baris as &$b) {
            $h = $b['data']['hulu_kode'];
            if (! $h || ! $b['data']['tingkat']) {
                continue;
            }
            $th = $tingkatAkhir[mb_strtolower($h)] ?? null;
            if (! $th) {
                $b['galat'][] = "Pintu hulu \"$h\" tidak ada di D.I. ini maupun di file.";
            } elseif ($th === 'tersier' || PintuAir::URUTAN_TINGKAT[$th] > PintuAir::URUTAN_TINGKAT[$b['data']['tingkat']]) {
                $b['galat'][] = "Pintu hulu \"$h\" ($th) harus setingkat atau lebih ke hulu, bukan tersier.";
            } else {
                $cek = mb_strtolower($h);
                for ($i = 0; $cek && $i < 100; $i++) {
                    if ($cek === mb_strtolower($b['data']['kode'])) {
                        $b['galat'][] = 'Aliran melingkar: pintu hulu berada di hilir pintu ini.';
                        break;
                    }
                    $cek = $huluAkhir[$cek] ?? null;
                }
            }
        }
        unset($b);

        // aksi: baru / ubah (dengan daftar perubahan) / sama
        foreach ($baris as &$b) {
            $p = $ada[mb_strtolower($b['data']['kode'])] ?? null;
            $b['id'] = $p?->id;
            if (! $p) {
                $b['aksi'] = 'baru';
                $b['ubah'] = [];
            } else {
                $ubah = [];
                foreach (['nama', 'tingkat', 'jenis', 'sub_daerah_irigasi_id', 'saluran', 'keterangan', 'aktif', ...self::ANGKA] as $k) {
                    $lama = $p->$k;
                    $baru = $b['data'][$k];
                    $beda = in_array($k, self::ANGKA, true) ? ($lama === null) !== ($baru === null) || ($lama !== null && abs((float) $lama - $baru) > 0.001) : (string) $lama !== (string) $baru;
                    if ($k === 'aktif') {
                        $beda = (bool) $lama !== $baru;
                    }
                    if ($beda) {
                        $ubah[] = self::KOLOM[$k][0] ?? ucfirst(str_replace('_', ' ', $k));
                    }
                }
                if ($b['data']['urutan'] !== null && (int) $p->urutan !== $b['data']['urutan']) {
                    $ubah[] = 'Urutan';
                }
                if (mb_strtolower((string) $p->hulu?->kode) !== mb_strtolower((string) $b['data']['hulu_kode'])) {
                    $ubah[] = 'Pintu hulu';
                }
                if ($b['tugas'] !== null) {
                    $lamaTugas = $p->petugas->mapWithKeys(fn ($u) => [$u->pivot->peran => $u->id])->sortKeys()->all();
                    $baruTugas = collect($b['tugas'])->map(fn ($x) => $x['id'])->sortKeys()->all();
                    if ($lamaTugas != $baruTugas) {
                        $ubah[] = 'Penugasan';
                    }
                }
                $b['aksi'] = $ubah ? 'ubah' : 'sama';
                $b['ubah'] = $ubah;
            }
        }
        unset($b);

        $c = collect($baris);

        return [
            'baris' => $baris, 'kolom_tugas' => isset($peta['utama']) || isset($peta['pengganti']),
            'galat' => $c->filter(fn ($b) => $b['galat'])->count(),
            'baru' => $c->where('aksi', 'baru')->count(), 'ubah' => $c->where('aksi', 'ubah')->count(), 'sama' => $c->where('aksi', 'sama')->count(),
        ];
    }

    /** Simpan hasil periksa (harus tanpa galat). @return array{baru: int, ubah: int} */
    public function terapkan(DaerahIrigasi $di, array $hasil): array
    {
        if ($hasil['galat']) {
            throw new \RuntimeException('Masih ada baris bergalat.');
        }

        return DB::transaction(function () use ($di, $hasil) {
            $n = ['baru' => 0, 'ubah' => 0];
            $simpan = [];
            foreach ($hasil['baris'] as $b) {
                if ($b['aksi'] === 'sama') {
                    continue;
                }
                $d = collect($b['data'])->except(['hulu_kode'])->all();
                if ($d['urutan'] === null) {
                    unset($d['urutan']);
                }
                $p = $b['id'] ? PintuAir::find($b['id']) : new PintuAir(['daerah_irigasi_id' => $di->id]);
                $p->fill($d)->save();
                $simpan[] = [$p, $b];
                $n[$b['id'] ? 'ubah' : 'baru']++;
            }
            // hulu & penugasan setelah semua pintu tersimpan (hulu bisa pintu baru di file yang sama)
            $kode = PintuAir::where('daerah_irigasi_id', $di->id)->pluck('id', 'kode')->mapWithKeys(fn ($id, $k) => [mb_strtolower($k) => $id]);
            foreach ($simpan as [$p, $b]) {
                $huluId = $b['data']['hulu_kode'] ? ($kode[mb_strtolower($b['data']['hulu_kode'])] ?? null) : null;
                if ($p->hulu_id !== $huluId) {
                    $p->update(['hulu_id' => $huluId]);
                }
                if ($b['tugas'] !== null) {
                    $p->petugas()->sync(collect($b['tugas'])->mapWithKeys(fn ($u, $peran) => [$u['id'] => ['peran' => $peran]])->all());
                }
            }

            return $n;
        });
    }

    /** Petugas lapangan aktif di D.I. ini (sama dengan pilihan di menu Penugasan). */
    private function calon(int $diId)
    {
        return User::where('is_active', true)->whereIn('role', [Role::MANAGER, Role::POB, Role::PETUGAS])
            ->where(fn ($q) => $q->where('daerah_irigasi_id', $diId)->orWhereHas('subDaerahIrigasi', fn ($s) => $s->where('daerah_irigasi_id', $diId)))
            ->orderByRaw("case role when 'manager' then 0 when 'pob' then 1 else 2 end")->orderBy('name')->get(['id', 'name', 'role', 'no_hp', 'username']);
    }

    private function hp(?string $hp): string
    {
        $n = preg_replace('/\D/', '', (string) $hp);

        return str_starts_with($n, '62') ? '0'.substr($n, 2) : $n;
    }

    private function sama(string $a, string $b): bool
    {
        $n = fn ($s) => preg_replace('/[^a-z0-9]/', '', mb_strtolower($s));

        return $n($a) !== '' && $n($a) === $n($b);
    }
}
