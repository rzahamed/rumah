<?php

/*
| Contact page copy, transcribed verbatim from the Paper design file
| (Desktop / Contact Page). Nothing here is authored by the implementation.
|
| The consultation form's FIELD labels, placeholders and choices are NOT here:
| the form is the CMS's admin-defined Contact form and its stored definition
| supplies them (lang for the shipped defaults lives in the provisioning
| migration and is edited in the admin panel). Only the copy Paper draws
| around the form — its title, intro and submit label — is transcribed.
|
| The FAQ questions and answers Paper draws are likewise not here: the page
| reads the CMS FAQ module. Only the section head is transcribed.
*/

return [

    'meta' => [
        'title' => 'Contact',
    ],

    'hero' => [
        'eyebrow' => 'Contact Us',
        'title' => 'Connect With Our Legal Experts',
        'body' => 'Our experienced attorneys will review your inquiry and provide clear, actionable guidance. Get personalized legal insights from qualified professionals who understand your needs.',
    ],

    'process' => [
        'title' => 'Consultation Process',
        'body' => 'We follow an organized process to ensure you receive the best legal consultation tailored to your needs.',
        'steps' => [
            [
                'title' => 'Submit Your Request',
                'body' => 'Fill out the consultation request form and provide the essential details about your case.',
            ],
            [
                'title' => 'Request Review',
                'body' => 'Our team reviews your request and assigns the most suitable attorney for your legal matter.',
            ],
            [
                'title' => 'Schedule an Appointment',
                'body' => 'We schedule your consultation—whether by phone, video call, or in our office.',
            ],
            [
                'title' => 'The Consultation',
                'body' => 'Receive a professional legal consultation with clear, practical solutions for your case.',
            ],
        ],
    ],

    'form' => [
        'title' => 'Request a Consultation Now',
        'intro' => 'Fill out the form below and we will contact you as soon as possible.',
        'submit' => 'Submit',
        // Not drawn in Paper: the accessible summary shown above the fields
        // when a submission is sent back with validation errors.
        'error_summary' => 'Please check the highlighted fields and try again.',
    ],

    'faq' => [
        'eyebrow' => 'FAQ',
        'title' => 'Got Questions? We Got Answers',
        'body' => 'Answers to the most common questions about our legal consultation services.',
    ],

];
