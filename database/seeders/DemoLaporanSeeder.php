<?php

namespace Database\Seeders;

use App\Models\DaerahIrigasi;
use App\Models\JadwalTanam;
use App\Models\LaporanHarian;
use App\Models\SubDaerahIrigasi;
use Illuminate\Database\Seeder;

/**
 * DATA CONTOH untuk uji coba / demo tampilan. Jangan dijalankan di server produksi.
 *   php artisan db:seed --class=DemoLaporanSeeder
 * Hapus lagi:  php artisan tinker --execute="App\Models\LaporanHarian::where('kendala','like','[DEMO]%')->delete()"
 */
class DemoLaporanSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoLaporanSeeder tidak boleh dijalankan di produksi.');

            return;
        }
        mt_srand(2026);
        $tahun = (int) date('Y');
        foreach (DaerahIrigasi::all() as $di) {
            JadwalTanam::firstOrCreate(['daerah_irigasi_id' => $di->id, 'tahun' => $tahun, 'mt' => 2], [
                'mulai' => "$tahun-08-01", 'selesai' => "$tahun-11-30", 'komoditas' => 'Padi', 'sumber' => '[DEMO]',
            ]);
        }

        $subs = SubDaerahIrigasi::with('petugas')->has('petugas')->inRandomOrder()->limit(70)->get();
        foreach ($subs as $i => $s) {
            $user = $s->petugas->first();
            $luas = (float) ($s->luas_ha ?: 100);
            $tingkat = [0.1, 0.25, 0.45, 0.7, 0.9][$i % 5]; // makin besar makin kering
            // laporan PPA rutin tiap Senin, 4 pekan terakhir; satu baris "seluruh Sub-D.I."
            for ($m = 3; $m >= 0; $m--) {
                $tgl = today()->startOfWeek()->subWeeks($m);
                $tanam = round($luas * (0.6 + mt_rand(0, 30) / 100), 2);
                $kering = round($tanam * min(0.9, $tingkat * (0.6 + (3 - $m) * 0.15)), 2);
                $l = LaporanHarian::updateOrCreate(['tanggal' => $tgl->toDateString(), 'sub_daerah_irigasi_id' => $s->id, 'user_id' => $user->id], [
                    'jam' => '07:30', 'jenis' => 'rutin', 'mt' => 2, 'cuaca' => 'cerah', 'hujan_7hari' => 'tidak', 'tindakan_operasi' => 'tetap',
                    'kendala' => '[DEMO] '.($tingkat > 0.4 ? 'Debit sungai menurun, giliran air per blok.' : 'Air cukup.'),
                    'status_verifikasi' => $m > 0 ? 'disetujui' : 'menunggu',
                ]);
                $l->petak()->delete();
                $l->petak()->create([
                    'luas_baku_ha' => $luas, 'luas_tanam_ha' => $tanam, 'jenis_tanaman' => 'P', 'fase' => [2, 3, 4][$i % 3], 'umur_hst' => 30 + (3 - $m) * 7,
                    'kecukupan_air' => $tingkat < 0.2 ? 'C' : ($tingkat < 0.4 ? 'KR' : ($tingkat < 0.8 ? 'KS' : 'KB')), 'luas_kering_ha' => $kering,
                ]);
                $l->hitungTotal();
            }
        }
    }
}
