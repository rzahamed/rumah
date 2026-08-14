<?php

return [

    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'invited' => 'Invited',
    ],

    'locales' => [
        'en' => 'English',
        'ar' => 'العربية',
    ],

    'singular' => 'User',
    'plural' => 'Users',

    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'status' => 'Status',
        // Singular: the product model is mutually exclusive — a user holds
        // exactly one role.
        'role' => 'Role',
        'last_login_at' => 'Last login',
        'preferred_admin_locale' => 'Panel language',
        'created_at' => 'Created',
    ],

    'actions' => [
        'invite' => 'Invite user',
        'invitation_sent' => 'Invitation sent.',
        'resend_invitation' => 'Resend invitation',
        'invitation_resent' => 'Invitation resent.',
        'resend_unauthorized' => 'You are not authorized to resend invitations.',
        'resend_not_invited' => 'Only users with a pending invitation can have it resent.',
    ],

    'guards' => [
        'self_delete' => 'You cannot delete your own account.',
        'last_super_admin_delete' => 'The last active super administrator cannot be deleted.',
        'last_super_admin_deactivate' => 'The last active super administrator cannot be deactivated.',
        'last_super_admin_role' => 'The last active super administrator must keep the super_admin role.',
        'roles_unauthorized' => 'You are not authorized to change roles.',
        'roles_invalid' => 'Every role must be a non-empty string.',
        'roles_unknown' => 'One or more selected roles do not exist.',
        'status_invalid' => 'The selected status is invalid.',
        'invited_status_locked' => 'Invited accounts can only be activated by accepting their invitation.',
        'super_admin_invite' => 'Only an active super administrator can assign the super administrator role.',
    ],

    'roles_info' => [
        'heading' => 'Role access',
    ],

    'roles' => [
        'super_admin' => [
            'label' => 'Super administrator',
            'description' => 'Bypasses every permission check while active; full access to everything, including inviting other super administrators.',
        ],
        'admin' => [
            'label' => 'Administrator',
            'description' => 'Panel access with full administration of users, posts, categories, team members, forms, and received submissions.',
        ],
        // DISPLAY label only. The database role machine name stays 'editor';
        // renaming that would break every permission grant and policy check.
        'editor' => [
            'label' => 'Content Editor',
            'description' => 'Panel access with content publishing only: posts, categories, and team members.',
        ],
    ],

];
