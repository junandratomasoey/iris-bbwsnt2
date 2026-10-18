<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bangunan extends Model
{
    use LogsActivity;

    protected $table = 'bangunan';

    public const JENIS = [
        'bendungan' => 'Bendungan', 'bendung' => 'Bendung', 'embung' => 'Embung', 'captering' => 'Captering',
        'free_intake' => 'Free Intake', 'bagi' => 'Bangunan Bagi', 'sadap' => 'Bangunan Sadap', 'pintu' => 'Pintu Air',
    ];

    protected $fillable = ['daerah_irigasi_id', 'sub_daerah_irigasi_id', 'nama', 'jenis', 'kondisi', 'lat', 'lng', 'keterangan'];

    protected $casts = ['lat' => 'float', 'lng' => 'float'];

    public function daerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(DaerahIrigasi::class);
    }

    public function subDaerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(SubDaerahIrigasi::class);
    }
}
