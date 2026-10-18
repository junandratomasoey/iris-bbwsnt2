<?php

namespace Database\Seeders;

use App\Models\DaerahIrigasi;
use App\Models\User;
use App\Support\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Akun admin + akun petugas lapangan dari sheet "Daftar Kontak Petugas Irigasi".
 * Setiap akun mendapat password acak dan wajib menggantinya saat login pertama.
 * Daftar password awal ditulis ke storage/app/akun-awal.csv (tidak masuk git)
 * untuk dibagikan Admin ke masing-masing petugas.
 */
class PenggunaSeeder extends Seeder
{
    private array $csv = [];

    public function run(): void
    {
        $adminPass = env('IRIS_ADMIN_PASSWORD') ?: Str::password(12, symbols: false);
        $admin = User::updateOrCreate(['username' => 'admin'], [
            'name' => 'Administrator IRIS', 'password' => $adminPass, 'role' => Role::SUPER_ADMIN,
            'jabatan' => 'Admin IT', 'must_change_password' => true,
        ]);
        if ($admin->wasRecentlyCreated) {
            $this->csv[] = ['admin', $adminPass, $admin->name, Role::SUPER_ADMIN, '', ''];
        }

        $diByKunci = [];
        foreach (DaerahIrigasi::with('subDaerahIrigasi')->get() as $di) {
            foreach ($di->alias ?? [] as $a) {
                $diByKunci[SeedData::kunci($a)] = $di;
            }
            $diByKunci[SeedData::kunci($di->nama)] = $di;
        }

        // gabungkan baris ganda (orang yang sama tercatat di beberapa Sub-D.I.)
        $orang = [];
        foreach (SeedData::get('petugas') as $p) {
            $hp = normalisasi_hp($p['no_hp']);
            $k = $hp ?: Str::slug($p['nama']);
            $orang[$k] ??= $p + ['hp' => $hp, 'subs' => []];
            $orang[$k]['subs'][] = [$p['di_excel'], $p['sub_excel']];
            // jabatan tertinggi menang: Manager > POB > lainnya
            $rank = ['Manager' => 3, 'POB' => 2];
            if (($rank[$p['peran']] ?? 1) > ($rank[$orang[$k]['peran']] ?? 1)) {
                $orang[$k]['peran'] = $p['peran'];
            }
        }

        foreach ($orang as $p) {
            $di = $diByKunci[SeedData::kunci($p['di_excel'])] ?? null;
            $role = match ($p['peran']) {
                'Manager' => Role::MANAGER,
                'POB' => Role::POB,
                default => Role::PETUGAS,
            };
            $username = $p['hp'] ?: $this->usernameDariNama($p['nama']);
            $pass = Str::password(8, symbols: false);

            $user = User::firstOrNew(['username' => $username]);
            $baru = ! $user->exists;
            $user->fill([
                'name' => Str::title(Str::lower($p['nama'])) === $p['nama'] ? $p['nama'] : $this->rapikanNama($p['nama']),
                'no_hp' => $p['hp'],
                'role' => $role,
                'jabatan' => $p['peran'],
                'daerah_irigasi_id' => $di?->id,
                'status_kontak' => $p['status_kontak'],
            ]);
            if ($baru) {
                $user->password = $pass;
                $user->must_change_password = true;
            }
            $user->save();

            if ($di) {
                $subIds = [];
                foreach ($p['subs'] as [$diNama, $subNama]) {
                    $sub = $di->subDaerahIrigasi->first(fn ($s) => SeedData::kunci($s->nama) === SeedData::kunci($subNama));
                    if ($sub) {
                        $subIds[] = $sub->id;
                    }
                }
                $user->subDaerahIrigasi()->syncWithoutDetaching(array_unique($subIds));
            }

            if ($baru) {
                $this->csv[] = [$username, $pass, $user->name, $role, $p['hp'], $di?->nama];
            }
        }

        $this->tulisCsv();
    }

    private function usernameDariNama(string $nama): string
    {
        $dasar = Str::limit(Str::slug($nama, '.'), 40, '');
        $u = $dasar;
        $i = 2;
        while (User::where('username', $u)->exists()) {
            $u = $dasar.$i++;
        }

        return $u;
    }

    private function rapikanNama(string $nama): string
    {
        // "SALMON DUKA" → "Salmon Duka", gelar seperti S.ST tetap
        return preg_replace_callback('/\b([A-Z]{2,})\b/', fn ($m) => Str::ucfirst(Str::lower($m[1])), $nama);
    }

    private function tulisCsv(): void
    {
        if (! $this->csv) {
            return;
        }
        $file = storage_path('app/akun-awal-'.now()->format('Ymd-His').'.csv');
        $fh = fopen($file, 'w');
        fputcsv($fh, ['username', 'password_awal', 'nama', 'role', 'no_hp', 'daerah_irigasi']);
        foreach ($this->csv as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);
        $this->command?->warn('Password awal '.count($this->csv).' akun ditulis ke '.$file.' — bagikan ke petugas lalu hapus file ini.');
    }
}
