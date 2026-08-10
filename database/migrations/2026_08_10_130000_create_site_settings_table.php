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
        // Focused single-row settings table with one typed column per
        // setting — deliberately NOT a generic key/value store. Snippets
        // are trusted raw HTML managed exclusively by active super admins
        // and rendered only on the public frontend.
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            // Database-enforced singleton, part 1: the unique index allows
            // at most one TRUE row.
            $table->boolean('singleton')->default(true)->unique();
            $table->text('custom_head_start')->nullable();
            $table->text('custom_head_end')->nullable();
            $table->text('custom_body_start')->nullable();
            $table->text('custom_body_end')->nullable();
            $table->string('cal_booking_url')->nullable();
            $table->timestamps();
        });

        // Part 2 (this project is PostgreSQL-only): every row MUST carry
        // TRUE, so together with the unique index the table permits
        // at most one row — no FALSE-row loophole.
        DB::statement(
            'ALTER TABLE site_settings
             ADD CONSTRAINT site_settings_singleton_must_be_true
             CHECK (singleton = TRUE)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
