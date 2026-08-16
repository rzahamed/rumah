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
        // The five practice-area tabs and their four sub-offerings each — the
        // client's copy from the approved prototype, in its order. Each tab
        // opens the same panel structure (chip, intro, consultation row, four
        // photo cards); the intro and consultation paragraphs below are shared
        // by all five tabs, exactly as the prototype has them.
        'areas' => [
            [
                'label' => 'Corporate Services',
                'items' => ['Commercial Law', 'Company Formation & Incorporation', 'Contract Drafting & Review', 'Corporate Governance'],
            ],
            [
                'label' => 'Individuals & Personal Status Law',
                'items' => ['Personal & Family Law', 'Inheritance Law', 'Labor Law', 'Criminal Law'],
            ],
            [
                'label' => 'Regulatory Compliance',
                'items' => ['Compliance', 'Regulatory Review', 'Anti-Money Laundering (AML)', 'Personal Data Protection Compliance'],
            ],
            [
                'label' => 'Litigation & Dispute Resolution',
                'items' => ['Court Representation', 'Dispute Resolution', 'Commercial Disputes', 'Arbitration'],
            ],
            [
                'label' => 'IP & Innovation Law',
                'items' => ['Trademark', 'Copyrights', 'Patents & Filings', 'IP Rights Disputes'],
            ],
        ],
        'detail' => [
            'label' => 'Sub Offerings',
            'intro' => 'Comprehensive legal support across all stages of litigation, starting from case filing and the preparation of legal pleadings and defenses, through to representing clients before all levels of courts in the Kingdom of Saudi Arabia.',
            'consultation' => 'Book a Consultation Session. From the initial meeting through to reaching a final resolution, our legal experts are committed to enabling you to fully understand your legal options and to developing a clear, effective strategy to move forward with confidence.',
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

    'testimonials' => [
        'eyebrow' => 'Client Testimonials',
        'title' => 'Trusted by Individuals & Businesses Across Saudi Arabia',
        'body' => 'Real stories from clients who experienced clarity, confidence, and successful outcomes through our legal expertise.',
        // Not drawn in the design: the pause/play control that WCAG requires
        // for content that moves on its own for more than five seconds.
        'pause' => 'Pause testimonials',
        'play' => 'Play testimonials',
        'list_label' => 'Client testimonials',
        // Supplied by the client from the approved prototype, in its order.
        // The English prototype repeats the Reem Al-Sayegh card a second time
        // dated Oct, 2024 and omits Mohammed Al-Zahra's date; the Arabic
        // prototype has each client once and dates that card May, 2025 —
        // the two lists are aligned on those nine records so EN and AR match
        // card for card. Rendered as two marquee rows: items 1–5 and 6–9.
        'items' => [
            ['quote' => '“The team simplified a very complex legal issue for me, and I finally felt protected and provided with reliable legal advice.”', 'name' => 'Reem Al-Sayegh', 'date' => 'Jan, 2025'],
            ['quote' => '“High professionalism, speed in completion, and great clarity in guidance. They handled my case with confidence from day one.”', 'name' => 'Fahad Al-Rashed', 'date' => 'Dec, 2024'],
            ['quote' => '“A truly reliable firm. Their tailor-made strategy helped us resolve a long-standing commercial dispute with great efficiency.”', 'name' => 'M. Omar Al-Qurashi', 'date' => 'Mar, 2025'],
            ['quote' => '“Exceptional service. Their attention to detail and meticulous follow-up made a real difference in my appeal case.”', 'name' => 'Khaled Al-Mutairi', 'date' => 'Jan, 2025'],
            ['quote' => '“Professional handling that exceeded expectations. Their role was not limited to providing the solution, but they explained it clearly and made it easy to understand and implement.”', 'name' => 'Mohammed Al-Zahra', 'date' => 'May, 2025'],
            ['quote' => '“A team characterized by accuracy and transparency. I was fully informed of every step, and I felt genuinely confident in the legal decisions made.”', 'name' => 'Sarah Al-Otaibi', 'date' => 'Dec, 2024'],
            ['quote' => '“Clarity, commitment, and professionalism at every stage. I felt my case was being handled with genuine care, not with routine procedures.”', 'name' => 'Ahmed Al-Harbi', 'date' => 'Mar, 2025'],
            ['quote' => '“A legal team that understands the details before they turn into problems. Their advice was accurate and helped us make confident decisions.”', 'name' => 'Lina Al-Salem', 'date' => 'Aug, 2025'],
            ['quote' => '“One of the best legal experiences I’ve had. The speed of response, depth of understanding, and concern for the client’s best interests were evident from the start.”', 'name' => 'عبدالعزيز القحطاني', 'date' => 'Oct, 2024'],
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
