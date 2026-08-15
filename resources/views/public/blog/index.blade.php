@extends('layouts.public')

@section('title', __('blog.meta.title').' — '.config('platform.brand_name'))
@section('description', __('blog.index.hero.body'))

@php
    // Presentation-only shaping of the controller's paginated posts: each
    // article's public URL through LocalizedUrl (this route exists, so it
    // resolves), the newest post on page 1 promoted to the featured card,
    // the rest to the grid. On later pages every post goes to the grid and
    // the "Latest Blog" row is not repeated.
    $locale = app()->getLocale();
    $items = collect($posts->items());
    $featured = $posts->onFirstPage() ? $items->first() : null;
    $featuredUrl = $featured === null ? null : \App\Support\LocalizedUrl::to($locale, 'blog.show', ['slug' => $featured->slug]);
    $articles = $items
        ->when($featured !== null, fn ($c) => $c->slice(1))
        ->map(fn ($post) => ['post' => $post, 'url' => \App\Support\LocalizedUrl::to($locale, 'blog.show', ['slug' => $post->slug])])
        ->filter(fn ($article) => $article['url'] !== null)
        ->values()
        ->all();
@endphp

@section('content')
    {{--
        Paper's Blog Index Page section order. The navigation and footer
        frames are the layout's; the Final CTA frame is identical to Home's,
        so the Home component is reused unchanged. $posts (paginated),
        $calBookingUrl and $canonicalPage arrive from
        PublicController::blogIndexData().
    --}}
    <x-blog.index-hero />
    @if ($posts->onFirstPage())
        <x-blog.latest :featured="$featured" :featured-url="$featuredUrl" />
    @endif
    <x-blog.grid :articles="$articles" :paginator="$posts" />
    <x-home.final-cta :cal-booking-url="$calBookingUrl" />
@endsection
