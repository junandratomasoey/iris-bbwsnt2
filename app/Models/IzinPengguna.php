<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pengecualian izin untuk satu orang: boleh=true memberi, boleh=false mencabut (menimpa izin jabatannya). */
class IzinPengguna extends Model
{
    use LogsActivity;

    protected $table = 'izin_pengguna';

    protected $fillable = ['user_id', 'izin', 'boleh', 'alasan'];

    protected $casts = ['boleh' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
