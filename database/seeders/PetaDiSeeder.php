<?php

namespace Database\Seeders;

use App\Models\DaerahIrigasi;
use App\Models\PetaDi;
use App\Services\PetaDiService;
use Illuminate\Database\Seeder;

/**
 * Memindahkan batas D.I. dari file public/geojson/*.geojson (hasil konversi Peta IGT)
 * ke tabel peta_di agar bisa dikelola dari aplikasi. Aman dijalankan ulang: lapisan
 * yang sudah berisi data dilewati.
 *   php artisan db:seed --class=PetaDiSeeder --force
 */
class PetaDiSeeder extends Seeder
{
    public function run(PetaDiService $svc): void
    {
        $kode = DaerahIrigasi::pluck('id', 'kode');
        foreach (array_keys(PetaDi::LAPISAN) as $lapisan) {
            $file = public_path("geojson/$lapisan.geojson");
            if (PetaDi::where('lapisan', $lapisan)->exists() || ! is_file($file)) {
                continue;
            }
            $n = 0;
            foreach (json_decode(file_get_contents($file), true)['features'] ?? [] as $f) {
                $g = $svc->bersihkan($f['geometry']);
                $p = $f['properties'];
                $diId = $p['kode'] ? ($kode[$p['kode']] ?? null) : null;
                [$lat, $lng] = $svc->titikTengah($g);
                PetaDi::create([
                    'lapisan' => $lapisan, 'daerah_irigasi_id' => $diId, 'nama' => $p['nama'],
                    'luas_ha' => $svc->luasHa($g), 'lat' => $lat, 'lng' => $lng, 'geometri' => $g,
                    'sumber' => 'Peta IGT BBWS NT II (shapefile '.ucfirst($lapisan).')',
                ]);
                $n++;
            }
            $svc->bangunFile($lapisan);
            $this->command?->info("Lapisan $lapisan: $n batas D.I. dipindahkan ke database.");
        }
    }
}
