<?php

use Illuminate\Support\Facades\Schedule;

// Cadangan otomatis (jalan bila cron server memanggil "php artisan schedule:run" tiap menit; lihat menu Cadangan data)
Schedule::command('iris:cadangan')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('iris:cadangan --foto --tanpa-db')->weeklyOn(0, '02:00')->withoutOverlapping();
