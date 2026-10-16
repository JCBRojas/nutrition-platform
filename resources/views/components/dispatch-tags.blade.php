{{-- Lista de una versión (tiempos, consistencias, especificaciones) con lo agregado y lo quitado desde la última confirmación --}}
@props(['items' => [], 'labels' => [], 'change' => null, 'class' => 'tag-tiempo', 'delivery' => []])

@php
    $added = $change['added'] ?? [];
    $removed = $change['removed'] ?? [];
    $label = fn ($key) => $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
    // Estado de despacho del día por tiempo de comida (solo columna Tiempo); claves normalizadas
    $status = fn ($key) => $delivery[str_replace('_', '', mb_strtolower((string) $key))] ?? null;
@endphp

<td @class(['dm-changed-cell' => $change])>
    @forelse ((array) $items as $item)
        @if (in_array($item, $added, true))
            <span class="tag dm-added" title="Agregado">+ {{ $label($item) }}<span class="sr-only"> (agregado)</span></span>
        @else
            <x-meal-tag :label="$label($item)" :status="$status($item)" :class="$class" />
        @endif
    @empty
        <span class="text-gray-400">—</span>
    @endforelse

    @foreach ($removed as $item)
        <span class="tag dm-removed" title="Quitado"><s>{{ $label($item) }}</s><span class="sr-only"> (quitado)</span></span>
    @endforeach
</td>
