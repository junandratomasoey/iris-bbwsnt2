<?php

namespace Tests\Feature;

use App\Models\DaerahIrigasi;
use App\Models\Kabupaten;
use App\Models\Kejadian;
use App\Models\LaporanAir;
use App\Models\LaporanHarian;
use App\Models\PembacaanPintu;
use App\Models\PintuAir;
use App\Models\SesiPintu;
use App\Models\SubDaerahIrigasi;
use App\Models\User;
use App\Models\WilayahSatgas;
use App\Services\KondisiService;
use App\Services\RekapService;
use App\Support\Periode;
use App\Support\Role;
use App\Support\StatusKondisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LaporanAirTest extends TestCase
{
    use RefreshDatabase;

    private DaerahIrigasi $di;

    private DaerahIrigasi $diLain;

    private SubDaerahIrigasi $sub1;

    private SubDaerahIrigasi $sub2;

    private User $manager;

    private User $ppa;

    private User $pimpinan;

    private User $koor;

    private PintuAir $intake;

    private PintuAir $bp1;

    private PintuAir $bp2;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        $w = WilayahSatgas::create(['kode' => 'II', 'nama' => 'Satgas II', 'cakupan' => 'Timor']);
        $kab = Kabupaten::create(['nama' => 'Kupang', 'wilayah_satgas_id' => $w->id]);
        $kab2 = Kabupaten::create(['nama' => 'Sumba Timur']);
        $this->di = DaerahIrigasi::create(['kode' => 'DI-01', 'nama' => 'Oesao', 'kabupaten_id' => $kab->id]);
        $this->diLain = DaerahIrigasi::create(['kode' => 'DI-02', 'nama' => 'Kambaniru', 'kabupaten_id' => $kab2->id]);
        $this->sub1 = $this->di->subDaerahIrigasi()->create(['nama' => 'Oesao Kiri', 'luas_ha' => 100]);
        $this->sub2 = $this->di->subDaerahIrigasi()->create(['nama' => 'Oesao Kanan', 'luas_ha' => 100]);
        $this->diLain->subDaerahIrigasi()->create(['nama' => 'Kambaniru']);
        $buat = fn ($role, $u, $x = []) => User::create(['name' => ucfirst($u), 'username' => $u, 'password' => 'x', 'role' => $role, 'must_change_password' => false] + $x);
        $this->manager = $buat(Role::MANAGER, 'manager', ['daerah_irigasi_id' => $this->di->id, 'jabatan' => 'Manager', 'no_hp' => '081211110000']);
        $this->ppa = $buat(Role::PETUGAS, 'ppa', ['daerah_irigasi_id' => $this->di->id]);
        $this->ppa->subDaerahIrigasi()->attach($this->sub1);
        $this->pimpinan = $buat(Role::PIMPINAN, 'kasatker');
        $this->koor = $buat(Role::KOORDINATOR, 'koor', ['wilayah_satgas_id' => $w->id]);
        $p = fn ($kode, $tingkat, $urut, $x = []) => PintuAir::create(['daerah_irigasi_id' => $this->di->id, 'kode' => $kode, 'jenis' => $tingkat === 'utama' ? 'intake' : 'bagi', 'tingkat' => $tingkat, 'urutan' => $urut] + $x);
        $this->intake = $p('INTAKE', 'utama', 1);
        $this->bp1 = $p('BP.1', 'primer', 2, ['debit_rencana_lps' => 600]);
        $this->bp2 = $p('BP.2', 'primer', 3);
    }

    private function air(array $ubah = []): array
    {
        return $ubah + [
            'daerah_irigasi_id' => $this->di->id, 'tanggal' => today()->toDateString(), 'jam' => '07:15', 'jenis' => 'rutin', 'mt' => 1,
            'cuaca' => 'cerah', 'curah_hujan_mm' => 0,
            'tma_sungai_cm' => 80, 'debit_sungai_m3s' => 2.5, 'melimpas' => '1', 'tinggi_limpasan_cm' => 5, 'bukaan_intake_cm' => 40,
            'tma_intake_cm' => 60, 'debit_intake_lps' => 900, 'bukaan_penguras_cm' => 0,
            'kondisi_bendung' => 'B', 'kondisi_pintu_intake' => 'RR', 'kondisi_kantong_lumpur' => 'Sd',
            'titik' => [$this->bp1->id => ['tma' => 50, 'debit' => 500, 'kebutuhan' => 600, 'kondisi_saluran' => 'S', 'catatan' => 'endapan']],
            'status_pemberian' => 'rotasi', 'atas_perintah' => 'Pengamat',
            'catatan' => 'Sedimen di intake', 'usulan' => 'Pengerukan kantong lumpur', 'dokumentasi' => ['papan_duga', 'intake'],
            'lat' => -10.1, 'lng' => 123.7,
        ];
    }

    public function test_manager_mengisi_laporan_air_ppa_tidak_bisa(): void
    {
        $this->actingAs($this->manager)->get(route('laporan-air.create'))->assertOk()
            ->assertSee('Bangunan utama (bendung / intake)')->assertSee('Saluran primer (titik ukur / bangunan bagi)')->assertSee('Rekap laporan PPA');
        $this->actingAs($this->manager)->post(route('laporan-air.store'), $this->air(['tma_sungai_cm' => '']))->assertSessionHasErrors('tma_sungai_cm');
        $this->actingAs($this->manager)->post(route('laporan-air.store'), $this->air(['jenis' => 'insidental']))->assertSessionHasErrors('alasan');
        $this->actingAs($this->manager)->post(route('laporan-air.store'), $this->air(['foto' => [UploadedFile::fake()->image('intake.jpg', 800, 600)]]))
            ->assertSessionHasNoErrors();
        $l = LaporanAir::firstOrFail();
        $this->assertSame(2.5, $l->debit_sungai_m3s);
        $this->assertTrue($l->melimpas);
        $this->assertSame(1, $l->foto()->count());
        $this->assertSame(StatusKondisi::WASPADA, $l->status());                      // titik ukur sedimentasi
        // titik ukur & intake tercatat sebagai pembacaan pintu
        $this->assertSame('air', $l->sesiPintu->jenis);
        $this->assertEqualsCanonicalizing([$this->bp1->id, $this->intake->id], $l->sesiPintu->pembacaan()->pluck('pintu_air_id')->all());
        $bp = PembacaanPintu::where('pintu_air_id', $this->bp1->id)->first();
        $this->assertSame(-100.0, $bp->selisih());
        $this->assertSame('RR', PembacaanPintu::where('pintu_air_id', $this->intake->id)->value('kondisi_pintu'));

        $this->actingAs($this->manager)->post(route('laporan-air.store'), $this->air())->assertSessionHasErrors('daerah_irigasi_id');   // satu per hari
        $this->actingAs($this->manager)->post(route('laporan-air.store'), $this->air(['daerah_irigasi_id' => $this->diLain->id, 'tanggal' => today()->subDay()->toDateString()]))
            ->assertSessionHasErrors('daerah_irigasi_id');                                                                          // D.I. lain
        $this->actingAs($this->ppa)->get(route('laporan-air.create'))->assertForbidden();
        $this->actingAs($this->manager)->get(route('laporan.create'))->assertForbidden();   // kondisi tanaman = tugas PPA

        $this->actingAs($this->manager)->get(route('laporan-air.show', $l))->assertOk()->assertSee('Sedimen di intake')->assertSee('Rotasi/giliran')
            ->assertSee('BP.1')->assertSee('RR · Rusak ringan')->assertSee('Sd · Sedimen sedang');
        $this->actingAs($this->manager)->put(route('laporan-air.update', $l), $this->air([
            'debit_intake_lps' => 950, 'titik' => [$this->bp1->id => ['tma' => 40, 'debit' => 300, 'kondisi_saluran' => 'K'], $this->bp2->id => ['tma' => '', 'debit' => '']],
        ]))->assertSessionHasNoErrors();
        $l->refresh();
        $this->assertSame(950.0, $l->debit_intake_lps);
        $this->assertSame(StatusKondisi::SIAGA, $l->status());                        // titik ukur kering
        $this->assertSame(2, $l->sesiPintu->pembacaan()->count());                     // BP.2 kosong tidak dicatat
        $this->actingAs($this->ppa)->get(route('laporan-air.index'))->assertOk()->assertSee('Oesao');
    }

    public function test_rekap_laporan_ppa_di_laporan_manager(): void
    {
        $tersier = PintuAir::create(['daerah_irigasi_id' => $this->di->id, 'sub_daerah_irigasi_id' => $this->sub1->id, 'kode' => 'T1', 'jenis' => 'tersier', 'tingkat' => 'tersier', 'luas_layanan_ha' => 50]);
        $lp = LaporanHarian::create(['tanggal' => today(), 'sub_daerah_irigasi_id' => $this->sub1->id, 'user_id' => $this->ppa->id, 'jenis' => 'rutin']);
        $lp->petak()->create(['pintu_air_id' => $tersier->id, 'jenis_tanaman' => 'P', 'luas_tanam_ha' => 40, 'fase' => 3, 'kecukupan_air' => 'KS']);
        $sesi = SesiPintu::create(['daerah_irigasi_id' => $this->di->id, 'user_id' => $this->ppa->id, 'laporan_harian_id' => $lp->id, 'tanggal' => today(), 'jenis' => 'harian']);
        $sesi->pembacaan()->create(['pintu_air_id' => $tersier->id, 'tanggal' => today(), 'tma_cm' => 10, 'kondisi_pintu' => 'RB']);

        $this->actingAs($this->manager)->post(route('laporan-air.store'), $this->air())->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->get(route('laporan-air.show', LaporanAir::first()))->assertOk()
            ->assertSee('Oesao Kiri')->assertSee('Ppa')->assertSee('Ya (1)')->assertSee('Oesao Kanan')->assertSee('Belum');
        $this->actingAs($this->manager)->get(route('laporan-air.create', ['di' => $this->di->id]))->assertOk()
            ->assertViewHas('rekap', fn ($r) => $r[$this->di->id]['sub'][1]['nama'] === 'Oesao Kiri'
                && $r[$this->di->id]['sub'][1]['pintu_bermasalah'] === 1 && $r[$this->di->id]['sub'][1]['petak_kurang_air'] === 1
                && $r[$this->di->id]['sub'][0]['masuk'] === 0);
    }

    public function test_laporan_tanaman_ppa_tanpa_data_air(): void
    {
        $this->actingAs($this->ppa)->get(route('laporan.create'))->assertOk()->assertDontSee('Debit intake')->assertSee('Kondisi tanaman');
        $this->actingAs($this->ppa)->post(route('laporan.store'), [
            'tanggal' => today()->toDateString(), 'jenis' => 'rutin', 'sub_daerah_irigasi_id' => $this->sub1->id,
            'petak' => [0 => ['jenis_tanaman' => 'P', 'luas_tanam_ha' => 50, 'fase' => 2, 'kecukupan_air' => 'C']],
            'kendala' => 'aman', 'lat' => -10.1, 'lng' => 123.7,
        ])->assertSessionHasNoErrors();
        $l = LaporanHarian::firstOrFail();
        $this->assertNull($l->debit_intake_lps);
        $this->assertNull($l->kondisi_saluran);
        $this->assertSame(StatusKondisi::AMAN, $l->status());
        $this->actingAs($this->ppa)->get(route('laporan.show', $l))->assertOk()->assertDontSee('Form lama');
    }

    public function test_kondisi_saluran_laporan_air_ikut_status_rekap_dan_peta(): void
    {
        $lp = LaporanHarian::create(['tanggal' => today(), 'sub_daerah_irigasi_id' => $this->sub1->id, 'user_id' => $this->ppa->id]);
        $lp->petak()->create(['jenis_tanaman' => 'P', 'luas_tanam_ha' => 50, 'fase' => 2, 'kecukupan_air' => 'C']);
        $lp->hitungTotal();
        $this->actingAs($this->manager)->post(route('laporan-air.store'), $this->air(['titik' => [$this->bp1->id => ['tma' => 10, 'debit' => 0, 'kondisi_saluran' => 'K']]]))
            ->assertSessionHasNoErrors();
        $a = LaporanAir::firstOrFail();
        $a->foto()->create(['path' => 'laporan-air/x.jpg']);

        $ring = app(KondisiService::class)->ringkasanDi(today())->get($this->di->id);
        $this->assertSame(StatusKondisi::SIAGA, $ring['status']);            // tanaman aman, titik ukur primer kering → siaga
        $this->assertSame(900.0, $ring['debit_total_lps']);

        $r = app(RekapService::class)->rekap(Periode::dariRequest(new Request(['periode' => 'rentang', 'dari' => today()->toDateString(), 'sampai' => today()->toDateString()])), null);
        $d = collect($r['di'])->firstWhere('id', $this->di->id);
        $this->assertSame(StatusKondisi::SIAGA, $d['terberat']);
        $this->assertSame(900.0, $d['debit_rata_lps']);
        $this->assertSame(2.5, $d['debit_sungai_rata_m3s']);

        $this->actingAs($this->manager)->getJson(route('api.di', $this->di))->assertOk()
            ->assertJsonPath('air.debit_sungai', 2.5)->assertJsonPath('air.status', StatusKondisi::SIAGA)
            ->assertJsonPath('foto_harian.0.laporan.0.sub', 'Laporan Manager Air · bendung, intake & saluran primer')
            ->assertJsonPath('riwayat.29.status', StatusKondisi::SIAGA);
        $this->getJson(route('api.publik.di', $this->di))->assertOk()->assertJsonPath('air.kondisi', 'Saluran primer: 1 kering · Rotasi/giliran')->assertJsonMissingPath('air.debit_intake');
        $this->assertStringNotContainsString('"debit_total_lps"', $this->getJson(route('api.publik.peta'))->getContent());
        $this->actingAs($this->manager)->get(route('dashboard'))->assertOk()->assertSee('D.I. sudah ada laporan Manager Air');
    }

    public function test_manager_hanya_di_dan_sub_miliknya_diatur_pimpinan(): void
    {
        // tanpa centang Sub-D.I. → seluruh D.I.-nya; D.I. lain tidak terlihat
        $this->assertEqualsCanonicalizing([$this->sub1->id, $this->sub2->id], $this->manager->subIdsTerjangkau());
        $this->actingAs($this->manager)->get(route('master.daerah-irigasi.index'))->assertOk()->assertSee('Oesao')->assertDontSee('Kambaniru');
        $this->actingAs($this->manager)->get(route('master.daerah-irigasi.show', $this->diLain))->assertForbidden();
        $kej = Kejadian::create(['tanggal_lapor' => today(), 'daerah_irigasi_id' => $this->diLain->id, 'kabupaten_id' => $this->diLain->kabupaten_id, 'jenis_kegagalan' => 'Kekeringan', 'status_kegagalan' => 'Potensi', 'status_penanganan' => 'Perlu Penanganan', 'desa' => 'Desa Sumba']);
        $this->actingAs($this->manager)->get(route('kejadian.index'))->assertOk()->assertDontSee('Desa Sumba');
        $this->actingAs($this->manager)->get(route('kejadian.show', $kej))->assertForbidden();

        // Koordinator tidak bisa mengubah Manager; Pimpinan bisa, termasuk membatasi Sub-D.I.
        $this->actingAs($this->koor)->get(route('petugas.edit', $this->manager))->assertForbidden();
        $this->actingAs($this->manager)->get(route('petugas.edit', $this->manager))->assertForbidden();
        $this->actingAs($this->pimpinan)->put(route('petugas.update', $this->manager), [
            'name' => 'Manager', 'no_hp' => '081211110000', 'role' => Role::MANAGER, 'jabatan' => 'Manager',
            'daerah_irigasi_id' => $this->di->id, 'sub_ids' => [$this->sub2->id], 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $m = User::find($this->manager->id);
        $this->assertSame([$this->sub2->id], $m->subIdsTerjangkau());
        $this->actingAs($m)->get(route('laporan.index', ['di' => $this->di->id]))->assertOk();
    }
}
