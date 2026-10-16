{{-- Leyenda del estado de despacho en la columna Tiempo --}}
<div {{ $attributes->merge(['class' => 'meal-legend']) }}>
    <span>Tiempo de hoy:</span>
    <x-meal-tag label="Entregado" status="received" />
    <x-meal-tag label="Despachado, sin recibir" status="dispatched" />
    <x-meal-tag label="Pendiente" />
</div>
