<?php

/*
| Home page copy, transcribed verbatim from the Paper design file
| (Desktop / Home Page). Nothing here is authored by the implementation.
|
| The figures under 'impact.stats' and the capability claims throughout are the
| CLIENT'S copy as drawn in Paper. Transcription is not verification: they must
| be confirmed for publication before this page goes live.
*/

return [

    'hero' => [
        'title' => 'Legal Expertise. Trusted Guidance. Proven Results.',
        'subtitle' => 'A specialized team of Saudi lawyers delivering comprehensive legal solutions for individuals and companies across the Kingdom.',
        'pills' => [
            'Specialized Legal Team',
            'Comprehensive Legal Services',
            'Integrity & Excellence',
        ],
    ],

    'impact' => [
        'eyebrow' => 'Our Impact',
        'title' => 'Advisory Built for Complexity.',
        'body' => 'We provide integrated and reliable legal services tailored to the needs of individuals and companies in the Kingdom of Saudi Arabia, while adhering to the highest standards of quality and professionalism.',
        'stats' => [
            [
                'value' => '150+',
                'label' => 'Legal advisory completed across corporate setup, real estate, and individual cases.',
            ],
            [
                'value' => '760+',
                'label' => 'Engagements delivered for investment firms, Fortune 500s, and leading institutions',
            ],
            [
                'value' => '99%',
                'label' => 'Of our clients are fully satisfied with the clarity, professionalism, and outcomes of our legal services.',
            ],
        ],
    ],

    'values' => [
        'eyebrow' => 'Our Values',
        'items' => [
            [
                'title' => 'Integrity',
                'body' => 'Commitment to the highest standards of quality and discipline in providing legal services.',
            ],
            [
                'title' => 'Transparency',
                'body' => 'Full clarity when dealing with clients regarding procedures, costs, and expectations.',
            ],
            [
                'title' => 'Confidentiality',
                'body' => 'Protecting clients’ privacy and legal information with the highest levels of security and trust.',
            ],
            [
                'title' => 'Innovation',
                'body' => 'Leveraging modern technologies to improve service delivery and enhance client experience.',
            ],
        ],
    ],

    'offerings' => [
        'eyebrow' => 'Our Offerings',
        'title' => 'Tailored Legal Solution',
        'body' => 'We craft legal solutions designed around your unique situation — from contracts and disputes to corporate and personal matters. Our team analyzes every detail to protect your rights.',
        'areas_label' => 'Practice areas',
        'areas' => [
            'Corporate Services',
            'Litigation & Dispute Resolution',
            'IP & Innovation Protection',
            'Regulatory Compliance',
            'Personal, Family & Individual Legal Services',
        ],
        'detail' => [
            'label' => 'Sub Offerings',
            'intro' => 'Specialized legal support across all stages of litigation, from filing cases and preparing defenses to representing clients before all levels of Saudi courts.',
            'consultation' => 'Book a Consultation. From the first meeting to the final resolution, our legal experts ensure you understand your options and have a clear strategy moving forward.',
            'items' => [
                'Appeals before appellate courts',
                'Challenges before the Supreme Court',
                'Preparatory procedures for court cases',
            ],
            // Accessible names for the sub-offerings strip and its pager. Not
            // drawn in Paper (the pager buttons carry only arrow glyphs).
            'strip_label' => 'Sub-offerings',
            'previous' => 'Previous sub-offerings',
            'next' => 'Next sub-offerings',
        ],
    ],

    'process' => [
        'eyebrow' => 'How it Works',
        'title' => 'How Our Legal Process Works',
        'body' => 'Our streamlined process ensures you get the right legal guidance from the first interaction to complete resolution.',
        'steps' => [
            [
                'title' => 'Simple Booking',
                'body' => 'Choose a convenient time and connect with our legal team through a quick and seamless booking process. Whether online or over the phone, we make it easy to get started without delays.',
            ],
            [
                'title' => 'Tailored Strategy',
                'body' => 'Our lawyers analyze your situation in depth and craft a personalized legal strategy that aligns with your needs, protects your rights, and maximizes your chances of success.',
            ],
            [
                'title' => 'Continuous Support',
                'body' => 'We stand by you throughout the entire legal journey—providing updates, clarifying every step, and ensuring you always know what’s happening next.',
            ],
        ],
    ],

    'insights' => [
        'eyebrow' => 'Trending Now',
        'title' => 'Latest Legal Insights',
        'body' => 'Stay updated with our latest articles covering legal trends, case updates, and expert guidance relevant to individuals, companies, and business owners in Saudi Arabia.',
        // Paper "Link / Read Full" on the feature card.
        'feature_cta' => 'Read the full insights',
        // Paper "Card / Newsletter" gives the shared signup form its own
        // heading and placeholder on this page. The consent sentence stays
        // the shared, reviewed one in lang/newsletter.php.
        'newsletter' => [
            'heading' => 'Be the first to know our insights!',
            'placeholder' => 'name@company.com',
        ],
    ],

    'final_cta' => [
        'title' => 'Your Next Step Toward the Right Legal Outcome Starts Here',
        'subtitle' => 'Speak with our legal experts and get a clear roadmap for your situation.',
    ],

];
