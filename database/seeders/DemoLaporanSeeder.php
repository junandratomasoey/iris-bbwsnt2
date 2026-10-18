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
            for ($h = 6; $h >= 0; $h--) {
                $tanam = round($luas * (0.6 + mt_rand(0, 30) / 100), 2);
                $kering = round($tanam * min(0.9, $tingkat * (0.6 + (6 - $h) * 0.08)), 2);
                LaporanHarian::updateOrCreate(['tanggal' => today()->subDays($h)->toDateString(), 'sub_daerah_irigasi_id' => $s->id], [
                    'user_id' => $user->id, 'mt' => 2,
                    'debit_intake_lps' => round(max(5, $luas * 1.2 * (1 - $tingkat) - $h * mt_rand(0, 5)), 1),
                    'tma_cm' => round(80 * (1 - $tingkat) + mt_rand(0, 15), 1),
                    'kondisi_saluran' => $tingkat < 0.2 ? 'normal' : ($tingkat < 0.5 ? 'kurang' : 'kering'),
                    'bukaan_pintu_persen' => (int) (100 - $tingkat * 60),
                    'fase_tanaman' => ['vegetatif', 'generatif', 'pemasakan'][$i % 3],
                    'luas_tanam_ha' => $tanam, 'luas_hijau_ha' => max(0, $tanam - $kering), 'luas_kering_ha' => $kering,
                    'luas_panen_ha' => 0, 'luas_puso_ha' => $tingkat > 0.8 && $h < 2 ? round($tanam * 0.05, 2) : 0,
                    'kendala' => '[DEMO] '.($tingkat > 0.4 ? 'Debit sungai menurun, giliran air per blok.' : 'Air cukup.'),
                    'status_verifikasi' => $h > 1 ? 'disetujui' : 'menunggu',
                ]);
            }
        }
    }
}
