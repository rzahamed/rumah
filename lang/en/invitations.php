<?php

return [

    'accepted' => 'Your account is ready. Sign in with your new password.',

    'email' => [
        // ":app" is the configured brand name (platform.brand_name), never a
        // hard-coded environment or product string.
        'subject' => 'Invitation to manage :app',
        'greeting' => 'Hello :name,',
        'intro' => 'You have been invited to manage :app. Set a password using the button below to activate your account.',
        'action' => 'Set your password',
        'expiry' => 'This invitation link expires in :days days and can be used once.',
        'ignore' => 'If you did not expect this invitation, you can safely ignore this email.',
    ],

    'accept' => [
        'title' => 'Set your password',
        'intro' => 'Choose a password for :email to activate your account.',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'submit' => 'Activate account',
    ],

];
