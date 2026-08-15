<?php

/*
| About page copy, transcribed verbatim from the Paper design file
| (Desktop / About Page). Nothing here is authored by the implementation.
|
| The figures under 'stats' and the capability claims throughout are the
| CLIENT'S copy as drawn in Paper. Transcription is not verification: they must
| be confirmed for publication before this page goes live. Note in particular
| that Paper's third statistic caption reads, in full, "Of our clients are" —
| the sentence is unfinished in the design and is carried here exactly as
| drawn rather than completed by guesswork.
|
| The Mission & Vision eyebrow reads "Our Values" in Paper although the
| section beneath it is Mission and Vision (a separate "Our Values" section
| follows). That is the design's wording and is kept.
*/

return [

    'meta' => [
        'title' => 'About',
    ],

    'hero' => [
        'title' => 'We provide integrated and reliable legal services tailored to individuals and companies in the Kingdom of Saudi Arabia',
    ],

    'stats' => [
        [
            'value' => '$25B+',
            'label' => 'Advised in transactions across corporate finance, restructuring, and capital markets',
        ],
        [
            'value' => '300+',
            'label' => 'Engagements delivered for investment firms, Fortune 500s, and leading institutions',
        ],
        [
            'value' => '99%',
            'label' => 'Of our clients are',
        ],
    ],

    'mission_vision' => [
        'eyebrow' => 'Our Values',
        // :brand is the firm name from config('platform.brand_name'), which is
        // what Paper's literal "Rumah" stands for.
        'title' => 'Driving Force Behind :brand',
        'mission' => [
            'title' => 'Our Mission',
            'body' => 'To deliver comprehensive legal services with a high level of quality and professionalism that meet clients’ needs efficiently—through a specialized legal team and a balance between field and digital presence.',
        ],
        'vision' => [
            'title' => 'Our Vision',
            'body' => 'To be the first trusted choice for legal services in the Kingdom of Saudi Arabia by providing professional legal services that combine expertise, integrity, and modern technology.',
        ],
    ],

    'values' => [
        'title' => 'Our Values',
        'body' => 'We stand behind our work and ensure every legal action reflects our commitment to quality and responsibility.',
        // Paper's "Chip Column / Left" and "Chip Column / Right", top to
        // bottom. Each key names the value glyph component that Paper pairs
        // with the label.
        'left' => [
            'confidentiality' => 'Confidentiality',
            'professionalism' => 'Professionalism',
            'innovation' => 'Innovation',
        ],
        'right' => [
            'integrity' => 'Integrity',
            'client-centered' => 'Client-Centered Service',
            'transparency' => 'Transparency',
        ],
    ],

    'team' => [
        'eyebrow' => 'Our Team',
        'title' => 'Who You’ll Be Working With',
        'body' => 'Our legal team brings together extensive expertise and diverse specializations to ensure the delivery of precise and tailored legal solutions.',
    ],

];
