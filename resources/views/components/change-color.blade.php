@props(['type'])

@php
$changeColors = [
    'cancelada' => 'bg-red-100 text-red-800',
    'finalizada' => 'bg-blue-100 text-blue-800',
    'con_cambios' => 'bg-yellow-100 text-yellow-800',
    'aislado' => 'bg-purple-100 text-purple-800',
    'ya_no_aislado' => 'bg-green-100 text-green-800',
];

$color = $changeColors[$type ?? ''] ?? 'bg-gray-100 text-gray-800';
@endphp

<td {{ $attributes->merge(['class' => "px-3 py-2 text-xs $color"]) }}>
    {{ $slot }}
</td>