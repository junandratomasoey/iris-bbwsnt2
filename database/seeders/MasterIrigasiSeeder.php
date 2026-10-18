<?php

namespace Database\Seeders;

use App\Models\Bangunan;
use App\Models\DaerahIrigasi;
use App\Models\P3a;
use App\Models\SubDaerahIrigasi;
use Illuminate\Database\Seeder;

/**
 * D.I., Sub-D.I., bangunan utama (sheet "D.I Pusat Permen PU-PR 14,2015")
 * + luas & titik tengah dari shapefile Peta IGT (public/geojson/*.geojson)
 * + P3A (sheet "Kontak P3A").
 */
class MasterIrigasiSeeder extends Seeder
{
    public function run(): void
    {
        $geo = $this->bacaGeojson();

        foreach (SeedData::get('daerah_irigasi') as $d) {
            $g = fn ($lapis, $f) => $geo[$lapis][$d['kode']][$f] ?? null;
            $nama = $d['nama'] === 'Soa / Ngada' ? "So'a / Ngada" : $d['nama'];

            $di = DaerahIrigasi::updateOrCreate(['kode' => $d['kode']], [
                'nama' => $nama,
                'alias' => array_values(array_unique(array_filter([$d['nama_excel'], $g('fungsional', 'nama'), $g('baku', 'nama')]))),
                'kabupaten_id' => SeedData::kabupatenId($d['kabupaten']),
                'luas_permen_ha' => $d['luas_ha'],
                'luas_baku_ha' => $g('baku', 'luas_ha'),
                'luas_potensial_ha' => $g('potensial', 'luas_ha'),
                'luas_fungsional_ha' => $g('fungsional', 'luas_ha'),
                'keterangan' => $g('fungsional', 'keterangan') ?? $g('baku', 'keterangan'),
                'lat' => $g('fungsional', 'lat') ?? $g('baku', 'lat'),
                'lng' => $g('fungsional', 'lng') ?? $g('baku', 'lng'),
                'tenaga_op' => ['pengamat' => $d['pengamat'], 'juru' => $d['juru'], 'manajer' => $d['manajer'], 'ppa' => $d['ppa'], 'pob' => $d['pob']],
            ]);

            foreach ($d['sub'] as $s) {
                $sub = SubDaerahIrigasi::updateOrCreate(
                    ['daerah_irigasi_id' => $di->id, 'nama' => $s['nama']],
                    ['luas_ha' => $s['luas_ha'], 'lokasi' => $s['lokasi'], 'kabupaten_id' => SeedData::kabupatenId($s['kabupaten'])]
                );
                if ($s['bangunan']) {
                    Bangunan::updateOrCreate(
                        ['daerah_irigasi_id' => $di->id, 'nama' => $s['bangunan']],
                        ['sub_daerah_irigasi_id' => $sub->id, 'jenis' => $s['jenis_bangunan'] ?? $this->tebakJenis($s['bangunan'])]
                    );
                }
            }
        }

        foreach (SeedData::get('p3a') as $p) {
            $jenis = str_starts_with(strtoupper($p['nama']), 'GP3A') ? 'GP3A' : (str_starts_with(strtoupper($p['nama']), 'IP3A') ? 'IP3A' : 'P3A');
            P3a::firstOrCreate(
                ['nama' => $p['nama'], 'ketua' => $p['ketua']],
                ['jenis' => $jenis, 'no_hp' => normalisasi_hp($p['no_hp']), 'kabupaten_id' => SeedData::kabupatenId($p['kabupaten'])]
            );
        }
    }

    private function bacaGeojson(): array
    {
        $hasil = [];
        foreach (['fungsional', 'baku', 'potensial'] as $lapis) {
            $f = public_path("geojson/$lapis.geojson");
            if (! is_file($f)) {
                continue;
            }
            foreach (json_decode(file_get_contents($f), true)['features'] as $ft) {
                $p = $ft['properties'];
                if ($p['kode']) {
                    $hasil[$lapis][$p['kode']] = $p;
                }
            }
        }

        return $hasil;
    }

    private function tebakJenis(string $nama): string
    {
        $n = strtolower($nama);
        foreach (['bendungan', 'embung', 'captering', 'free intake' => 'free_intake', 'bendung'] as $k => $v) {
            $kata = is_int($k) ? $v : $k;
            if (str_contains($n, $kata)) {
                return $v;
            }
        }

        return 'bendung';
    }
}
