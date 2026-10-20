<?php

namespace Tests\Feature;

use App\Models\DaerahIrigasi;
use App\Models\Kabupaten;
use App\Models\LaporanHarian;
use App\Models\PembacaanPintu;
use App\Models\PintuAir;
use App\Models\SesiPintu;
use App\Models\SubDaerahIrigasi;
use App\Models\User;
use App\Services\KondisiService;
use App\Services\RekapService;
use App\Support\Periode;
use App\Support\Role;
use App\Support\StatusKondisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class JaringanPintuTest extends TestCase
{
    use RefreshDatabase;

    private DaerahIrigasi $di;

    private SubDaerahIrigasi $sub;

    private User $manager;

    private User $ppaA;

    private User $ppaB;

    private PintuAir $intake;

    private PintuAir $sek;

    private PintuAir $t1;

    private PintuAir $t2;

    protected function setUp(): void
    {
        parent::setUp();
        $kab = Kabupaten::create(['nama' => 'Kupang']);
        $this->di = DaerahIrigasi::create(['kode' => 'DI-01', 'nama' => 'Oesao', 'kabupaten_id' => $kab->id]);
        $this->sub = $this->di->subDaerahIrigasi()->create(['nama' => 'Oesao Kiri', 'luas_ha' => 300]);
        $buat = fn ($role, $u, $jab = null) => User::create(['name' => ucfirst($u), 'username' => $u, 'password' => 'x', 'role' => $role, 'jabatan' => $jab, 'must_change_password' => false, 'daerah_irigasi_id' => $this->di->id]);
        $this->manager = $buat(Role::MANAGER, 'manager', 'Manager');
        $this->ppaA = $buat(Role::PETUGAS, 'ppaa', 'PPA');
        $this->ppaB = $buat(Role::PETUGAS, 'ppab', 'PPA');
        $this->ppaA->subDaerahIrigasi()->attach($this->sub);
        $this->ppaB->subDaerahIrigasi()->attach($this->sub);

        $p = fn ($kode, $tingkat, $hulu = null, $attr = []) => PintuAir::create(['daerah_irigasi_id' => $this->di->id, 'sub_daerah_irigasi_id' => $tingkat === 'utama' ? null : $this->sub->id,
            'kode' => $kode, 'jenis' => $tingkat === 'tersier' ? 'tersier' : 'bagi', 'tingkat' => $tingkat, 'hulu_id' => $hulu?->id] + $attr);
        $this->intake = $p('INTAKE', 'utama');
        $this->sek = $p('BOs.1', 'sekunder', $this->intake);
        $this->t1 = $p('BOs.1 ki', 'tersier', $this->sek, ['luas_layanan_ha' => 50]);
        $this->t2 = $p('BOs.1 ka', 'tersier', $this->sek, ['luas_layanan_ha' => 100]);
    }

    private function laporan(array $ubah = []): array
    {
        return $ubah + [
            'tanggal' => today()->toDateString(), 'sub_daerah_irigasi_id' => $this->sub->id,
            'debit_intake_lps' => 40, 'tma_cm' => 30, 'kondisi_saluran' => 'normal',
            'luas_tanam_ha' => 40, 'luas_hijau_ha' => 40, 'luas_kering_ha' => 0, 'luas_panen_ha' => 0, 'luas_puso_ha' => 0,
        ];
    }

    private function tugaskan(): void
    {
        $this->actingAs($this->manager)->post(route('master.pintu-air.penugasan.simpan'), [
            'di' => $this->di->id,
            'tugas' => [
                $this->intake->id => ['utama' => $this->manager->id],
                $this->t1->id => ['utama' => $this->ppaA->id, 'pengganti' => $this->ppaB->id],
                $this->t2->id => ['utama' => $this->ppaB->id],
            ],
        ])->assertSessionHas('ok', 'Penugasan 3 pintu diperbarui.');
    }

    public function test_penugasan_membatasi_pintu_yang_bisa_diisi_dan_titik_gps(): void
    {
        $this->actingAs($this->manager)->get(route('master.pintu-air.penugasan', ['di' => $this->di->id]))->assertOk()->assertSee('Ppaa');
        $this->tugaskan();
        $this->actingAs($this->ppaA)->get(route('master.pintu-air.penugasan', ['di' => $this->di->id]))->assertForbidden();

        // PPA A: form menampilkan tugasnya + pintu sekunder yang belum ditugaskan; intake milik manager tidak tampil
        $this->actingAs($this->ppaA)->get(route('pembacaan-pintu.create', ['di' => $this->di->id]))->assertOk()
            ->assertSee('BOs.1 ki')->assertSee('tugas saya')->assertSee('Sekunder')   // pintu sekunder BOs.1 belum ditugaskan → boleh
            ->assertDontSee('BOs.1 ka')->assertDontSee('INTAKE');                              // milik PPA B dan Manager
        // t2 milik B saja → A ditolak
        $this->actingAs($this->ppaA)->post(route('pembacaan-pintu.store'), [
            'daerah_irigasi_id' => $this->di->id, 'tanggal' => today()->toDateString(), 'jenis' => 'rutin',
            'baca' => [$this->t2->id => ['debit' => 10]],
        ])->assertForbidden();

        // A membaca t1 + merekam titik GPS pintu yang belum punya titik
        $this->actingAs($this->ppaA)->post(route('pembacaan-pintu.store'), [
            'daerah_irigasi_id' => $this->di->id, 'tanggal' => today()->toDateString(), 'jenis' => 'rutin',
            'baca' => [$this->t1->id => ['debit' => 30, 'lat' => -10.1234567, 'lng' => 123.7654321, 'akurasi' => 6]],
        ])->assertSessionHasNoErrors();
        $this->t1->refresh();
        $this->assertSame(-10.1234567, $this->t1->lat);
        $this->assertSame($this->ppaA->id, $this->t1->titik_oleh);
        $this->assertSame(6, $this->t1->titik_akurasi_m);

        // B (pengganti t1) boleh memperbarui titik t1; titik saja tanpa pembacaan juga diterima
        $this->actingAs($this->ppaB)->post(route('pembacaan-pintu.store'), [
            'daerah_irigasi_id' => $this->di->id, 'tanggal' => today()->toDateString(), 'jenis' => 'rutin',
            'baca' => [$this->t1->id => ['lat' => -10.2, 'lng' => 123.8, 'akurasi' => 4]],
        ])->assertSessionHas('ok', '1 titik pintu disimpan (tanpa pembacaan).');
        $this->assertSame(-10.2, $this->t1->fresh()->lat);
        $this->assertSame(1, SesiPintu::count());

        // dashboard petugas: pintu saya
        $this->actingAs($this->ppaA)->get(route('dashboard'))->assertOk()->assertSee('Pembacaan pintu air saya')->assertSee('Petak BOs.1 ki');
    }

    public function test_pintu_hulu_harus_lebih_hulu_dan_tidak_melingkar(): void
    {
        $ubah = fn ($p, $hulu) => $this->actingAs($this->manager)->put(route('master.pintu-air.update', $p), ['kode' => $p->kode, 'jenis' => $p->jenis, 'tingkat' => $p->tingkat, 'hulu_id' => $hulu->id, 'aktif' => 1]);
        $ubah($this->sek, $this->t1)->assertSessionHasErrors('hulu_id');            // tersier tidak bisa jadi hulu
        $ubah($this->intake, $this->sek)->assertSessionHasErrors('hulu_id');        // sekunder di hulu intake? tidak
        $this->intake->update(['tingkat' => 'primer']);
        $ubah($this->intake->fresh(), $this->sek)->assertSessionHasErrors('hulu_id');   // melingkar
    }

    public function test_ppa_wajib_lapor_per_petak_tugasnya(): void
    {
        $this->tugaskan();
        $this->actingAs($this->ppaA)->get(route('laporan.create', ['sub' => $this->sub->id]))->assertOk()->assertSee('Petak tersier yang dilaporkan');

        $this->actingAs($this->ppaA)->post(route('laporan.store'), $this->laporan())->assertSessionHasErrors('pintu_air_id');
        $this->actingAs($this->ppaA)->post(route('laporan.store'), $this->laporan(['pintu_air_id' => $this->t2->id]))->assertSessionHasErrors('pintu_air_id');   // milik B
        $this->actingAs($this->ppaA)->post(route('laporan.store'), $this->laporan(['pintu_air_id' => $this->t1->id, 'luas_padi_ha' => 60]))
            ->assertSessionHasErrors('luas_padi_ha');                                                                                                    // > luas layanan 50 Ha
        $this->actingAs($this->ppaA)->post(route('laporan.store'), $this->laporan(['pintu_air_id' => $this->t1->id]))->assertSessionHasNoErrors();
        $this->actingAs($this->ppaA)->post(route('laporan.store'), $this->laporan(['pintu_air_id' => $this->t1->id]))->assertSessionHasErrors('sub_daerah_irigasi_id');   // satu per petak per hari
        $this->actingAs($this->ppaB)->post(route('laporan.store'), $this->laporan(['pintu_air_id' => $this->t2->id, 'luas_padi_ha' => 80, 'luas_kering_ha' => 45, 'kondisi_saluran' => 'kurang']))
            ->assertSessionHasNoErrors();                                                                                                              // petak lain, Sub-D.I. sama, hari sama
        $this->assertSame(2, LaporanHarian::where('sub_daerah_irigasi_id', $this->sub->id)->count());
        $this->assertSame($this->t1->id, LaporanHarian::first()->pintu_air_id);

        // kondisi tanaman adalah tugas PPA: Manager tidak bisa membuka/mengirim form laporan harian, tetapi tetap bisa melihat & memverifikasi
        $this->actingAs($this->manager)->get(route('laporan.create'))->assertForbidden();
        $this->actingAs($this->manager)->post(route('laporan.store'), $this->laporan(['tanggal' => today()->subDay()->toDateString()]))->assertForbidden();
        $this->actingAs($this->manager)->get(route('laporan.index'))->assertOk()->assertDontSee('Isi laporan hari ini')->assertSee('Baca pintu air');
        $this->actingAs($this->manager)->post(route('laporan.verifikasi', LaporanHarian::first()), ['keputusan' => 'disetujui'])->assertSessionHasNoErrors();
        $this->assertSame('disetujui', LaporanHarian::first()->status_verifikasi);
    }

    public function test_laporan_per_petak_digabung_untuk_status_dan_rekap(): void
    {
        $l = fn ($petak, $tanam, $kering, $tgl = null, $kondisi = 'normal') => LaporanHarian::create($this->laporan([
            'tanggal' => $tgl ?? today()->toDateString(), 'pintu_air_id' => $petak?->id, 'user_id' => $this->ppaA->id,
            'luas_tanam_ha' => $tanam, 'luas_padi_ha' => $tanam, 'luas_hijau_ha' => $tanam - $kering, 'luas_kering_ha' => $kering, 'kondisi_saluran' => $kondisi,
        ]));
        $l(null, 200, 0, today()->subDay()->toDateString());   // laporan lama tingkat Sub-D.I.
        $l($this->t1, 40, 0);
        $l($this->t2, 80, 45, null, 'kurang');                  // 56% kering → kritis

        $g = app(KondisiService::class)->laporanTerkini(today())->get($this->sub->id);
        $this->assertSame(120.0, (float) $g->luas_tanam_ha);      // 40 + 80 (laporan Sub-D.I. lama tidak ikut)
        $this->assertSame(45.0, (float) $g->luas_kering_ha);
        $this->assertSame('kurang', $g->kondisi_saluran);
        $this->assertSame(StatusKondisi::KRITIS, $g->status());   // petak terberat
        $this->assertCount(2, $g->petak_rinci);

        $ring = app(KondisiService::class)->ringkasanDi(today())->get($this->di->id);
        $this->assertSame(120.0, $ring['luas_tanam_ha']);
        $this->assertSame(1, $ring['sub_lapor']);

        $r = app(RekapService::class)->rekap(Periode::dariRequest(new Request(['periode' => 'rentang', 'dari' => today()->subDay()->toDateString(), 'sampai' => today()->toDateString()])), null);
        $d = collect($r['di'])->firstWhere('id', $this->di->id);
        $this->assertSame(120.0, $d['puncak_tanam_ha']);          // puncak per petak dijumlah, tanpa dobel laporan Sub-D.I.
        $this->assertSame(100, $d['kepatuhan']);                  // 1 Sub-D.I. × 2 hari, keduanya ada laporan
        $this->assertSame(StatusKondisi::KRITIS, $d['terberat']);

        $this->actingAs($this->manager)->getJson(route('api.di', $this->di))->assertOk()
            ->assertJsonCount(2, 'sub.0.laporan.petak')->assertJsonPath('sub.0.laporan.petak.0.kode', 'BOs.1 ka')->assertJsonPath('sub.0.laporan.petak.0.status', StatusKondisi::KRITIS);
        $this->getJson(route('api.publik.di', $this->di))->assertOk()->assertJsonMissingPath('pintu')->assertJsonMissingPath('sub.0.laporan.petak.0.url');
    }

    public function test_neraca_pemerataan_dan_peta_pintu(): void
    {
        $this->intake->update(['lat' => -10.1, 'lng' => 123.7]);
        $this->sek->update(['lat' => -10.11, 'lng' => 123.71, 'debit_min_lps' => 50]);
        $sesi = SesiPintu::create(['daerah_irigasi_id' => $this->di->id, 'user_id' => $this->manager->id, 'tanggal' => today(), 'jenis' => 'rutin']);
        foreach ([[$this->intake, 200], [$this->sek, 100], [$this->t1, 40], [$this->t2, 50]] as [$p, $d]) {
            PembacaanPintu::create(['sesi_pintu_id' => $sesi->id, 'pintu_air_id' => $p->id, 'tanggal' => today(), 'debit_lps' => $d]);
        }
        $n = app(\App\Services\PintuService::class)->neraca($this->di->id, SesiPintu::seninIni());
        $sek = collect($n['neraca'])->firstWhere('pintu.id', $this->sek->id);
        $this->assertSame(90.0, $sek['jumlah_anak']);
        $this->assertSame(10.0, $sek['selisih']);
        $this->assertSame(10.0, $sek['persen']);
        $this->assertTrue($sek['lengkap']);
        $this->assertSame(0.6, $n['rata_per_ha']);                         // 90 L/dt ÷ 150 Ha
        $this->assertSame($this->t2->id, $n['merata'][0]['pintu']->id);    // 0,5 L/dt/Ha → paling sedikit
        $this->assertSame(83, $n['merata'][0]['rasio']);
        $this->assertSame(133, $n['merata'][1]['rasio']);

        $this->actingAs($this->manager)->get(route('pintu.neraca', ['di' => $this->di->id]))->assertOk()
            ->assertSee('Neraca air per cabang')->assertSee('83%')->assertSee('Lengkap');
        $this->actingAs($this->manager)->getJson(route('api.di', $this->di))->assertOk()
            ->assertJsonCount(2, 'pintu')->assertJsonPath('pintu.1.hulu', [-10.1, 123.7]);
    }
}
