<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Support\LocalizedUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A legal policy page (Privacy Policy, Terms of Use).
 *
 * Two canonical rows are provisioned by the creating migration. The key is
 * constrained by a unique index plus a database CHECK, so no other key can
 * be stored; the Filament resource additionally exposes no create or delete
 * action, so ordinary administrator creation and deletion are structurally
 * disabled and an administrator edits titles and bodies and toggles
 * publication, nothing more. (Direct database access can still remove a row —
 * the schema constrains which keys are valid, not that a row must exist.)
 *
 * Bodies are Markdown rendered through App\Support\ArticleBody — stored HTML
 * is escaped rather than executed, and heading levels are clamped to h2..h4
 * so the page keeps exactly one h1 (its title). HasTranslations needs no
 * field declaration: the 'array' casts below are the whole contract, the same
 * pattern Post and TeamMember use.
 */
#[Fillable(['title', 'body', 'is_published'])]
class PolicyPage extends Model
{
    use HasTranslations;

    /** Fixed policy keys. Never taken from user input. */
    public const string PRIVACY = 'privacy-policy';

    public const string TERMS = 'terms-of-use';

    /**
     * Published policies only.
     *
     * @param  Builder<PolicyPage>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Keys of every published policy, memoized for the request.
     *
     * ONE bounded query per request, shared by every consumer (footer legal
     * bar in both branches, newsletter consent line). once() is the same
     * request-memoization SiteSettings::current() uses; nothing is cached
     * across requests, so publishing a policy takes effect immediately with
     * no invalidation to get wrong.
     *
     * @return list<string>
     */
    public static function publishedKeys(): array
    {
        return once(fn (): array => static::query()
            ->published()
            ->pluck('key')
            ->all());
    }

    /**
     * Locale-correct public URL for a policy, or null when the key is not a
     * supported policy or that policy is not published — callers render plain
     * text for null, so a link to a route that would 404 is never emitted.
     */
    public static function urlFor(string $key, string $locale): ?string
    {
        // Resolve the route name FIRST: an unsupported key must never reach
        // the published-keys lookup or LocalizedUrl.
        $routeName = match ($key) {
            self::PRIVACY => 'policy.privacy',
            self::TERMS => 'policy.terms',
            default => null,
        };

        if ($routeName === null || ! in_array($key, static::publishedKeys(), true)) {
            return null;
        }

        // LocalizedUrl returns null when the page is not routed in this
        // locale, so an unbuilt policy page yields plain text rather than a
        // link that would 404.
        return LocalizedUrl::to($locale, $routeName);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => 'array',
            'body' => 'array',
            'is_published' => 'boolean',
        ];
    }
}
