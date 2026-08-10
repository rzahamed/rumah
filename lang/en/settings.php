<?php

return [

    'title' => 'Site settings',
    'navigation_label' => 'Site settings',
    'saved' => 'Settings saved.',
    'save' => 'Save',

    'custom_code' => [
        'section' => 'Custom code',
        'warning' => 'Snippets are output RAW on the public website only, at the four positions below. They run with full page privileges — paste only code you trust.',
        'head_start' => 'Immediately after the opening <head> tag',
        'head_end' => 'Immediately before the closing </head> tag',
        'body_start' => 'Immediately after the opening <body> tag',
        'body_end' => 'Immediately before the closing </body> tag',
    ],

    'cal' => [
        'section' => 'Booking',
        'url' => 'Cal.com booking URL',
        'helper' => 'HTTPS URL on cal.com (or an approved Cal domain), e.g. https://cal.com/your-team/consultation. The Contact page hides its booking section while this is empty.',
    ],

    'cal_url_invalid' => 'The booking URL must be an HTTPS link on an approved Cal.com host, without query parameters.',

];
