@if ($paginator->hasPages())
<nav class="flex items-center justify-between gap-2 px-3 py-3 text-sm" aria-label="Halaman">
    <p class="text-basalt-soft">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ angka($paginator->total()) }}</p>
    <div class="flex gap-1">
        @if ($paginator->onFirstPage())
            <span class="btn-ghost pointer-events-none opacity-40">Sebelumnya</span>
        @else
            <a class="btn-ghost" href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>
        @endif
        @if ($paginator->hasMorePages())
            <a class="btn-ghost" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>
        @else
            <span class="btn-ghost pointer-events-none opacity-40">Berikutnya</span>
        @endif
    </div>
</nav>
@endif
