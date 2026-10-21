<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Cadangan data: database (.sql.gz, harian) dan foto unggahan (.zip, mingguan).
 * File disimpan di storage/app/cadangan (tidak bisa dibuka dari web), dan file lama dihapus otomatis.
 * Database ditulis dengan PHP sendiri (tanpa mysqldump), agar tetap jalan di hosting bersama yang mematikan exec().
 */
class CadanganService
{
    /** Jumlah cadangan yang disimpan. */
    public const SIMPAN_DB = 14;

    public const SIMPAN_FOTO = 4;

    public function folder(): string
    {
        $f = config('services.cadangan.folder') ?: storage_path('app/cadangan');
        File::ensureDirectoryExists($f);
        if (! File::exists($f.'/.htaccess')) {
            File::put($f.'/.htaccess', "Require all denied\nDeny from all\n");
        }

        return $f;
    }

    /** @return string nama file */
    public function buatDatabase(): string
    {
        $nama = 'iris-db-'.now()->format('Ymd-His').'.sql.gz';
        $path = $this->folder().'/'.$nama;
        $gz = gzopen($path.'.tmp', 'wb6');
        if (! $gz) {
            throw new RuntimeException('Tidak bisa menulis file cadangan di '.$this->folder());
        }
        try {
            $this->tulisDatabase(fn (string $s) => gzwrite($gz, $s));
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($path.'.tmp');
            throw $e;
        }
        gzclose($gz);
        rename($path.'.tmp', $path); // file baru "ada" setelah selesai ditulis
        $this->bersihkan('iris-db-', self::SIMPAN_DB);

        return $nama;
    }

    /** @return string nama file */
    public function buatFoto(): string
    {
        $nama = 'iris-foto-'.now()->format('Ymd-His').'.zip';
        $path = $this->folder().'/'.$nama;
        $sumber = public_path('uploads');
        $zip = new ZipArchive;
        if ($zip->open($path.'.tmp', ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak bisa membuat file zip cadangan foto.');
        }
        $zip->addFromString('BACA.txt', 'Cadangan foto IRIS-BBWSNTII '.now()->format('d-m-Y H:i')."\nSalin isi folder ini ke public/uploads di server untuk memulihkan.\n");
        if (File::isDirectory($sumber)) {
            foreach (File::allFiles($sumber) as $f) {
                $zip->addFile($f->getPathname(), 'uploads/'.str_replace('\\', '/', $f->getRelativePathname()));
                $zip->setCompressionName('uploads/'.str_replace('\\', '/', $f->getRelativePathname()), ZipArchive::CM_STORE); // foto sudah terkompres
            }
        }
        $zip->close();
        rename($path.'.tmp', $path);
        $this->bersihkan('iris-foto-', self::SIMPAN_FOTO);

        return $nama;
    }

    /** @return Collection<int, array{nama: string, jenis: string, ukuran: int, waktu: Carbon}> terbaru dulu */
    public function daftar()
    {
        return collect(File::files($this->folder()))
            ->filter(fn ($f) => preg_match('/^iris-(db|foto)-\d{8}-\d{6}\.(sql\.gz|zip)$/', $f->getFilename()))
            ->map(fn ($f) => [
                'nama' => $f->getFilename(),
                'jenis' => str_starts_with($f->getFilename(), 'iris-db-') ? 'db' : 'foto',
                'ukuran' => $f->getSize(),
                'waktu' => Carbon::createFromTimestamp($f->getMTime()),
            ])->sortByDesc('waktu')->values();
    }

    public function path(string $nama): ?string
    {
        $nama = basename($nama);
        $p = $this->folder().'/'.$nama;

        return preg_match('/^iris-(db|foto)-\d{8}-\d{6}\.(sql\.gz|zip)$/', $nama) && File::exists($p) ? $p : null;
    }

    public function terakhir(string $jenis = 'db'): ?array
    {
        return $this->daftar()->firstWhere('jenis', $jenis);
    }

    private function bersihkan(string $awalan, int $simpan): void
    {
        $this->daftar()->filter(fn ($f) => str_starts_with($f['nama'], $awalan))->slice($simpan)
            ->each(fn ($f) => File::delete($this->folder().'/'.$f['nama']));
    }

    /** Tulis seluruh struktur & isi tabel sebagai perintah SQL. */
    private function tulisDatabase(callable $tulis): void
    {
        $db = DB::connection();
        $driver = $db->getDriverName();
        $pdo = $db->getPdo();
        $q = fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v));

        $tulis("-- Cadangan database IRIS-BBWSNTII\n-- Dibuat: ".now()->format('Y-m-d H:i:s')."\n-- Pulihkan: impor file ini lewat phpMyAdmin (tab Import) ke database kosong.\n\n");
        if ($driver === 'mysql' || $driver === 'mariadb') {
            $tulis("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
            $tabel = collect($db->select('SHOW FULL TABLES'))->filter(fn ($r) => array_values((array) $r)[1] === 'BASE TABLE')
                ->map(fn ($r) => array_values((array) $r)[0])->values();
            $buat = fn ($t) => array_values((array) $db->selectOne("SHOW CREATE TABLE `$t`"))[1];
            $nama = fn ($t) => "`$t`";
        } elseif ($driver === 'sqlite') {
            $tabel = collect($db->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))->pluck('name');
            $buat = fn ($t) => $db->selectOne('SELECT sql FROM sqlite_master WHERE type = ? AND name = ?', ['table', $t])->sql;
            $nama = fn ($t) => '"'.$t.'"';
        } else {
            throw new RuntimeException("Driver database $driver belum didukung untuk cadangan.");
        }

        foreach ($tabel as $t) {
            $tulis("-- ----- $t -----\nDROP TABLE IF EXISTS {$nama($t)};\n".$buat($t).";\n\n");
            $kolom = null;
            $baris = [];
            foreach ($db->table($t)->cursor() as $r) {
                $r = (array) $r;
                $kolom ??= implode(', ', array_map($nama, array_keys($r)));
                $baris[] = '('.implode(', ', array_map($q, array_values($r))).')';
                if (count($baris) >= 200) {
                    $tulis("INSERT INTO {$nama($t)} ($kolom) VALUES\n".implode(",\n", $baris).";\n");
                    $baris = [];
                }
            }
            if ($baris) {
                $tulis("INSERT INTO {$nama($t)} ($kolom) VALUES\n".implode(",\n", $baris).";\n");
            }
            $tulis("\n");
        }
        if ($driver !== 'sqlite') {
            $tulis("SET FOREIGN_KEY_CHECKS=1;\n");
        }
    }
}
