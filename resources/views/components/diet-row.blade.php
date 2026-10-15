<tr>
    <td>
        <span class="room-badge">{{ $diet->habitation }}</span>
    </td>

    <td>{{ $diet->name_patient }}</td>

    {{-- Tiempo de comida --}}
    <td>
        @php
        $items = $diet->currentVersion->timeFood ?? [];
        @endphp

        @forelse ($items as $item)
        <span class="tag tag-tiempo">
            {{ ucfirst(str_replace('_', ' ', $item)) }}
        </span>
        @empty
        <span>—</span>
        @endforelse
    </td>

    {{-- Consistencia --}}
    <td>
        @php
        $items = $diet->currentVersion->consistency ?? [];
        @endphp

        @forelse ($items as $item)
        <span class="tag tag-consist">
            {{ ucfirst(str_replace('_', ' ', $item)) }}
        </span>
        @empty
        <span>—</span>
        @endforelse
    </td>

    {{-- Especificaciones --}}
    <td>
        @php
        $items = $diet->currentVersion->specifications ?? [];
        @endphp

        @forelse ($items as $item)
        <span class="tag tag-espec">
            {{ ucfirst(str_replace('_', ' ', $item)) }}
        </span>
        @empty
        <span>—</span>
        @endforelse
    </td>

    <td>{{ $diet->currentVersion->observations ?? '—' }}</td>
    <td>{{ $diet->currentVersion->isolation ?? 'No' }}</td>
    <td>{{ $diet->currentVersion->changes ?? 'N/A' }}</td>

    <td class="text-right">
        <button class="btn btn-primary btn-sm" onclick="openEditModal({{$diet->id }}, {{$diet->currentVersion}} )">
            Editar
        </button>
    </td>
</tr>
