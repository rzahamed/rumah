@extends('layouts.public')

@section('title', $post->translate('title').' — '.config('platform.brand_name'))
@if ($post->translate('excerpt') !== null)
    @section('description', $post->translate('excerpt'))
@endif

@section('content')
    {{--
        Paper's Blog Post Page section order: the post hero, the article body,
        the Final CTA (identical to Home's, reused unchanged), footer. The
        navigation frame is the layout's.

        "Section / Article Body": paddingInline 363 (a 714px reading column),
        paddingTop 129, blocks 28px apart — the markup is produced by
        App\Support\ArticleBody::render() from the post's CMS Markdown
        (raw HTML escaped, unsafe links dropped, headings clamped to h2–h4 so
        the title stays the page's only h1) and styled by .article-body in
        app.css. $post and $calBookingUrl arrive from
        PublicController::blogShowData().
    --}}
    <article>
        <x-blog.post-hero :post="$post" />

        <div class="container-site">
            <div class="article-body mx-auto w-full max-w-[714px] pt-4xl desktop:pt-[129px]" data-animate="fade-up">
                {{ \App\Support\ArticleBody::render($post->translate('body')) }}
            </div>
        </div>
    </article>

    <x-home.final-cta :cal-booking-url="$calBookingUrl" />
@endsection
