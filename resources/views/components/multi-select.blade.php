@props([
    'name',
    'options' => [],
    'selected' => [],
    'style' => 'btn-light',
    'label' => null
])

@if($label)
<label>{{ $label }}</label>
@endif

<select class="selectpicker form-control mb-1"
        name="{{ $name }}[]"
        multiple
        data-style="{{ $style }}">

    @foreach($options as $value => $text)
        <option value="{{ $value }}"
            {{ in_array($value, $selected) ? 'selected' : '' }}>
            {{ $text }}
        </option>
    @endforeach

</select>
