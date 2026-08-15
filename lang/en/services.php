<?php

/*
| Services + Packages page copy, transcribed verbatim from the Paper design
| file (Desktop / Services + Packages Page). Nothing here is authored by the
| implementation.
|
| Prices, discounts and capability claims are the CLIENT'S copy as drawn in
| Paper — transcribed, not verified — and Paper states no currency beside the
| figures. Several strings are carried exactly as Paper writes them even where
| they read as drafting slips, because completing or correcting them would be
| authoring: "Preparing two (2) two official letter …", "Exclusive upto 30%
| Discount", "On litigation fees when combined our packages.", and the service
| cards Compliance / Commercial Law / Personal Status & Family Law, whose intro
| lines repeat other cards' intros in Paper (Compliance's closing line also
| repeats its own bullet list). All must be confirmed before publication.
*/

return [

    'meta' => [
        'title' => 'Services',
    ],

    'packages' => [
        'eyebrow' => 'Our Popular Packages',
        'title' => 'Legal Support, Packaged for Clarity',
        'body' => 'Our legal packages are thoughtfully structured to provide consistent, high-quality legal support with transparent pricing—giving you confidence, clarity, and compliance at every stage.',
        'items' => [
            [
                'name' => 'Protection & Growth Package',
                'price_label' => 'Starting from',
                'price' => '3,000',
                'features' => [
                    'Providing Legal Consultations (up to 4 consultations per month)',
                    'Drafting or reviewing two (2) simplified contracts per month',
                    'Preparing one (1) official legal letter or regulatory response per month',
                    'Reviewing company documents and essential legal records',
                    'Following up on client inquiries',
                ],
            ],
            [
                'name' => 'Legal Foundation Package',
                'price_label' => 'Price ranges between',
                'price' => '3,000 - 6,000',
                'features' => [
                    'All services included in the “Protection & Growth” package',
                    'Drafting or reviewing up to four (4) diverse contracts per month',
                    'Preparing two (2) two official letter or regulatory responses per month',
                    'Preparing a monthly legal report that includes: Assessment of existing legal obligations, Identification of potential risks, Operational recommendations for management.',
                    'Reviewing or preparing one internal policy per month (e.g., HR policy, etc.)',
                    'Attending one (1) monthly legal meeting (on-site or remote).',
                ],
            ],
            [
                'name' => 'Comprehensive Legal Management Package',
                'price_label' => 'Custom Quote',
                'price' => 'Contact for Pricing',
                'features' => [
                    'All services included in the “Protection & Growth” package',
                    'Drafting and reviewing commercial contracts on an ongoing basis within the monthly hours cap.',
                    'Conducting an annual or semi-annual Legal Audit. (eg., including a review of contracts, compliance, etc.)',
                    'Preparing or updating: Internal bylaws and work policies, Governance policies, Compliance and anti-corruption policies, Anti–money laundering controls (if needed).',
                    'Attending up to three (3) monthly meetings with management or the Board of Directors.',
                    'Full support in negotiating commercial transactions (MOU – LOI – Term Sheets).',
                ],
            ],
        ],
    ],

    'addons' => [
        'title' => 'Litigation Add-ons',
        'body' => 'Added to any package through a separate agreement.',
        'benefit_label' => 'Benefits for Package Holders:',
        'discount' => 'Exclusive upto 30% Discount',
        'benefit_body' => 'On litigation fees when combined our packages.',
        'items' => [
            'Representing the client before judicial and quasi-judicial authorities according to the scope of the case.',
            'Preparing statements of claim, responses, pleadings, and objections.',
            'Communicating with opposing parties and experts, and submitting legal memoranda.',
            'Government fees, travel expenses, and expert costs are not included.',
        ],
    ],

    'advantages' => [
        'title' => 'Key Advantages of All Packages',
        'items' => [
            'A dedicated legal advisor for the company at a lower cost than internal hiring.',
            'A well-structured hourly system ensuring no wasted hours and maximizing value.',
            'A priority-based SLA (Service Level Agreement) guaranteeing fast response times.',
            'Extensive expertise representing companies before governmental and commercial entities.',
            'A global methodology based on the GCaaS – General Counsel as a Service model.',
        ],
    ],

    'services' => [
        'eyebrow' => 'Our Legal Services',
        'title' => 'We’ll be Your Trusted Legal Partner',
        'body' => 'We provide an integrated selection of specialized legal services tailored to the needs of individuals and businesses across different fields.',
        'items' => [
            [
                'title' => 'Legal Consultations',
                'intro' => 'We offer comprehensive legal consultations across various legal fields, including:',
                'items' => [
                    'Commercial and corporate law',
                    'Real estate law',
                    'Criminal law',
                    'Family law',
                    'Labor law',
                    'Administrative law',
                    'Inheritance law',
                ],
                'outro' => 'Our team of specialized lawyers provides accurate, customized legal solutions for each case.',
            ],
            [
                'title' => 'Contract Drafting & Review',
                'intro' => 'We specialize in drafting and reviewing various types of legal contracts and agreements, including',
                'items' => [
                    'Sale and purchase agreements',
                    'Lease agreements',
                    'Partnership agreements',
                    'Employment contracts',
                    'Non-disclosure agreements (NDA)',
                    'Service agreements',
                ],
                'outro' => 'We ensure that all contracts protect our clients’ interests and comply with applicable laws.',
            ],
            [
                'title' => 'Trademark Registration',
                'intro' => 'We provide comprehensive intellectual property services, including:',
                'items' => [
                    'Trademark Registration locally and internationally',
                    'Copyright protection',
                    'Patent registration',
                    'Industrial design registration',
                    'Supervision of intellectual property rights violations',
                    'Representation in intellectual property disputes',
                ],
                'outro' => 'We help protect your creative and commercial assets from unauthorized use and infringement.',
            ],
            [
                'title' => 'Court Representation',
                'intro' => 'Our team represents clients in all stages of litigation, including:',
                'items' => [
                    'Representation before all judicial authorities',
                    'Appeals before appellate courts',
                    'Challenges before the Supreme Court',
                    'Preparatory procedures for court cases',
                    'Drafting legal memos and defense statements',
                    'Oral and written pleadings',
                ],
                'outro' => 'We bring extensive expertise in handling various cases and ensuring the strongest defense for our clients.',
            ],
            [
                'title' => 'Dispute Resolution',
                'intro' => 'We provide effective legal solutions for resolving disputes through:',
                'items' => [
                    'Mediation and alternative dispute resolution (ADR)',
                    'Reconciliation between disputing parties',
                    'Managing negotiations to settle conflicts',
                    'Representing clients in arbitration sessions',
                    'Enforcing arbitration rulings',
                    'Providing consultations on the best dispute-resolution strategies',
                ],
                'outro' => 'We strive to find fast, efficient solutions that maintain your commercial and legal relationships.',
            ],
            [
                'title' => 'Company Formation',
                'intro' => 'We provide inclusive legal services for businesses, including:',
                'items' => [
                    'Establishment of companies of all types',
                    'Preparing company bylaws',
                    'Consultations on optimal legal structures',
                    'Procedures for amending incorporation contracts',
                    'Compliance with regulations and laws',
                    'Company liquidation',
                ],
                'outro' => 'We ensure all legal procedures are completed efficiently and promptly to establish your company successfully.',
            ],
            [
                'title' => 'Governance',
                'intro' => 'We provide governance services, including:',
                'items' => [
                    'Internal governance regulations',
                    'Partner and shareholder organization',
                    'Authority & responsibility matrices',
                    'Board and committee structuring',
                ],
                'outro' => 'We assist organizations in building clear administrative and operational frameworks that ensure transparency, proper decision-making, and protection against internal disputes and regulatory risks.',
            ],
            [
                'title' => 'Compliance',
                'intro' => 'Our team represents clients in all stages of litigation, including:',
                'items' => [
                    'Regulatory compliance reviews',
                    'Anti-money laundering (AML) policies',
                    'Personal data protection compliance',
                    'Regulatory authority requirements',
                ],
                'outro' => 'Regulatory compliance reviews Anti-money laundering (AML) policies Personal data protection compliance Regulatory authority requirements',
            ],
            [
                'title' => 'Commercial Law',
                'intro' => 'We provide effective legal solutions for resolving disputes through:',
                'items' => [
                    'Drafting and reviewing commercial contracts',
                    'Corporate and business disputes',
                    'Debt collection & claims',
                    'Partnerships and investments',
                ],
                'outro' => 'We provide comprehensive legal solutions for all commercial and corporate activities, from contracts to disputes and investments.',
            ],
            [
                'title' => 'Personal Status & Family Law',
                'intro' => 'We provide inclusive legal services for businesses, including:',
                'items' => [
                    'Divorce cases',
                    'Alimony & child support',
                    'Child custody',
                    'Marriage & divorce documentation',
                    'Inheritance and estate matters',
                ],
                'outro' => 'We handle family and personal status cases with the highest level of confidentiality, professionalism, and legal sensitivity.',
            ],
        ],
    ],

];
