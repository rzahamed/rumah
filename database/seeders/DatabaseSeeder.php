<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Roles and permissions only — user
     * accounts are never seeded (see `users:create-super-admin`).
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
    }
}
