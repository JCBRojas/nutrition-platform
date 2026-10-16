@if ($errors->any())
    <div class="alert alert-error" role="alert">
        @if ($errors->count() === 1)
            {{ $errors->first() }}
        @else
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif

@if (session('status'))
    <div class="alert alert-status" role="status">{{ session('status') }}</div>
@endif
