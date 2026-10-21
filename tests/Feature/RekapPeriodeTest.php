<?php

namespace Tests\Feature;

use App\Models\DaerahIrigasi;
use App\Models\Kabupaten;
use App\Models\LaporanHarian;
use App\Models\User;
use App\Models\WilayahSatgas;
use App\Services\RekapService;
use App\Support\Periode;
use App\Support\Role;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class RekapPeriodeTest extends TestCase
{
    use RefreshDatabase;

    private DaerahIrigasi $di;

    private User $admin;

    private User $petugas;

    protected function setUp(): void
    {
        parent::setUp();
        $w = WilayahSatgas::create(['kode' => 'II', 'nama' => 'Satgas II', 'cakupan' => 'Timor Barat']);
        $kab = Kabupaten::create(['nama' => 'Kupang', 'wilayah_satgas_id' => $w->id]);
        $this->di = DaerahIrigasi::create(['kode' => 'DI-01', 'nama' => 'Oesao', 'kabupaten_id' => $kab->id, 'luas_fungsional_ha' => 1000]);
        $this->di->subDaerahIrigasi()->createMany([['nama' => 'Sub A', 'luas_ha' => 500], ['nama' => 'Sub B', 'luas_ha' => 500]]);
        $this->admin = User::create(['name' => 'Admin', 'username' => 'adm', 'password' => 'x', 'role' => Role::ADMIN_OP, 'must_change_password' => false]);
        $this->petugas = User::create(['name' => 'P', 'username' => 'p', 'password' => 'x', 'role' => Role::PETUGAS, 'must_change_password' => false]);
    }

    private function lapor(int $subKe, string $tgl, string $kondisi, float $tanam = 100, float $kering = 0, float $puso = 0): void
    {
        LaporanHarian::create([
            'tanggal' => $tgl, 'sub_daerah_irigasi_id' => $this->di->subDaerahIrigasi[$subKe]->id, 'user_id' => $this->petugas->id,
            'debit_intake_lps' => 50, 'tma_cm' => 10, 'kondisi_saluran' => $kondisi,
            'luas_tanam_ha' => $tanam, 'luas_kering_ha' => $kering, 'luas_puso_ha' => $puso,
        ]);
    }

    private function rekap(string $dari, string $sampai): array
    {
        $p = Periode::dariRequest(new Request(['periode' => 'rentang', 'dari' => $dari, 'sampai' => $sampai]));

        return app(RekapService::class)->rekap($p, null);
    }

    public function test_status_harian_berlaku_7_hari_dan_luas_memakai_puncak(): void
    {
        $h = fn ($n) => today()->subDays(14 - $n)->toDateString(); // hari ke-1 .. ke-14 dalam periode
        $this->lapor(0, $h(1), 'normal', 100, 5);          // aman, berlaku hari 1–7
        $this->lapor(0, $h(9), 'kering', 100, 60, 2);      // kritis (puso), hari 9
        $this->lapor(0, $h(10), 'normal', 120, 10);        // aman, menggantikan laporan hari 9
        $this->lapor(1, $h(10), 'kurang', 80, 0);          // waspada, Sub B hari 10–14

        $r = $this->rekap($h(1), $h(14));
        $d = $r['di'][$this->di->id];

        // hari 1–7 aman, 8 kosong, 9 kritis, 10–14 waspada (Sub A aman + Sub B waspada)
        $this->assertSame(14, $r['jumlah_hari']);
        $this->assertSame('kritis', $d['terberat']);
        $this->assertSame([7, 1, 1, 5], [$d['hari']['aman'], $d['hari']['kosong'], $d['hari']['kritis'], $d['hari']['waspada']]);
        $this->assertSame('aman', $d['tersering']);

        // puncak per Sub-D.I. lalu dijumlah: tanam 120 + 80, kering 60 + 0, puso 2 — bukan jumlah semua laporan
        $this->assertEquals(200, $d['puncak_tanam_ha']);
        $this->assertEquals(60, $d['puncak_kering_ha']);
        $this->assertEquals(2, $d['puncak_puso_ha']);
        // kepatuhan per pekan (rutin tiap Senin): pekan yang ada laporan ÷ (Sub-D.I. × pekan dalam periode)
        $pekan = fn (array $hari) => collect($hari)->map(fn ($x) => Carbon::parse($h($x))->startOfWeek()->toDateString())->unique()->count();
        $jumlahPekan = $pekan(range(1, 14));
        $this->assertSame($jumlahPekan, $r['total']['jumlah_minggu']);
        $this->assertSame((int) round(($pekan([1, 9, 10]) + $pekan([10])) / (2 * $jumlahPekan) * 100), $d['kepatuhan']);
    }

    public function test_periode_dan_kalender_mingguan(): void
    {
        $p = Periode::dariRequest(new Request(['periode' => 'tahun']));
        $this->assertSame(today()->startOfYear()->toDateString(), $p->dari->toDateString());
        $this->assertTrue($p->mingguan());

        $p = Periode::dariRequest(new Request(['periode' => 'rentang', 'dari' => '2020-01-01', 'sampai' => today()->addDays(5)->toDateString()]));
        $this->assertTrue($p->sampai->isToday());                       // tidak boleh ke masa depan
        $this->assertSame(Periode::MAKS_HARI, $p->jumlahHari());         // dibatasi

        $r = $this->rekap(today()->subDays(99)->toDateString(), today()->toDateString());
        $this->assertTrue($r['mingguan']);
        $this->assertLessThanOrEqual(16, count($r['kalender']['kolom']));
        $this->assertCount(count($r['kalender']['kolom']), $r['kalender']['baris'][$this->di->id]);
    }

    public function test_dashboard_peta_dan_unduh_mengikuti_periode(): void
    {
        $this->lapor(0, today()->subDays(2)->toDateString(), 'kering', 100, 60);

        $this->actingAs($this->admin)->get(route('dashboard', ['periode' => 'bulan']))->assertOk()
            ->assertSee('Rekap kondisi irigasi')->assertSee('Kalender kondisi')->assertSee('Satgas Wilayah II');
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee('Kondisi irigasi hari ini');

        $peta = $this->getJson(route('api.publik.peta', ['periode' => '7hari', 'warna' => 'tersering']))->assertOk();
        $peta->assertJsonPath('periode.rekap', true)->assertJsonPath('periode.warna', 'tersering')
            ->assertJsonPath('daerah_irigasi.0.status', 'kritis')
            ->assertJsonMissingPath('daerah_irigasi.0.debit_rata_lps');            // debit tidak dibuka ke publik
        $this->actingAs($this->admin)->getJson(route('api.peta', ['periode' => '7hari']))->assertOk()
            ->assertJsonPath('daerah_irigasi.0.debit_rata_lps', 50);

        $this->getJson(route('api.publik.di', ['di' => $this->di, 'periode' => 'tahun']))->assertOk()
            ->assertJsonPath('riwayat_judul', fn ($v) => str_contains($v, 'per minggu'));

        $csv = $this->actingAs($this->admin)->get(route('rekap.unduh', ['periode' => 'bulan']))->assertOk()->streamedContent();
        $this->assertStringContainsString('DI-01;Oesao;Kupang;II', $csv);
        $this->assertStringContainsString('TOTAL', $csv);
    }
}
