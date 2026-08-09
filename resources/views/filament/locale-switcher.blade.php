{{-- Server-side locale switcher: authenticated POST per locale, CSRF
     protected, no client-side state. Rendered in the panel topbar via the
     USER_MENU_BEFORE render hook. --}}
@php
    $currentLocale = app()->getLocale();
    $locales = collect(config('platform.supported_locales', []))
        ->reject(fn (string $locale): bool => $locale === $currentLocale);
@endphp

@if ($locales->isNotEmpty())
    <div style="display: flex; align-items: center; gap: 0.5rem;">
        @foreach ($locales as $locale)
            <form method="POST" action="{{ route('admin.locale.update') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $locale }}">
                <x-filament::button type="submit" color="gray" size="sm">
                    {{ __('users.locales.'.$locale) }}
                </x-filament::button>
            </form>
        @endforeach
    </div>
@endif
