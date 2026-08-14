<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The only keys this table may ever hold, with their page LABELS (not
     * legal copy). Footer links, the newsletter consent line and the public
     * routes all depend on these exact keys, so they are fixed in the schema
     * rather than left editable.
     *
     * Single source for BOTH the CHECK constraint and the provisioned rows —
     * the two can never drift apart.
     */
    private const POLICIES = [
        'privacy-policy' => ['en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
        'terms-of-use' => ['en' => 'Terms of Use', 'ar' => 'شروط الاستخدام'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('policy_pages', function (Blueprint $table) {
            $table->id();
            // Stable identifier — never user-editable.
            $table->string('key', 32)->unique();
            // Locale-keyed translations ({"en": ..., "ar": ...}).
            $table->jsonb('title');
            // Markdown per locale, rendered through App\Support\ArticleBody:
            // stored HTML is escaped, never executed.
            $table->jsonb('body');
            // Unpublished by default: a policy is legal content and must not
            // become reachable until its body is client-approved.
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        $allowed = collect(array_keys(self::POLICIES))
            ->map(fn (string $key): string => "'".$key."'")
            ->implode(', ');

        DB::statement(
            "ALTER TABLE policy_pages ADD CONSTRAINT policy_pages_key_check CHECK (key IN ({$allowed}))"
        );

        $this->provisionCanonicalRows();
    }

    /**
     * The two canonical rows are FIXED SYSTEM RECORDS: the Filament resource
     * deliberately exposes no create action, so they must exist before an
     * administrator can edit them.
     *
     * Written with the query builder, not Eloquent — no model class, casts,
     * fillable rules or model events participate, so this keeps working
     * regardless of how App\Models\PolicyPage evolves (or whether it exists
     * when the migration is replayed).
     *
     * insertOrIgnore compiles to "on conflict do nothing" on PostgreSQL
     * (PostgresGrammar::compileInsertOrIgnore), so re-running provisions only
     * genuinely missing rows and can never overwrite an administrator's
     * edited title, body or publication state.
     *
     * Bodies are intentionally empty and is_published is false: the client
     * must supply and approve the Privacy Policy and Terms of Use text before
     * either page can go live.
     */
    private function provisionCanonicalRows(): void
    {
        $now = now();

        $rows = collect(self::POLICIES)
            ->map(fn (array $title, string $key): array => [
                'key' => $key,
                'title' => json_encode($title, JSON_UNESCAPED_UNICODE),
                'body' => json_encode(['en' => '', 'ar' => ''], JSON_UNESCAPED_UNICODE),
                'is_published' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        DB::table('policy_pages')->insertOrIgnore($rows);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_pages');
    }
};
