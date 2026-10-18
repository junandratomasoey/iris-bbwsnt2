<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetaDi extends Model
{
    use LogsActivity;

    protected $table = 'peta_di';

    public const LAPISAN = [
        'fungsional' => 'Areal fungsional',
        'baku' => 'Luas baku',
        'potensial' => 'Areal potensial',
    ];

    /** Kolom besar yang tidak dicatat di log aktivitas. */
    public array $tidakDicatat = ['geometri'];

    protected $fillable = ['lapisan', 'daerah_irigasi_id', 'nama', 'luas_ha', 'lat', 'lng', 'geometri', 'sumber', 'keterangan'];

    protected $casts = ['geometri' => 'array', 'luas_ha' => 'float', 'lat' => 'float', 'lng' => 'float'];

    public function daerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(DaerahIrigasi::class);
    }

    public function jumlahBagian(): int
    {
        return ($this->geometri['type'] ?? null) === 'MultiPolygon' ? count($this->geometri['coordinates']) : 1;
    }
}
