<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            // Public professional contact address — plain string, not
            // translatable. Rendered only in the team biography modal.
            $table->string('email')->nullable();
            // Locale-keyed JSONB lists ([{"en": ..., "ar": ...}, ...]).
            $table->jsonb('highlights')->nullable();
            // Structured items: {title: {...}, institution: {...}|null,
            // description: {...}|null}.
            $table->jsonb('credentials')->nullable();
            $table->jsonb('expertise')->nullable();
            // Same media disk as the photo; commit-safe lifecycle.
            $table->string('licence_image_path', 2048)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn(['email', 'highlights', 'credentials', 'expertise', 'licence_image_path']);
        });
    }
};
