<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), config('platform.rtl_locales', []), true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('invitations.accept.title') }} — {{ config('platform.brand_name') }}</title>
    <style>
        :root { color-scheme: light; }
        body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; background: #f4f4f5; color: #18181b; margin: 0; display: grid; min-height: 100vh; place-items: center; }
        .card { background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; padding: 2rem; width: 100%; max-width: 24rem; box-shadow: 0 1px 3px rgb(0 0 0 / .06); }
        h1 { font-size: 1.125rem; margin: 0 0 .25rem; }
        p.intro { color: #52525b; font-size: .875rem; margin: 0 0 1.5rem; }
        label { display: block; font-size: .875rem; font-weight: 600; margin-bottom: .375rem; }
        input { width: 100%; box-sizing: border-box; border: 1px solid #d4d4d8; border-radius: 8px; padding: .5rem .75rem; font-size: .875rem; margin-bottom: 1rem; }
        input:focus { outline: 2px solid #6366f1; outline-offset: 1px; border-color: #6366f1; }
        button { width: 100%; background: #4f46e5; color: #fff; border: 0; border-radius: 8px; padding: .625rem; font-size: .875rem; font-weight: 600; cursor: pointer; }
        button:hover { background: #4338ca; }
        ul.errors { color: #b91c1c; font-size: .8125rem; padding-inline-start: 1.25rem; margin: 0 0 1rem; }
    </style>
</head>
<body>
    <main class="card">
        <h1>{{ __('invitations.accept.title') }}</h1>
        <p class="intro">{{ __('invitations.accept.intro', ['email' => $user->email]) }}</p>

        @if ($errors->any())
            <ul class="errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('admin.invitation.store', ['token' => $token]) }}">
            @csrf

            <label for="password">{{ __('invitations.accept.password') }}</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" autofocus>

            <label for="password_confirmation">{{ __('invitations.accept.password_confirmation') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">

            <button type="submit">{{ __('invitations.accept.submit') }}</button>
        </form>
    </main>
</body>
</html>
