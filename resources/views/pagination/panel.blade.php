@if ($paginator->hasPages())
    <nav class="p-pager" role="navigation" aria-label="Paginación">
        @if ($paginator->onFirstPage())
            <span class="p-btn" aria-disabled="true">← Anterior</span>
        @else
            <a class="p-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Anterior</a>
        @endif
        <span class="p-muted">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a class="p-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente →</a>
        @else
            <span class="p-btn" aria-disabled="true">Siguiente →</span>
        @endif
    </nav>
@endif
