@props(['items' => [], 'changes' => null, 'category' ])

<td class="px-3 py-2 text-xs">

    @forelse ($items as $item)

        <span class="tag 
            {{ $changes ? 'tag-' . $category : "tag-".$category }}">
            {{ ucfirst(str_replace('_',' ',$item)) }}
        </span>

    @empty
        <span class="text-gray-400">N/A</span>
    @endforelse

   @if($changes)
    <div class="text-xs text-gray-500 mt-1">
        <p>  Cambios: </p>

        @php
            $oldValues = (array) ($changes['old'] ?? []);
            $items = (array) $items;
        @endphp

        {{-- ❌ Valores que estaban antes y ya no están --}}
        @foreach($oldValues as $old)
            @if(!in_array($old, $items))
                <span class="badge badge-danger">
                    <s> <small>❌ </small>  {{ ucfirst(str_replace('_',' ',$old)) }}</s>
                </span>
            @endif
        @endforeach

        {{-- ✅ Valores nuevos que no estaban antes --}}
        @foreach($items as $item)
            @if(!in_array($item, $oldValues))
                <span class="badge badge-success">
                    <b> <small>✅ </small>  {{ ucfirst(str_replace('_',' ',$item)) }}</b>
                </span>
            @endif
        @endforeach

    </div>
@endif


</td>