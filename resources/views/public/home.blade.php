@extends('layouts.public')

@section('title', config('platform.brand_name'))
@section('description', __('home.hero.subtitle'))

@section('content')
    {{--
        Paper's Home Page section order. Two of its ten frames are absent:

        • "Section / Testimonials" — the design's quotes are placeholder copy
          and there is no CMS module or supplied source for real ones.
        • The navigation and footer frames, which the layout owns.

        $calBookingUrl arrives from PublicController already validated; each
        section renders its call to action only when it is present.
    --}}
    <x-home.hero :cal-booking-url="$calBookingUrl" />
    <x-home.impact />
    <x-home.values />
    <x-home.offerings :cal-booking-url="$calBookingUrl" />
    <x-home.process :cal-booking-url="$calBookingUrl" />
    <x-home.insights :posts="$latestPosts" />
    <x-home.final-cta :cal-booking-url="$calBookingUrl" />
@endsection
