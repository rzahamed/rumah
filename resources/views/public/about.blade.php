@extends('layouts.public')

@section('title', __('about.meta.title').' — '.config('platform.brand_name'))
@section('description', __('about.hero.title'))

@section('content')
    {{--
        Paper's About Page section order. The navigation and footer frames are
        the layout's. The Final CTA frame is identical to the Home page's, so
        the Home component is reused unchanged rather than duplicated.

        $calBookingUrl and $teamMembers arrive from PublicController::aboutData().
    --}}
    <x-about.hero />
    <x-about.stats />
    <x-about.mission-vision />
    <x-about.values />
    <x-about.team :members="$teamMembers" />
    <x-home.final-cta :cal-booking-url="$calBookingUrl" />
@endsection
