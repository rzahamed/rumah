@extends('layouts.public')

@section('title', config('platform.brand_name'))

@section('content')
    <main style="font-family:system-ui,-apple-system,sans-serif;max-width:42rem;margin:4rem auto;padding:0 1.25rem;line-height:1.6;">
        <h1 style="margin:0 0 .5rem;">{{ config('platform.brand_name') }}</h1>
        <p style="color:#555;">Reusable CMS starter — public frontend placeholder. Each client repository replaces this with its own bespoke design (Blade + CSS + GSAP).</p>
        <p style="color:#888;font-size:.9rem;">Locale: <strong>{{ app()->getLocale() }}</strong></p>
    </main>
@endsection
