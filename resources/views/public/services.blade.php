@extends('layouts.public')

@section('title', __('services.meta.title').' — '.config('platform.brand_name'))
@section('description', __('services.packages.body'))

@section('content')
    {{--
        Paper's Services + Packages Page section order. The navigation and
        footer frames are the layout's. The Final CTA frame is identical to the
        Home page's, so the Home component is reused unchanged.

        $calBookingUrl arrives from PublicController::servicesData(); it is the
        page's only model-backed value and is used by the Final CTA alone —
        the pricing cards' calls to action point at /contact for now.
    --}}
    <x-services.packages />
    <x-services.addons />
    <x-services.legal-services />
    <x-home.final-cta :cal-booking-url="$calBookingUrl" />
@endsection
