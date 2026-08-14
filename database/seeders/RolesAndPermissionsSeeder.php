<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Minimal generic role/permission set. Content permissions (blog, team,
 * forms) are added alongside their resources in later work — nothing is
 * pre-created for features that do not exist yet. Idempotent by design.
 * Never seeds users or passwords: the first super administrator is created
 * interactively via `php artisan users:create-super-admin`.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Panel entry: required by User::canAccessPanel() for every
        // non-super-admin account, separate from user management.
        $panelAccess = Permission::findOrCreate('access_admin', 'web');

        $userManagement = collect([
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.manage_roles',
        ])->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));

        // Content modules follow one verb set; submissions deliberately get
        // only view/delete — they are created by the public endpoint and are
        // never editable.
        $content = collect(['posts', 'categories', 'team', 'faqs'])
            ->flatMap(fn (string $prefix): array => [
                $prefix.'.view',
                $prefix.'.create',
                $prefix.'.update',
                $prefix.'.delete',
            ])
            ->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));

        // Policy pages are LEGAL content: view + update only (the two rows
        // are provisioned by migration, so there is no create or delete
        // verb). Deliberately NOT granted to the editor role below —
        // publishing legal text is an administrator responsibility.
        $policyAdministration = collect([
            'policies.view',
            'policies.update',
        ])->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));

        // Subscribers follow the submissions pattern — created only by the
        // public endpoint, so no create/update verb exists. 'export' is
        // deliberately separate from 'view': reading the list in the panel
        // and carrying the whole list off it are different privileges.
        $formAdministration = collect([
            'forms.view',
            'forms.create',
            'forms.update',
            'forms.delete',
            'submissions.view',
            // Triage state only — the collected payload is never editable.
            // Separate from .view because reading collected data and
            // changing its workflow state are different privileges.
            'submissions.update',
            'submissions.delete',
            // Choosing who receives collected personal data by email is a
            // larger privilege than reading it in the panel, so it is its
            // own permission rather than folded into .view.
            'submissions.manage_notifications',
            'subscribers.view',
            'subscribers.delete',
            'subscribers.export',
        ])->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));

        // Granted everything through the active-super-admin Gate::before
        // override — no explicit permissions.
        Role::findOrCreate('super_admin', 'web');

        // Panel entry plus full user, content, form and policy administration.
        Role::findOrCreate('admin', 'web')
            ->syncPermissions([
                $panelAccess,
                ...$userManagement->all(),
                ...$content->all(),
                ...$formAdministration->all(),
                ...$policyAdministration->all(),
            ]);

        // Content role: publishing content only — no user administration,
        // no form definitions, no collected submission data.
        Role::findOrCreate('editor', 'web')
            ->syncPermissions([$panelAccess, ...$content->all()]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
