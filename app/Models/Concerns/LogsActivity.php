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
        $data = collect($data)->except(['password', 'remember_token'])->all();
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
