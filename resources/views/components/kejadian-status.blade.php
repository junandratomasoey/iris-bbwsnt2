@props(['s'])
<span class="chip bg-savana-deep text-basalt"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ \App\Models\Kejadian::STATUS[$s] ?? '#9AA4A8' }}"></span>{{ $s }}</span>
