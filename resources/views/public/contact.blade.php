@extends('layouts.public')

@section('title', __('contact.meta.title').' — '.config('platform.brand_name'))
@section('description', __('contact.hero.body'))

@section('content')
    {{--
        Paper's Contact Page section order: hero; "Section / Intro Video",
        which is the reserved Cal.com inline-embed section (x-contact.cal-embed
        renders the #cal-inline mount and stays hidden until the CMS Custom
        Code injects the calendar); the consultation process and form; FAQ.

        One frame is deliberately absent: "Section / Testimonials" — the same
        placeholder quotes excluded from the Home page, by the same decision.

        The navigation and footer frames are the layout's; there is no Final
        CTA on this page in Paper. $contactForm and $faqs arrive from
        PublicController::contactData().
    --}}
    <x-contact.hero />
    <x-contact.cal-embed />
    <x-contact.consultation :form="$contactForm" />
    <x-contact.faq :faqs="$faqs" />
@endsection
