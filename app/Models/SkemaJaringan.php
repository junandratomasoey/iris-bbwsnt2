<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Gambar/PDF skema jaringan irigasi per D.I. (disimpan apa adanya agar tetap tajam saat diperbesar). */
class SkemaJaringan extends Model
{
    use LogsActivity;

    protected $table = 'skema_jaringan';

    public const MAKS_FILE = 6;

    public const MAKS_KB = 20480;

    protected $fillable = ['daerah_irigasi_id', 'judul', 'path', 'mime', 'ukuran', 'user_id'];

    protected static function booted(): void
    {
        static::deleting(fn (SkemaJaringan $s) => Storage::disk('uploads')->delete($s->path));
    }

    public function daerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(DaerahIrigasi::class);
    }

    public function getUrlAttribute(): string
    {
        return asset('uploads/'.$this->path);
    }

    public function getPdfAttribute(): bool
    {
        return $this->mime === 'application/pdf';
    }

    public function getUkuranTeksAttribute(): string
    {
        return $this->ukuran >= 1048576 ? number_format($this->ukuran / 1048576, 1, ',', '.').' MB' : max(1, (int) round($this->ukuran / 1024)).' KB';
    }
}
