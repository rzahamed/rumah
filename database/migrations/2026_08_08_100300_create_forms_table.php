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
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            // Internal (admin-facing) label.
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            // Ordered field definitions:
            // [{name, type, required, label: {locale: ...}}, ...]
            $table->jsonb('fields');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
