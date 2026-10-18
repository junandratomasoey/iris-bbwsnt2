<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Foto extends Model
{
    protected $table = 'foto';

    protected $fillable = ['path', 'keterangan', 'lat', 'lng'];

    protected static function booted(): void
    {
        static::deleting(fn (Foto $f) => Storage::disk('uploads')->delete($f->path));
    }

    public function fotoable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): string
    {
        return asset('uploads/'.$this->path);
    }
}
