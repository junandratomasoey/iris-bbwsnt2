<?php

namespace Tests\Feature;

use App\Models\DaerahIrigasi;
use App\Models\Kabupaten;
use App\Models\LaporanHarian;
use App\Models\LaporanPetak;
use App\Models\PembacaanPintu;
use App\Models\PintuAir;
use App\Models\SesiPintu;
use App\Models\SubDaerahIrigasi;
use App\Models\User;
use App\Services\KondisiService;
use App\Services\PintuService;
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

    /** Laporan PPA: petak = [pintu tersier id => isian], baca = [pintu id => isian]. */
    private function laporan(array $ubah = []): array
    {
        return $ubah + [
            'tanggal' => today()->toDateString(), 'jam' => '07:30', 'jenis' => 'rutin', 'sub_daerah_irigasi_id' => $this->sub->id,
            'petak' => [], 'baca' => [],
        ];
    }

    private function petak(float $tanam, string $kecukupan = 'C', float $kering = 0, array $lain = []): array
    {
        return $lain + ['jenis_tanaman' => 'P', 'luas_tanam_ha' => $tanam, 'fase' => 3, 'umur_hst' => 45, 'kecukupan_air' => $kecukupan, 'luas_kering_ha' => $kering];
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
        $this->actingAs($this->ppaA)->get(route('dashboard'))->assertOk()->assertSee('Pembacaan pintu air saya')->assertSee('Petak tugas: BOs.1 ki')->assertSee('Belum ada laporan sejak Senin');
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
        $this->tugaskan();   // t1: A (utama), B (pengganti); t2: B; intake: Manager
        $this->actingAs($this->ppaA)->get(route('laporan.create', ['sub' => $this->sub->id]))->assertOk()
            ->assertSee('Kondisi tanaman per petak tersier')->assertSee('Pembacaan pintu air (sekunder/tersier)')->assertDontSee('INTAKE');

        $t1 = $this->t1->id;
        $t2 = $this->t2->id;
        // petak tugas utama & TMA pintunya wajib
        $this->actingAs($this->ppaA)->post(route('laporan.store'), $this->laporan(['baca' => [$t1 => ['tma' => 20]]]))->assertSessionHasErrors("petak.$t1.jenis_tanaman");
        $this->actingAs($this->ppaA)->post(route('laporan.store'), $this->laporan(['petak' => [$t1 => $this->petak(40)]]))->assertSessionHasErrors("baca.$t1.tma");
        $this->actingAs($this->ppaA)->post(route('laporan.store'), $this->laporan(['petak' => [$t1 => $this->petak(60)], 'baca' => [$t1 => ['tma' => 20]]]))
            ->assertSessionHasErrors("petak.$t1.luas_tanam_ha");                                                                // > luas layanan 50 Ha
        $this->actingAs($this->ppaA)->post(route('laporan.store'), $this->laporan([
            'petak' => [$t1 => $this->petak(40), $t2 => $this->petak(80, 'KB')],     // t2 milik B → diabaikan
            'baca' => [$t1 => ['tma' => 20, 'debit' => 35, 'kondisi_pintu' => 'B', 'kondisi_saluran' => 'N'], $t2 => ['tma' => 99]],
        ]))->assertSessionHasNoErrors();
        $la = LaporanHarian::firstOrFail();
        $this->assertSame([$t1], $la->petak()->pluck('pintu_air_id')->all());
        $this->assertSame([$t1], $la->sesiPintu->pembacaan()->pluck('pintu_air_id')->all());
        $this->actingAs($this->ppaA)->post(route('laporan.store'), $this->laporan(['petak' => [$t1 => $this->petak(40)], 'baca' => [$t1 => ['tma' => 20]]]))
            ->assertSessionHasErrors('sub_daerah_irigasi_id');                                                                   // satu per petugas per Sub-D.I. per hari

        // B (pengganti t1, utama t2): wajib t2 saja; boleh ikut mengisi t1
        $this->actingAs($this->ppaB)->post(route('laporan.store'), $this->laporan([
            'petak' => [$t2 => $this->petak(80, 'KS', 45)], 'baca' => [$t2 => ['tma' => 30, 'kondisi_pintu' => 'RR']],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(2, LaporanHarian::where('sub_daerah_irigasi_id', $this->sub->id)->count());

        // kondisi tanaman adalah tugas PPA: Manager tidak bisa membuka/mengirim form laporan PPA, tetapi tetap bisa melihat & memverifikasi
        $this->actingAs($this->manager)->get(route('laporan.create'))->assertForbidden();
        $this->actingAs($this->manager)->post(route('laporan.store'), $this->laporan(['tanggal' => today()->subDay()->toDateString()]))->assertForbidden();
        $this->actingAs($this->manager)->get(route('laporan.index'))->assertOk()->assertSee('Baca pintu air')->assertSee('BOs.1 ki');
        $this->actingAs($this->manager)->post(route('laporan.verifikasi', $la), ['keputusan' => 'disetujui'])->assertSessionHasNoErrors();
        $this->assertSame('disetujui', $la->fresh()->status_verifikasi);
    }

    public function test_laporan_per_petak_digabung_untuk_status_dan_rekap(): void
    {
        $buat = function ($user, $tgl, array $petak) {
            $l = LaporanHarian::create(['tanggal' => $tgl, 'sub_daerah_irigasi_id' => $this->sub->id, 'user_id' => $user->id, 'jenis' => 'rutin']);
            foreach ($petak as $id => $r) {
                $l->petak()->create(['pintu_air_id' => $id ?: null] + $r);
            }
            $l->hitungTotal();

            return $l;
        };
        $buat($this->ppaA, today()->subDay()->toDateString(), [0 => $this->petak(200)]);               // laporan lama tingkat Sub-D.I.
        $buat($this->ppaA, today()->toDateString(), [$this->t1->id => $this->petak(40)]);
        $buat($this->ppaB, today()->toDateString(), [$this->t2->id => $this->petak(80, 'KB', 45)]);   // kering berat → kritis

        $g = app(KondisiService::class)->laporanTerkini(today())->get($this->sub->id);
        $this->assertSame(120.0, (float) $g->luas_tanam_ha);      // 40 + 80 (laporan Sub-D.I. lama tidak ikut)
        $this->assertSame(45.0, (float) $g->luas_kering_ha);
        $this->assertSame(StatusKondisi::KRITIS, $g->status());   // petak terberat
        $this->assertSame(1, $g->kekurangan_air);
        $this->assertCount(2, $g->petak_rinci);

        $ring = app(KondisiService::class)->ringkasanDi(today())->get($this->di->id);
        $this->assertSame(120.0, $ring['luas_tanam_ha']);
        $this->assertSame(1, $ring['sub_lapor']);

        $r = app(RekapService::class)->rekap(Periode::dariRequest(new Request(['periode' => 'rentang', 'dari' => today()->subDay()->toDateString(), 'sampai' => today()->toDateString()])), null);
        $d = collect($r['di'])->firstWhere('id', $this->di->id);
        $this->assertSame(StatusKondisi::KRITIS, $d['terberat']);
        $this->assertSame(100, $d['kepatuhan']);                  // kepatuhan per pekan: Sub-D.I. melapor di setiap pekan periode

        $this->actingAs($this->manager)->getJson(route('api.di', $this->di))->assertOk()
            ->assertJsonCount(2, 'sub.0.laporan.petak')->assertJsonPath('sub.0.laporan.petak.0.kode', 'BOs.1 ka')
            ->assertJsonPath('sub.0.laporan.petak.0.status', StatusKondisi::KRITIS)->assertJsonPath('sub.0.laporan.petak.0.kecukupan', 'KB');
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
        $n = app(PintuService::class)->neraca($this->di->id, SesiPintu::seninIni());
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

    public function test_ppa_mengisi_pembacaan_pintu_di_laporan(): void
    {
        $this->tugaskan();
        $t1 = $this->t1->id;
        $this->actingAs($this->ppaA)->post(route('laporan.store'), $this->laporan([
            'petak' => [$t1 => $this->petak(40)],
            'baca' => [
                $t1 => ['tma' => 22, 'bukaan' => 15, 'debit' => 35, 'kondisi_pintu' => 'B', 'kondisi_saluran' => 'S', 'catatan' => 'sedimen'],
                $this->sek->id => ['tma' => 48, 'debit' => 120],   // sekunder belum ditugaskan → boleh
                $this->intake->id => ['tma' => 99],                // intake = tugas Manager → diabaikan
            ],
        ]))->assertSessionHasNoErrors();

        $l = LaporanHarian::firstOrFail();
        $sesi = $l->sesiPintu;
        $this->assertSame('harian', $sesi->jenis);
        $this->assertEqualsCanonicalizing([$t1, $this->sek->id], $sesi->pembacaan()->pluck('pintu_air_id')->all());
        $b = PembacaanPintu::where('pintu_air_id', $t1)->first();
        $this->assertSame([22.0, 15.0, 'S'], [$b->tma_cm, $b->bukaan_cm, $b->kondisi_saluran]);
        $this->assertTrue($b->bermasalah());

        // ikut neraca minggu ini
        $n = app(PintuService::class)->neraca($this->di->id, SesiPintu::seninIni());
        $this->assertSame(35.0, collect($n['neraca'])->firstWhere('pintu.id', $this->sek->id)['jumlah_anak']);
        $this->assertSame(StatusKondisi::AMAN, collect($n['merata'])->firstWhere('pintu.id', $t1)['status_lahan']);

        // debit boleh kosong (tidak ada tabel debit), TMA saja
        $this->actingAs($this->ppaA)->get(route('pembacaan-pintu.edit', $sesi))->assertForbidden();   // diubah lewat laporan
        $this->actingAs($this->ppaA)->put(route('laporan.update', $l), $this->laporan(['petak' => [$t1 => $this->petak(40, 'KR')], 'baca' => [$t1 => ['tma' => 20]]]))
            ->assertSessionHasNoErrors();
        $this->assertSame([$t1], $sesi->pembacaan()->pluck('pintu_air_id')->all());
        $this->assertNull(PembacaanPintu::where('pintu_air_id', $t1)->value('debit_lps'));
        $this->assertSame(StatusKondisi::WASPADA, $l->fresh()->status());
        $this->actingAs($this->ppaA)->get(route('laporan.show', $l))->assertOk()->assertSee('Pembacaan pintu air')->assertSee('BOs.1 ki')->assertSee('KR · Kurang');

        $this->actingAs($this->ppaA)->delete(route('laporan.destroy', $l));
        $this->assertSame(0, SesiPintu::count());
        $this->assertSame(0, PembacaanPintu::count());
        $this->assertSame(0, LaporanPetak::count());
    }
}
