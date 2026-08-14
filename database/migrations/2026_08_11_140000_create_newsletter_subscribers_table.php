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
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            // Stored already normalized (trimmed + lowercased by
            // NewsletterSubscriber::normalizeEmail), so this unique index is
            // case-insensitive in practice: it is what makes a repeat signup
            // a no-op rather than a second row. 254 = the maximum length of
            // an addr-spec.
            $table->string('email', 254)->unique();
            // Locale of the FIRST subscription; a later signup in another
            // locale never rewrites it. Validated against supported_locales
            // rather than constrained in schema, so the column survives any
            // SUPPORTED_LOCALES change.
            $table->string('locale', 12);
            // Explicit consent moment. The consent checkbox is enforced
            // server-side ('accepted'), never by client JavaScript.
            $table->timestampTz('consented_at');
            $table->timestamps();
            // Deliberately absent: IP address, user agent and any other
            // requester metadata — the same posture as form_submissions.
            // Also absent: unsubscribe tokens, because no sending system
            // exists yet and the shape one would need is not yet known.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
