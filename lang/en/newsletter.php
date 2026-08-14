<?php

/*
 * Newsletter signup strings.
 *
 * The signup endpoint is independent of any page: a frontend places the form
 * wherever it likes and posts to the newsletter route, so these strings live
 * in their own namespace rather than alongside page copy.
 */

return [

    'heading' => 'Subscribe to our newsletter',
    'email_label' => 'Your email',

    // Concise label for the checkbox itself (visually hidden). The visible
    // sentence below is the control's DESCRIPTION, because a link inside a
    // <label for> would toggle the checkbox.
    'consent_label' => 'I accept the Privacy Policy and consent to receive updates',

    // Three plain fragments — no HTML in translation strings, nothing echoed
    // raw. The middle one becomes a link only when the Privacy Policy is
    // published; otherwise it renders as text.
    'consent_before' => 'By submitting, I accept the ',
    'consent_link' => 'Privacy Policy',
    'consent_after' => ' and consent to receive updates.',

    'submit' => 'Subscribe',
    'required' => 'required',

    // Identical for a first-time and a repeat subscription — nothing
    // distinguishes the two cases externally.
    'success' => 'Thank you — your subscription is confirmed.',
    'error_summary' => 'Please check the details below and try again.',

    'errors' => [
        'email' => 'Please enter a valid email address.',
        'consent' => 'Please accept the consent statement before subscribing.',
    ],

];
