<?php

namespace Tests\Unit;

use App\Models\LaporanHarian;
use App\Support\StatusKondisi as SK;
use PHPUnit\Framework\TestCase;

class StatusKondisiTest extends TestCase
{
    private function lap(array $a): LaporanHarian
    {
        return new LaporanHarian($a + ['kondisi_saluran' => 'normal', 'luas_tanam_ha' => 100, 'luas_kering_ha' => 0, 'luas_puso_ha' => 0]);
    }

    public function test_tanpa_laporan_kosong(): void
    {
        $this->assertSame(SK::KOSONG, SK::dariLaporan(null));
    }

    public function test_kondisi_saluran_menentukan_status_dasar(): void
    {
        $this->assertSame(SK::AMAN, SK::dariLaporan($this->lap(['kondisi_saluran' => 'penuh'])));
        $this->assertSame(SK::WASPADA, SK::dariLaporan($this->lap(['kondisi_saluran' => 'kurang'])));
        $this->assertSame(SK::SIAGA, SK::dariLaporan($this->lap(['kondisi_saluran' => 'kering'])));
    }

    public function test_luas_kekeringan_menaikkan_status(): void
    {
        $this->assertSame(SK::WASPADA, SK::dariLaporan($this->lap(['luas_kering_ha' => 10])));
        $this->assertSame(SK::SIAGA, SK::dariLaporan($this->lap(['luas_kering_ha' => 30])));
        $this->assertSame(SK::KRITIS, SK::dariLaporan($this->lap(['luas_kering_ha' => 50])));
    }

    public function test_puso_selalu_kritis(): void
    {
        $this->assertSame(SK::KRITIS, SK::dariLaporan($this->lap(['kondisi_saluran' => 'penuh', 'luas_puso_ha' => 0.5])));
    }

    public function test_wa_link_mengubah_awalan_nol(): void
    {
        $this->assertSame('https://wa.me/6281225919765', wa_link('0812-2591-9765'));
        $this->assertNull(wa_link('-'));
    }
}
