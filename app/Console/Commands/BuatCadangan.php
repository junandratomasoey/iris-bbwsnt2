<?php

namespace App\Console\Commands;

use App\Services\CadanganService;
use Illuminate\Console\Command;

class BuatCadangan extends Command
{
    protected $signature = 'iris:cadangan {--foto : Cadangkan juga foto unggahan (zip)} {--tanpa-db : Lewati cadangan database}';

    protected $description = 'Buat cadangan database (.sql.gz) dan/atau foto (.zip) di storage/app/cadangan';

    public function handle(CadanganService $svc): int
    {
        try {
            if (! $this->option('tanpa-db')) {
                $this->info('Database: '.$svc->buatDatabase());
            }
            if ($this->option('foto')) {
                $this->info('Foto: '.$svc->buatFoto());
            }
        } catch (\Throwable $e) {
            report($e);
            $this->error('Cadangan gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
