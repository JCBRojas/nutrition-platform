@if ($errors->any())
    <div {{ $attributes }}>
        <div class="font-medium text-red-600">

            @foreach ($errors->all() as $error)
                {{ $error }}
            @endforeach
        </div>
    </div>
@endif
