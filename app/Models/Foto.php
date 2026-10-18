<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Foto extends Model
{
    protected $table = 'foto';

    /** Label foto kejadian (kolom keterangan). */
    public const LABEL = ['sebelum' => 'Sebelum penanganan', 'sesudah' => 'Sesudah penanganan', 'lainnya' => 'Dokumentasi lain'];

    protected $fillable = ['path', 'keterangan', 'lat', 'lng'];

    protected static function booted(): void
    {
        static::deleting(fn (Foto $f) => Storage::disk('uploads')->delete([$f->path, self::pathKecil($f->path)]));
    }

    public function fotoable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function pathKecil(string $path): string
    {
        return preg_replace('/\.jpg$/i', '-kecil.jpg', $path);
    }

    public function getUrlAttribute(): string
    {
        return asset('uploads/'.$this->path);
    }

    /** Thumbnail (±480 px) untuk popup peta & galeri; jatuh ke foto asli bila belum ada. */
    public function getUrlKecilAttribute(): string
    {
        $kecil = self::pathKecil($this->path);

        return Storage::disk('uploads')->exists($kecil) ? asset('uploads/'.$kecil) : $this->url;
    }

    public function getLabelAttribute(): ?string
    {
        return self::LABEL[$this->keterangan] ?? $this->keterangan;
    }
}
