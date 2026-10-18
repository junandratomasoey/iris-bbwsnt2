<?php

namespace App\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** Kolom DATE: dibaca sebagai Carbon (awal hari), selalu disimpan 'Y-m-d' agar perbandingan tanggal konsisten di MySQL/MariaDB/SQLite. */
class Tanggal implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value)->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null || $value === '' ? null : Carbon::parse($value)->toDateString();
    }
}
