<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Lifecycle: 'active' may sign in; 'inactive' is locked out;
            // 'invited' exists only until the invitation is accepted.
            // Added nullable, backfilled, then enforced NOT NULL below so the
            // migration is safe on tables that already contain users. No
            // permanent database default: every code path sets status
            // explicitly.
            $table->string('status', 20)->nullable();
            $table->string('preferred_admin_locale', 10)->nullable();
            $table->timestampTz('last_login_at')->nullable();
            // Only a SHA-256 hash of the invitation token is ever stored;
            // the plaintext token exists solely in the emailed one-time URL.
            $table->string('invitation_token_hash', 64)->nullable()->unique();
            $table->timestampTz('invitation_expires_at')->nullable();
        });

        // Pre-existing accounts keep working.
        DB::table('users')
            ->whereNull('status')
            ->update(['status' => 'active']);

        DB::statement('ALTER TABLE users ALTER COLUMN status SET NOT NULL');

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'inactive', 'invited'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'preferred_admin_locale',
                'last_login_at',
                'invitation_token_hash',
                'invitation_expires_at',
            ]);
        });
    }
};
