<?php

namespace App\Models;

use App\Casts\Tanggal;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalTanam extends Model
{
    use LogsActivity;

    protected $table = 'jadwal_tanam';

    protected $fillable = [
        'daerah_irigasi_id', 'tahun', 'mt', 'mulai', 'selesai', 'komoditas', 'rencana_tanam_ha',
        'realisasi_tanam_ha', 'puso_ha', 'panen_ha', 'sumber', 'keterangan',
    ];

    protected $casts = ['mulai' => Tanggal::class, 'selesai' => Tanggal::class];

    public function daerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(DaerahIrigasi::class);
    }

    public function getLabelAttribute(): string
    {
        return 'MT '.['', 'I', 'II', 'III'][$this->mt].' '.$this->tahun;
    }
}
