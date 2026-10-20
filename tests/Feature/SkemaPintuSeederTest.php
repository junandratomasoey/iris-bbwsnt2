<?php

namespace Tests\Feature;

use App\Models\Bangunan;
use App\Models\DaerahIrigasi;
use App\Models\PintuAir;
use App\Models\SkemaJaringan;
use App\Models\User;
use App\Services\PintuService;
use App\Support\Role;
use Database\Seeders\SkemaPintuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SkemaPintuSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_skema_manikin_masuk_sebagai_jaringan_pintu(): void
    {
        Storage::fake('uploads');
        $di = DaerahIrigasi::create(['kode' => 'DI-01', 'nama' => 'Manikin']);
        $sub = $di->subDaerahIrigasi()->create(['nama' => 'Manikin', 'luas_ha' => 1525]);
        Bangunan::create(['daerah_irigasi_id' => $di->id, 'nama' => 'Bendung Manikin', 'jenis' => 'bendung', 'lat' => -10.1392167, 'lng' => 123.6889222]);
        foreach (['Aprianus Ully Djewa' => Role::POB, 'Derric Darius Riwu' => Role::PETUGAS, 'Anton R. Dacosta' => Role::PETUGAS, 'Ambrosius Mone' => Role::PETUGAS] as $nama => $role) {
            User::create(['name' => $nama, 'username' => \Illuminate\Support\Str::slug($nama), 'password' => 'x', 'role' => $role, 'daerah_irigasi_id' => $di->id])->subDaerahIrigasi()->attach($sub);
        }

        $this->seed(SkemaPintuSeeder::class);
        $this->seed(SkemaPintuSeeder::class);   // dijalankan ulang: tidak ganda

        $this->assertSame(14, PintuAir::count());
        $this->assertSame(1, SkemaJaringan::count());
        $bm1 = PintuAir::where('kode', 'BM 1')->first();
        $this->assertSame('INTAKE MN', $bm1->hulu->kode);
        $this->assertSame(['BM 2 Kn', 'BM 2 Kr', 'MN. 1Ka'], $bm1->hilir()->orderBy('kode')->pluck('kode')->all());
        $this->assertSame(6, PintuAir::where('tingkat', 'tersier')->where('debit_rencana_lps', 16)->where('luas_layanan_ha', 62)->count());
        $this->assertSame('Ambrosius Mone', PintuAir::where('kode', 'MN.T6')->first()->petugas()->value('name'));
        $this->assertSame(-10.1392167, PintuAir::where('kode', 'INTAKE MN')->value('lat') + 0.0);
        $this->assertSame($sub->id, PintuAir::where('kode', 'MN.T1')->value('sub_daerah_irigasi_id'));

        $n = app(PintuService::class)->neraca($di->id, \App\Models\SesiPintu::seninIni());
        $this->assertCount(7, $n['neraca']);   // 7 titik bercabang: INTAKE, BM 1, BM 2 Kn, BM 2 Kr, SDP Kr-1, SDP Kr-2, SDP Kr-3
    }
}
