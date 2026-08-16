@extends('layouts.public')

@section('title', config('platform.brand_name'))
@section('description', __('home.hero.subtitle'))

@section('content')
    {{--
        Paper's Home Page section order, in full. The navigation and footer
        frames are the layout's. "Section / Testimonials" carries the client's
        own testimonials (lang/home.php) as a paused-by-default marquee.

        $calBookingUrl arrives from PublicController already validated; each
        section renders its call to action only when it is present.
    --}}
    <x-home.hero :cal-booking-url="$calBookingUrl" />
    <x-home.impact />
    <x-home.values />
    <x-home.offerings :cal-booking-url="$calBookingUrl" />
    <x-home.process :cal-booking-url="$calBookingUrl" />
    <x-home.testimonials />
    <x-home.insights :posts="$latestPosts" />
    <x-home.final-cta :cal-booking-url="$calBookingUrl" />
@endsection
