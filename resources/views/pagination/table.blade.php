{{-- Paginación de tablas con los estilos de la plantilla (.pagination / .page-btn en styleTree.css) --}}
@if ($paginator->total() > 0)
    <nav class="pagination" role="navigation" aria-label="Paginación">
        <div class="pagination-info">
            Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
        </div>

        @if ($paginator->hasPages())
            <div class="pagination-btns">
                @if ($paginator->onFirstPage())
                    <span class="page-btn" aria-disabled="true" aria-label="Página anterior">&lsaquo;</span>
                @else
                    <a class="page-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Página anterior">&lsaquo;</a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="page-btn" aria-disabled="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="page-btn active" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="page-btn" href="{{ $url }}" aria-label="Ir a la página {{ $page }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a class="page-btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Página siguiente">&rsaquo;</a>
                @else
                    <span class="page-btn" aria-disabled="true" aria-label="Página siguiente">&rsaquo;</span>
                @endif
            </div>
        @endif
    </nav>
@endif
