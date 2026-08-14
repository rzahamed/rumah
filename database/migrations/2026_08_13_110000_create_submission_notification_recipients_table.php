<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal users who receive an email when a public form submission is
 * created.
 *
 * A normalized table rather than a JSON blob or an env list: recipients are
 * real user references, so they need a foreign key that follows user
 * deletion, and a UNIQUE constraint that makes duplicate recipients
 * impossible in the DATABASE rather than only in application code — a
 * duplicated row would otherwise mean a duplicated email.
 *
 * cascadeOnDelete is correct here (unlike form_submissions.reviewed_by): a
 * recipient row is meaningless without its user, and silently dropping it
 * loses nothing but the routing preference.
 *
 * No recipients are seeded; the list starts empty and is configured in the
 * panel by an actor holding submissions.manage_notifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_notification_recipients', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_notification_recipients');
    }
};
