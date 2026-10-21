{{-- Enlace del menú lateral; recibe un elemento ya resuelto por App\Support\Navigation --}}
@props(['item'])

<a @class(['nav-item', 'active' => $item['active'], 'is-locked' => $item['locked']]) href="{{ $item['href'] }}" id="{{ $item['id'] }}"
  title="{{ $item['label'] }}{{ $item['locked'] ? ' (plan Premium)' : '' }}"
  @if ($item['active']) aria-current="page" @endif>
  <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">{!! config('navigation.icons.'.$item['icon']) !!}</svg>
  <span class="nav-label">{{ $item['label'] }}</span>
  @if ($item['locked'])
    <span class="nav-lock"><svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg><span class="nav-label">Premium</span></span>
  @endif
</a>
