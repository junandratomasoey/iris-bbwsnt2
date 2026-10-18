<?php

namespace Database\Seeders;

use App\Models\Bangunan;
use App\Models\DaerahIrigasi;
use Illuminate\Database\Seeder;

/**
 * Koordinat, kondisi, dan data teknis bendung/bendungan dari shapefile SIGA BBWS NT II
 * (data/infrastruktur_siga.json). Aman dijalankan ulang:
 * - koordinat, lokasi, dan kondisi hanya diisi bila masih kosong (isian dari aplikasi tidak ditimpa)
 * - data teknis & kode aset selalu diperbarui dari sumber
 *
 * php artisan db:seed --class=InfrastrukturSigaSeeder --force
 */
class InfrastrukturSigaSeeder extends Seeder
{
    public function run(): void
    {
        $sumber = json_decode(file_get_contents(database_path('seeders/data/infrastruktur_siga.json')), true);
        $di = DaerahIrigasi::pluck('id', 'kode');
        $hitung = ['diperbarui' => 0, 'ditambah' => 0, 'dilewati' => 0];

        foreach ($sumber['data'] as $r) {
            $diId = $di[$r['di_kode']] ?? null;
            if (! $diId) {
                $this->catat("D.I. {$r['di_kode']} tidak ada, {$r['nama']} dilewati.");
                $hitung['dilewati']++;

                continue;
            }

            $q = fn () => Bangunan::where('daerah_irigasi_id', $diId);
            $b = (! empty($r['kode_aset']) ? $q()->where('kode_aset', $r['kode_aset'])->first() : null)
                ?? $q()->where('nama', $r['nama'])->first()
                ?? (! empty($r['cari']) ? $q()->where('nama', $r['cari'])->first() : null);

            if (! $b && ! empty($r['cari'])) {
                $this->catat("{$r['cari']} tidak ditemukan di D.I. {$r['di_kode']} (mungkin sudah diganti nama), dilewati.");
                $hitung['dilewati']++;

                continue;
            }

            $baru = ! $b;
            $b ??= new Bangunan(['daerah_irigasi_id' => $diId, 'jenis' => $r['jenis'] ?? 'bendung']);
            $b->nama = $r['nama'];
            $b->kode_aset = $r['kode_aset'] ?? $b->kode_aset;
            $b->data_teknis = $r['data_teknis'] ?? $b->data_teknis;
            if (! $b->lokasi && ! empty($r['lokasi'])) {
                $b->lokasi = $r['lokasi'];
            }
            if (! $b->lat || ! $b->lng) {
                [$b->lat, $b->lng] = [$r['lat'], $r['lng']];
            }
            if (! $b->kondisi && ! empty($r['kondisi'])) {
                $b->kondisi = $r['kondisi'];
                $b->kondisi_per = $r['kondisi_per'] ?? today();
            }
            $b->save();
            $hitung[$baru ? 'ditambah' : 'diperbarui']++;
        }

        $this->catat("Infrastruktur SIGA: {$hitung['diperbarui']} diperbarui, {$hitung['ditambah']} ditambah, {$hitung['dilewati']} dilewati.");
    }

    private function catat(string $pesan): void
    {
        $this->command?->info($pesan);
    }
}
