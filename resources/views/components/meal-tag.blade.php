{{--
    Tiempo de comida con su estado de despacho del día:
    - received   → turquesa sólido + check: entregado (recibido por el nutricionista)
    - dispatched → borde turquesa punteado + camión: despachado, falta confirmar recepción
    - null       → etiqueta normal
--}}
@props(['label', 'status' => null, 'class' => 'tag-tiempo'])

@if ($status === 'received')
    <span class="tag meal-received" title="{{ $label }}: entregado y recibido hoy">
        <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
        {{ $label }}<span class="sr-only"> (entregado)</span>
    </span>
@elseif ($status === 'dispatched')
    <span class="tag meal-dispatched" title="{{ $label }}: despachado hoy, falta confirmar recepción">
        <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 3h15v13H1zM16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        {{ $label }}<span class="sr-only"> (despachado, sin confirmar recepción)</span>
    </span>
@else
    <span class="tag {{ $class }}">{{ $label }}</span>
@endif
