<?php

return [

    'post_status' => [
        'draft' => 'Draft',
        'published' => 'Published',
    ],

    'fields' => [
        'title' => 'Title',
        'name' => 'Name',
        'slug' => 'Slug',
        'category' => 'Category',
        'excerpt' => 'Excerpt',
        'body' => 'Body',
        'status' => 'Status',
        'published_at' => 'Published at',
        'position' => 'Position',
        'bio' => 'Bio',
        'photo' => 'Photo',
        'featured_image' => 'Featured image',
        'email' => 'Email',
        'highlights' => 'Highlights',
        'highlight' => 'Highlight',
        'credentials' => 'Credentials',
        'credential_title' => 'Title',
        'credential_institution' => 'Institution',
        'credential_description' => 'Description',
        'expertise' => 'Expertise',
        'expertise_item' => 'Expertise area',
        'licence_image' => 'Licence image',
        'question' => 'Question',
        'answer' => 'Answer',
        'sort_order' => 'Sort order',
        'is_visible' => 'Visible',
        'is_active' => 'Active',
        'form' => 'Form',
        'form_fields' => 'Fields',
        'field_name' => 'Field name',
        'field_type' => 'Field type',
        'field_required' => 'Required',
        'field_required_consent' => 'Must be ticked (consent)',
        'field_label' => 'Label',
        'field_options' => 'Options',
        'option_value' => 'Option value',
        'option_label' => 'Option label',
        'payload' => 'Submitted data',
        'contact_name' => 'Name',
        'phone' => 'Phone',
        'case_interest' => 'Case interest',
        'reviewed_at' => 'Reviewed at',
        'reviewed_by' => 'Reviewed by',
        'submitted_at' => 'Submitted at',
        'submissions_count' => 'Submissions',
        'posts_count' => 'Posts',
        'created_at' => 'Created',
        'locale' => 'Language',
        'consented_at' => 'Consent given',
        'subscribed_at' => 'Subscribed',
        'policy_key' => 'Page key',
        'is_published' => 'Published',
        'updated_at' => 'Last updated',
    ],

    'field_types' => [
        'text' => 'Text',
        'textarea' => 'Text area',
        'email' => 'Email',
        'tel' => 'Phone',
        'number' => 'Number',
        'select' => 'Select',
        'checkbox' => 'Checkbox',
    ],

    'faq' => [
        'singular' => 'FAQ',
        'plural' => 'FAQs',
    ],

    'posts' => [
        'singular' => 'Post',
        'plural' => 'Posts',
    ],

    'categories' => [
        'singular' => 'Category',
        'plural' => 'Categories',
    ],

    'team' => [
        'singular' => 'Team member',
        'plural' => 'Team members',
    ],

    'submissions' => [
        'singular' => 'Contact request',
        'plural' => 'Contact requests',
        'contact_section' => 'Contact details',
        'all_fields_section' => 'All submitted information',
        'metadata_section' => 'Record details',
        'yes' => 'Yes',
        'no' => 'No',
        'update_status' => 'Update status',
        'save_status' => 'Save status',
        'status_updated' => 'Status updated.',
        'status_invalid' => 'That status is not recognised.',
        // Deliberately generic: never discloses whether a hidden user exists.
        'recipients_invalid' => 'One or more selected recipients are not available. Please review your selection.',
        'not_provided' => 'Not provided',
        'configure_notifications' => 'Configure notifications',
        'configure_notifications_hint' => 'Choose which team members receive an email when a new contact request arrives.',
        'recipients' => 'Email recipients',
        'recipients_hint' => 'Only active team members with panel access can be selected.',
        'save_recipients' => 'Save recipients',
        'recipients_saved' => ':count recipient(s) will be notified.',
        'email' => [
            'subject' => 'New contact request — :app',
            'greeting' => 'Hello :name,',
            'intro' => 'A new contact request has been submitted.',
            'name' => 'Name: :value',
            'email' => 'Email: :value',
            'phone' => 'Phone: :value',
            'interest' => 'Case interest: :value',
            'action' => 'View request',
            'outro' => 'Open the panel to read the full message and reply.',
        ],
    ],

    'submission_status' => [
        'new' => 'New',
        'reviewed' => 'Reviewed',
        'archived' => 'Archived',
    ],

    // Shared by BOTH public submission boundaries (admin-defined forms and
    // the newsletter). 'required' states the visitor's own form state;
    // 'failed' is the single neutral outcome for every server-side
    // rejection — no upstream detail is ever surfaced.
    'turnstile' => [
        // Visually hidden: the widget is an opaque third-party iframe, so the
        // field still needs a name a screen reader can announce.
        'label' => 'Security check',
        'required' => 'Please complete the security check before submitting.',
        'failed' => 'We could not verify that you are human. Please try again.',
    ],

    'hints' => [
        'body_markdown' => 'Markdown is supported: headings (## and ###), **bold**, and bullet lists (- item). HTML is displayed as plain text, never rendered.',
        'policy_publication' => 'A policy is only reachable on the website once published. Both language bodies must be filled in before publishing.',
        'field_name' => 'The internal key saved with every submission — not shown to visitors. Use lowercase letters, numbers and underscores, for example full_name. Anything else is converted automatically.',
        'field_name_locked' => 'This field already has collected submissions, so its key can no longer be changed. Change the label instead — that is what visitors see.',
    ],

    'policies' => [
        'singular' => 'Policy page',
        'plural' => 'Policy pages',
        'publish_blocked_title' => 'This policy cannot be published yet',
        'publish_blocked_body' => 'Add the body text for :locales before publishing. Nothing was saved.',
    ],

    'subscribers' => [
        'singular' => 'Subscriber',
        'plural' => 'Newsletter subscribers',
        'export' => 'Export CSV',
    ],

    'forms' => [
        'singular' => 'Contact form',
        'plural' => 'Contact form',
        'submitted' => 'Thank you — your submission has been received.',
        'duplicate_field_name' => 'Two fields would end up with the same internal key. Give each field a distinct key.',
        'invalid_field_name' => 'The internal key must start with a lowercase Latin letter and contain only lowercase Latin letters, numbers and underscores, up to 64 characters — for example full_name.',
    ],

];
