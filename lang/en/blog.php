<?php

/*
| Blog copy, transcribed verbatim from the Paper design file (Desktop / Blog
| Index Page and Desktop / Blog Post Page). Nothing here is authored by the
| implementation.
|
| Articles, categories, dates, excerpts and bodies are NOT here: both pages
| read the CMS Post module. Only the fixed copy Paper draws around them is
| transcribed. Paper's second eyebrow reads "Al Blogs" — a slip for "All
| Blogs" — and is carried exactly as drawn.
*/

return [

    'meta' => [
        'title' => 'Blog',
    ],

    'index' => [
        'hero' => [
            'eyebrow' => 'All Blogs',
            'title' => 'Insights, Legal Updates, and Expert Guidance',
            'body' => 'Stay informed with practical legal insights, updates on Saudi regulations, and expert commentary from our team. Our blog is designed to help individuals and businesses navigate the legal landscape with clarity and confidence.',
        ],
        'latest_title' => 'Latest Blog',
        'insights' => [
            'eyebrow' => 'Al Blogs',
            'title' => 'Our Legal Insights',
        ],
        'read_more' => 'Read More',
        // Not drawn in Paper: the visually hidden suffix that turns each
        // "Read More" into a distinct link name for assistive technology.
        'read_more_suffix' => ': :title',
        'newsletter' => [
            'title' => 'Stay ahead of legal risks — before they become problems.!',
            'body' => 'In a constantly evolving legal environment, keeping up with new regulations, case developments, and best practices is essential. Our articles provide thoughtful analysis and actionable guidance across various practice areas — from corporate law and contracts to dispute resolution and intellectual property. Whether you are a business owner, an entrepreneur, or an individual seeking clarity, our blog delivers the knowledge you need to make informed decisions.',
            'subscribe_now' => 'Subscribe Now.',
            'placeholder' => 'name@company.com',
        ],
    ],

];
