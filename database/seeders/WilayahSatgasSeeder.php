<?php

namespace Database\Seeders;

use App\Models\Kabupaten;
use App\Models\WilayahSatgas;
use Illuminate\Database\Seeder;

/** Satgas Bencana 2026 (SK 134/KPTS/Bbws15). */
class WilayahSatgasSeeder extends Seeder
{
    public function run(): void
    {
        $wilayah = [
            'I' => 'Pulau Timor Bagian Timur dan Pulau Alor',
            'II' => 'Pulau Timor Bagian Barat, Pulau Sabu dan Pulau Rote',
            'III' => 'Pulau Sumba',
            'IV' => 'Pulau Flores Bagian Timur dan Pulau Lembata',
            'V' => 'Pulau Flores Bagian Barat',
        ];
        foreach ($wilayah as $kode => $cakupan) {
            WilayahSatgas::updateOrCreate(['kode' => $kode], ['nama' => "Satgas Wilayah $kode", 'cakupan' => $cakupan]);
        }

        // Kabupaten → Satgas dari sheet "Rekapan Data Terdampak", ditambah yang jelas dari nama pulau.
        // Kabupaten yang belum pasti (mis. TTS, Ngada, Nagekeo) dibiarkan kosong untuk diatur Admin.
        $peta = [];
        foreach (SeedData::get('satgas_kabupaten') as $kab => $satgas) {
            if (preg_match('/Wilayah\s+([IV]+)/', $satgas, $m)) {
                $peta[SeedData::namaKabupaten($kab)] = $m[1];
            }
        }
        $peta += ['Alor' => 'I', 'Belu' => 'I', 'Sabu Raijua' => 'II', 'Kota Kupang' => 'II',
            'Sumba Timur' => 'III', 'Sumba Barat Daya' => 'III', 'Flores Timur' => 'IV'];

        $ids = WilayahSatgas::pluck('id', 'kode');
        foreach ($peta as $kab => $kode) {
            Kabupaten::updateOrCreate(['nama' => $kab], ['wilayah_satgas_id' => $ids[$kode] ?? null]);
        }
    }
}
