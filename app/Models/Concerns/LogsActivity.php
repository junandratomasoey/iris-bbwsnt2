<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn ($m) => $m->catatAktivitas('dibuat', $m->getAttributes()));
        static::updated(function ($m) {
            $ubah = collect($m->getChanges())->except(['updated_at', 'password', 'remember_token']);
            if ($ubah->isNotEmpty()) {
                $m->catatAktivitas('diubah', ['baru' => $ubah, 'lama' => collect($m->getOriginal())->only($ubah->keys())]);
            }
        });
        static::deleted(fn ($m) => $m->catatAktivitas('dihapus', $m->getOriginal()));
    }

    protected function catatAktivitas(string $aksi, $data): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return; // seeder & artisan tidak dicatat
        }
        $kecuali = array_merge(['password', 'remember_token'], $this->tidakDicatat ?? []);
        $data = collect($data)->except($kecuali)->all();
        foreach (['baru', 'lama'] as $k) {
            if (isset($data[$k])) {
                $data[$k] = collect($data[$k])->except($kecuali)->all();
            }
        }
        if (($data['baru'] ?? null) === []) {
            $data['baru'] = ['geometri' => '(batas peta diubah)'];
        }
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $aksi,
            'subject_type' => $this->getMorphClass(),
            'subject_id' => $this->getKey(),
            'changes' => $data,
            'ip_address' => request()?->ip(),
        ]);
    }
}
