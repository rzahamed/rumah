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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            // Deleting a category must never delete content: posts fall
            // back to uncategorized.
            $table->foreignId('category_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            // Locale-keyed translations ({"en": ..., "ar": ...}).
            $table->jsonb('title');
            $table->string('slug')->unique();
            $table->jsonb('excerpt')->nullable();
            $table->jsonb('body');
            // Same explicit-status pattern as users: no database default,
            // enforced value set.
            $table->string('status', 20);
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE posts ADD CONSTRAINT posts_status_check CHECK (status IN ('draft', 'published'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
