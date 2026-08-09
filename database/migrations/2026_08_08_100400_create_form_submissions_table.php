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
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            // RESTRICTIVE on purpose: submissions are protected admin data,
            // so a form that has collected any cannot be deleted until an
            // authorized user deliberately deletes those submissions
            // individually. Never cascaded away silently.
            $table->foreignId('form_id')
                ->constrained()
                ->restrictOnDelete();
            // Only fields declared in the form definition are ever stored;
            // no IP addresses or other requester metadata is collected.
            $table->jsonb('payload');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
