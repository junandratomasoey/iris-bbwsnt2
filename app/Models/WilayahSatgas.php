<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WilayahSatgas extends Model
{
    protected $table = 'wilayah_satgas';

    protected $fillable = ['kode', 'nama', 'cakupan'];

    public function kabupaten(): HasMany
    {
        return $this->hasMany(Kabupaten::class);
    }

    public function koordinator(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'koordinator');
    }

    public function getLabelAttribute(): string
    {
        return 'Wilayah '.$this->kode.' — '.$this->cakupan;
    }
}
