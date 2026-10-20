<?php

namespace App\Models;

use App\Casts\Tanggal;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Kejadian extends Model
{
    use LogsActivity;

    protected $table = 'kejadian';

    public const JENIS = ['Gagal Tanam', 'Gagal Panen', 'Kerusakan Jaringan', 'Lainnya'];

    public const STATUS = [
        'Perlu Penanganan' => '#B3261E',
        'Dalam Penanganan' => '#D9622B',
        'Selesai Ditangani' => '#4E8A2E',
        'Tidak Perlu Penanganan' => '#9AA4A8',
    ];

    protected $fillable = [
        'tanggal_lapor', 'daerah_irigasi_id', 'sub_daerah_irigasi_id', 'kabupaten_id', 'user_id', 'kecamatan', 'desa',
        'lat', 'lng', 'jenis_kegagalan', 'penyebab', 'status_kegagalan', 'luas_terdampak_ha', 'luas_tertangani_ha',
        'sumber_air', 'solusi', 'p3a', 'pelapor_nama', 'pelapor_hp', 'sumber_data', 'catatan', 'status_penanganan',
    ];

    protected $casts = ['tanggal_lapor' => Tanggal::class, 'lat' => 'float', 'lng' => 'float'];

    public function daerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(DaerahIrigasi::class);
    }

    public function subDaerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(SubDaerahIrigasi::class);
    }

    public function kabupaten(): BelongsTo
    {
        return $this->belongsTo(Kabupaten::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function foto(): MorphMany
    {
        return $this->morphMany(Foto::class, 'fotoable');
    }

    /**
     * Kejadian dalam jangkauan user: semua untuk Super Admin/Pimpinan/Admin OP;
     * selain itu kejadian di D.I. wilayahnya, atau kejadian tanpa D.I. di kabupaten D.I. tersebut.
     */
    public function scopeTerjangkau(\Illuminate\Database\Eloquent\Builder $q, User $u): \Illuminate\Database\Eloquent\Builder
    {
        $diIds = $u->diIdsTerjangkau();
        if ($diIds === null) {
            return $q;
        }
        $kab = DaerahIrigasi::whereIn('id', $diIds)->whereNotNull('kabupaten_id')->distinct()->pluck('kabupaten_id');

        return $q->where(fn ($w) => $w->whereIn('daerah_irigasi_id', $diIds)
            ->orWhere(fn ($x) => $x->whereNull('daerah_irigasi_id')->whereIn('kabupaten_id', $kab))
            ->orWhere('user_id', $u->id));
    }
}
