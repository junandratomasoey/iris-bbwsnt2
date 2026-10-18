<?php

namespace Database\Seeders;

use App\Models\DaerahIrigasi;
use App\Models\Kejadian;
use Illuminate\Database\Seeder;

/** 95 lokasi terdampak dari sheet "Rekapan Data Terdampak" (data s.d. 27 Sep 2026). */
class KejadianSeeder extends Seeder
{
    public function run(): void
    {
        if (Kejadian::exists()) {
            return;
        }
        $di = DaerahIrigasi::all();
        $cariDi = function (?string $nama) use ($di) {
            if (! $nama) {
                return null;
            }
            $k = SeedData::kunci($nama);

            return $di->first(fn ($d) => collect($d->alias ?? [])->push($d->nama)->contains(fn ($a) => SeedData::kunci($a) === $k))?->id;
        };
        $status = [
            'Belum Ditangani' => 'Perlu Penanganan',
            'Sedang Berproses' => 'Dalam Penanganan',
            'Selesai' => 'Selesai Ditangani',
        ];

        foreach (SeedData::get('kejadian') as $k) {
            $catatan = collect([$k['catatan'], $k['pic'] ? 'PIC: '.$k['pic'] : null, $k['dok_sebelum'] ? 'Dokumentasi: '.$k['dok_sebelum'] : null])->filter()->implode("\n");
            Kejadian::create([
                'tanggal_lapor' => '2026-09-27',
                'daerah_irigasi_id' => $cariDi($k['di']),
                'kabupaten_id' => SeedData::kabupatenId($k['kabupaten']),
                'kecamatan' => $k['kecamatan'],
                'desa' => $k['desa'],
                'lat' => $k['lat'],
                'lng' => $k['lng'],
                'jenis_kegagalan' => $k['jenis_kegagalan'] ?? 'Lainnya',
                'penyebab' => $k['penyebab'],
                'status_kegagalan' => $k['status_kegagalan'] ?? 'Potensi',
                'luas_terdampak_ha' => $k['luas_terdampak_ha'] ?? 0,
                'luas_tertangani_ha' => $k['luas_tertangani_ha'] ?? 0,
                'sumber_air' => $k['sumber_air'],
                'solusi' => $k['solusi'],
                'p3a' => $k['p3a'],
                'pelapor_nama' => $k['pelapor_nama'],
                'pelapor_hp' => normalisasi_hp($k['pelapor_hp']),
                'sumber_data' => $k['sumber_data'] ?? 'Rekapan Kompilasi BBWS NT II',
                'catatan' => $catatan ?: null,
                'status_penanganan' => $status[$k['status_penanganan']] ?? 'Perlu Penanganan',
            ]);
        }
    }
}
