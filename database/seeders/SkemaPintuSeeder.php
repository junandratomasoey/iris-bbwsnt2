<?php

namespace Database\Seeders;

use App\Models\Bangunan;
use App\Models\DaerahIrigasi;
use App\Models\PintuAir;
use App\Models\SkemaJaringan;
use App\Models\User;
use App\Support\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Memasukkan pintu air dari skema jaringan (database/seeders/data/skema/*.json):
 * tingkat, pintu hulu, luas layanan, debit rencana, penugasan petugas (dicocokkan dari nama di skema),
 * dan file skema PDF/gambar.
 *
 *   php artisan db:seed --class=SkemaPintuSeeder --force
 *
 * Aman dijalankan ulang: D.I. yang sudah punya pintu air dilewati (agar perubahan di aplikasi tidak tertimpa).
 */
class SkemaPintuSeeder extends Seeder
{
    public function run(): void
    {
        foreach (File::glob(database_path('seeders/data/skema/*.json')) as $berkas) {
            $data = json_decode(File::get($berkas), true, flags: JSON_THROW_ON_ERROR);
            $this->satu($data, dirname($berkas));
        }
    }

    private function satu(array $data, string $folder): void
    {
        $di = DaerahIrigasi::where('nama', $data['di'])->first();
        if (! $di) {
            $this->command?->warn("D.I. {$data['di']} tidak ditemukan — dilewati.");

            return;
        }
        $this->unggahSkema($di, $data['skema'] ?? null, $folder);

        if (PintuAir::where('daerah_irigasi_id', $di->id)->exists()) {
            $this->command?->info("D.I. {$di->nama} sudah punya pintu air — pintu tidak ditambahkan lagi.");

            return;
        }
        $sub = isset($data['sub']) ? $di->subDaerahIrigasi()->where('nama', $data['sub'])->first() : null;
        $petugasDi = User::where('is_active', true)->whereIn('role', [Role::MANAGER, Role::POB, Role::PETUGAS])
            ->where(fn ($q) => $q->where('daerah_irigasi_id', $di->id)->orWhereHas('subDaerahIrigasi', fn ($s) => $s->where('daerah_irigasi_id', $di->id)))
            ->get(['id', 'name']);

        DB::transaction(function () use ($data, $di, $sub, $petugasDi) {
            $kode = [];
            foreach ($data['pintu'] as $i => $r) {
                $bangunan = isset($r['bangunan']) ? Bangunan::where('daerah_irigasi_id', $di->id)->where('nama', $r['bangunan'])->first() : null;
                $titik = ($r['titik_dari_bangunan'] ?? false) && $bangunan?->lat;
                $p = PintuAir::create([
                    'daerah_irigasi_id' => $di->id,
                    'sub_daerah_irigasi_id' => $sub?->id,
                    'bangunan_id' => $bangunan?->id,
                    'kode' => $r['kode'], 'nama' => $r['nama'] ?? null,
                    'tingkat' => $r['tingkat'], 'jenis' => $r['jenis'],
                    'hulu_id' => isset($r['hulu']) ? ($kode[$r['hulu']] ?? null) : null,
                    'saluran' => $r['saluran'] ?? null,
                    'luas_layanan_ha' => $r['luas'] ?? null,
                    'debit_rencana_lps' => $r['q'] ?? null,
                    'urutan' => $i + 1,
                    'lat' => $titik ? $bangunan->lat : null, 'lng' => $titik ? $bangunan->lng : null,
                    'keterangan' => trim(($r['keterangan'] ?? '').($titik ? ' Titik sementara dari koordinat '.$bangunan->nama.'; perbarui dengan GPS di pintu.' : '')) ?: null,
                ]);
                $kode[$r['kode']] = $p->id;

                foreach ($r['petugas'] ?? [] as $j => $nama) {
                    $cocok = $petugasDi->filter(fn ($u) => Str::contains(Str::lower($u->name), Str::lower($nama)));
                    if ($cocok->count() === 1) {
                        $p->petugas()->attach($cocok->first()->id, ['peran' => $j === 0 ? 'utama' : 'pengganti']);
                    } else {
                        $this->command?->warn("Pintu {$r['kode']}: petugas \"$nama\" ".($cocok->isEmpty() ? 'tidak ditemukan' : 'cocok dengan lebih dari satu akun').' — atur di halaman Penugasan.');
                    }
                }
            }
            $this->command?->info('D.I. '.$di->nama.': '.count($kode).' pintu air ditambahkan.');
        });
    }

    private function unggahSkema(DaerahIrigasi $di, ?array $skema, string $folder): void
    {
        if (! $skema || $di->skemaJaringan()->where('judul', $skema['judul'])->exists()) {
            return;
        }
        $sumber = $folder.'/'.$skema['file'];
        if (! File::exists($sumber)) {
            return;
        }
        $ext = strtolower(pathinfo($sumber, PATHINFO_EXTENSION));
        $path = 'skema/'.$di->id.'/'.Str::uuid().'.'.$ext;
        Storage::disk('uploads')->put($path, File::get($sumber));
        SkemaJaringan::create([
            'daerah_irigasi_id' => $di->id, 'judul' => $skema['judul'], 'path' => $path,
            'mime' => $ext === 'pdf' ? 'application/pdf' : (File::mimeType($sumber) ?: 'application/octet-stream'),
            'ukuran' => File::size($sumber),
        ]);
    }
}
