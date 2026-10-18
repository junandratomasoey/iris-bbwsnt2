<?php

namespace App\Support;

final class GeojsonVersi
{
    /** Waktu ubah terakhir file GeoJSON tampilan, dipakai sebagai penanda cache. */
    public static function get(): string
    {
        $t = 0;
        foreach (glob(public_path('geojson/*.geojson')) ?: [] as $f) {
            $t = max($t, (int) @filemtime($f));
        }

        return (string) $t;
    }
}
