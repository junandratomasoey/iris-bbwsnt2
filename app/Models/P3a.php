<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P3a extends Model
{
    use LogsActivity;

    protected $table = 'p3a';

    protected $fillable = ['nama', 'jenis', 'ketua', 'no_hp', 'kabupaten_id', 'daerah_irigasi_id', 'sub_daerah_irigasi_id'];

    public function kabupaten(): BelongsTo
    {
        return $this->belongsTo(Kabupaten::class);
    }

    public function daerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(DaerahIrigasi::class);
    }
}
