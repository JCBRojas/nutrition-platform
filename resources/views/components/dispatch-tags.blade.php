{{-- Lista de una versión (tiempos, consistencias, especificaciones) con lo agregado y lo quitado desde la última confirmación --}}
@props(['items' => [], 'labels' => [], 'change' => null, 'class' => 'tag-tiempo'])

@php
    $added = $change['added'] ?? [];
    $removed = $change['removed'] ?? [];
    $label = fn ($key) => $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
@endphp

<td @class(['dm-changed-cell' => $change])>
    @forelse ((array) $items as $item)
        @if (in_array($item, $added, true))
            <span class="tag dm-added" title="Agregado">+ {{ $label($item) }}<span class="sr-only"> (agregado)</span></span>
        @else
            <span class="tag {{ $class }}">{{ $label($item) }}</span>
        @endif
    @empty
        <span class="text-gray-400">—</span>
    @endforelse

    @foreach ($removed as $item)
        <span class="tag dm-removed" title="Quitado"><s>{{ $label($item) }}</s><span class="sr-only"> (quitado)</span></span>
    @endforeach
</td>
