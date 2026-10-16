{{--
    Paginación de tablas con los estilos de la plantilla (.pagination / .page-btn en styleTree.css).
    Controlador: $paginator->links('pagination.table')
    Livewire:    $paginator->links('pagination.table', ['livewire' => true])
--}}
@php
    $livewire = $livewire ?? false;
    $pageName = $paginator->getPageName();
@endphp
@if ($paginator->total() > 0)
    <nav class="pagination" role="navigation" aria-label="Paginación">
        <div class="pagination-info" aria-live="polite">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }} registros
        </div>

        @if ($paginator->hasPages())
            <div class="pagination-btns">
                @if ($paginator->onFirstPage())
                    <span class="page-btn page-btn-text" aria-disabled="true">&lsaquo; Anterior</span>
                @elseif ($livewire)
                    <button type="button" class="page-btn page-btn-text" wire:click="previousPage('{{ $pageName }}')" rel="prev">&lsaquo; Anterior</button>
                @else
                    <a class="page-btn page-btn-text" href="{{ $paginator->previousPageUrl() }}" rel="prev">&lsaquo; Anterior</a>
                @endif

                <span class="page-numbers">
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span class="page-btn" aria-hidden="true">{{ $element }}</span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span class="page-btn active" aria-current="page">{{ $page }}</span>
                                @elseif ($livewire)
                                    <button type="button" class="page-btn" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')"
                                        aria-label="Ir a la página {{ $page }}">{{ $page }}</button>
                                @else
                                    <a class="page-btn" href="{{ $url }}" aria-label="Ir a la página {{ $page }}">{{ $page }}</a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                </span>

                @if (! $paginator->hasMorePages())
                    <span class="page-btn page-btn-text" aria-disabled="true">Siguiente &rsaquo;</span>
                @elseif ($livewire)
                    <button type="button" class="page-btn page-btn-text" wire:click="nextPage('{{ $pageName }}')" rel="next">Siguiente &rsaquo;</button>
                @else
                    <a class="page-btn page-btn-text" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente &rsaquo;</a>
                @endif

                <label class="page-jump">
                    <span>Página</span>
                    @if ($livewire)
                        <select class="filter-select" aria-label="Ir a la página"
                            x-on:change="$wire.gotoPage(Number($event.target.value), '{{ $pageName }}')">
                            @for ($i = 1; $i <= $paginator->lastPage(); $i++)
                                <option value="{{ $i }}" @selected($i === $paginator->currentPage())>{{ $i }}</option>
                            @endfor
                        </select>
                    @else
                        <select class="filter-select" aria-label="Ir a la página" onchange="location.href = this.value">
                            @for ($i = 1; $i <= $paginator->lastPage(); $i++)
                                <option value="{{ $paginator->url($i) }}" @selected($i === $paginator->currentPage())>{{ $i }}</option>
                            @endfor
                        </select>
                    @endif
                    <span>de {{ $paginator->lastPage() }}</span>
                </label>
            </div>
        @endif
    </nav>

    @once
        <style>
            .pagination a.page-btn, .pagination button.page-btn { color: inherit; text-decoration: none; }
            .pagination .page-btn[aria-disabled] { opacity: .35; cursor: default; }
            .pagination .page-btn-text { width: auto; padding: 0 10px; }
            .pagination .page-btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
            .pagination-btns { flex-wrap: wrap; align-items: center; }
            .page-numbers { display: inline-flex; gap: 4px; }
            .page-jump { display: inline-flex; align-items: center; gap: 6px; margin-left: 8px; font-size: 13px; color: var(--text-muted); }
            .page-jump select { width: auto; padding: 4px 8px; }
            /* En pantallas pequeñas: solo Anterior / Siguiente y el selector */
            @media (max-width: 640px) {
                .pagination { justify-content: center; }
                .page-numbers { display: none; }
                .page-jump { margin-left: 0; }
            }
        </style>
    @endonce
@endif
