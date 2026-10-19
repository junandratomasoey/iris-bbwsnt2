<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class IzinJabatan extends Model
{
    use LogsActivity;

    protected $table = 'izin_jabatan';

    protected $fillable = ['role', 'izin', 'boleh'];

    protected $casts = ['boleh' => 'boolean'];
}
