<div>
    @if ($recordId)
        @livewire('prioridad-stock-por-almacen', ['almacenIntermedioId' => (int) $recordId])
    @else
        <div class="text-gray-500 text-sm">
            Guarda el almacén para poder gestionar sus prioridades de stock.
        </div>
    @endif
</div>
