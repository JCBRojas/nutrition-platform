<div>
    <!-- Botón para abrir modal -->
    <button wire:click="abrirModal" class="btn-info px-4 py-2 rounded">
        Nuevo Paciente
    </button>

    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white rounded-lg shadow-lg w-96 p-6">
                <h2 class="text-xl font-bold mb-4">Formulario de Paciente</h2>

                <form wire:submit.prevent="guardar" class="space-y-3">
                    <input type="text" wire:model="habitacion" placeholder="Habitación" class="w-full border rounded px-2 py-1">
                    <input type="text" wire:model="nombre" placeholder="Nombre Paciente" class="w-full border rounded px-2 py-1">
                    <input type="text" wire:model="tiempo_comida" placeholder="Tiempo de Comida" class="w-full border rounded px-2 py-1">
                    <input type="text" wire:model="consistencia" placeholder="Consistencia" class="w-full border rounded px-2 py-1">
                    <textarea wire:model="especificaciones" placeholder="Especificaciones" class="w-full border rounded px-2 py-1"></textarea>
                    <textarea wire:model="observaciones" placeholder="Observaciones" class="w-full border rounded px-2 py-1"></textarea>
                    <input type="text" wire:model="aislamiento" placeholder="Aislamiento" class="w-full border rounded px-2 py-1">
                    <textarea wire:model="cambios" placeholder="Cambios" class="w-full border rounded px-2 py-1"></textarea>

                    <div class="flex justify-end space-x-2 mt-4">
                        <button type="button" wire:click="cerrarModal" class="bg-gray-400 text-white px-3 py-1 rounded">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-green-600 px-3 py-1 rounded">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
