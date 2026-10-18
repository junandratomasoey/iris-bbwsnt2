<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kabupaten extends Model
{
    protected $table = 'kabupaten';

    protected $fillable = ['nama', 'wilayah_satgas_id'];

    public function wilayahSatgas(): BelongsTo
    {
        return $this->belongsTo(WilayahSatgas::class);
    }

    public function daerahIrigasi(): HasMany
    {
        return $this->hasMany(DaerahIrigasi::class);
    }
}
