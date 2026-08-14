<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds triage state to collected submissions.
 *
 * SAFE ON A POPULATED DATABASE: status carries a constant NOT NULL default,
 * so PostgreSQL backfills every existing row to 'new' as part of the ALTER
 * — there is no data-migration step and nothing is guessed as reviewed.
 * Historical submissions correctly appear as untriaged.
 *
 * The CHECK constraint makes the enum authoritative in the DATABASE, not
 * merely in Eloquent: a raw insert or a stray update cannot park a row in a
 * state the application cannot represent. Mirrors the approach already used
 * for user statuses.
 *
 * Deliberately adds NO requester metadata (IP, user agent, source). Their
 * absence is an intentional privacy property of this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->string('status', 16)
                ->default('new')
                ->index();

            $table->timestamp('reviewed_at')->nullable();

            // nullOnDelete: removing a reviewer must neither delete the
            // submission nor block that user's deletion. Attribution drops
            // to null and the collected data survives.
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::statement("
            ALTER TABLE form_submissions
            ADD CONSTRAINT form_submissions_status_check
            CHECK (status IN ('new', 'reviewed', 'archived'))
        ");
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE form_submissions
            DROP CONSTRAINT IF EXISTS form_submissions_status_check
        ');

        Schema::table('form_submissions', function (Blueprint $table): void {
            // The foreign key must go before its column.
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['status', 'reviewed_at', 'reviewed_by']);
        });
    }
};
