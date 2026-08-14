<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\TeamMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'position', 'bio', 'photo_path', 'sort_order', 'is_visible', 'email', 'highlights', 'credentials', 'expertise', 'licence_image_path'])]
class TeamMember extends Model
{
    /** @use HasFactory<TeamMemberFactory> */
    use HasFactory, HasTranslations;

    /**
     * Media lifecycle guarantees enforced at the MODEL layer so they bind
     * every write path, not just the Filament form: replacing the photo or
     * licence image queues the previous file for deletion, and deleting a
     * member queues both files. Every deletion runs only AFTER the database
     * change commits, and only if the committed state confirms the path is
     * no longer referenced — a rolled back transaction must never lose a
     * file, and a path shared between attributes or rows must survive.
     */
    protected static function booted(): void
    {
        static::updated(function (TeamMember $member): void {
            foreach (['photo_path', 'licence_image_path'] as $attribute) {
                $previous = $member->getOriginal($attribute);

                if ($member->wasChanged($attribute) && $previous !== null) {
                    self::deleteAfterCommitIfUnreferenced((string) $previous);
                }
            }
        });

        static::deleted(function (TeamMember $member): void {
            foreach (['photo_path', 'licence_image_path'] as $attribute) {
                $path = $member->getAttribute($attribute);

                if ($path !== null) {
                    self::deleteAfterCommitIfUnreferenced((string) $path);
                }
            }
        });
    }

    /**
     * Queue a media file for deletion after the surrounding transaction
     * commits — and delete only if the COMMITTED database state confirms no
     * team member still references the path in either media column. Covers
     * paths shared between the two attributes, swapped between attributes,
     * or reused by another row. Disk and path are captured as scalars at
     * event time.
     */
    private static function deleteAfterCommitIfUnreferenced(string $path): void
    {
        $disk = (string) config('platform.media_disk');

        DB::afterCommit(function () use ($disk, $path): void {
            $stillReferenced = self::query()
                ->where('photo_path', $path)
                ->orWhere('licence_image_path', $path)
                ->exists();

            if (! $stillReferenced) {
                Storage::disk($disk)->delete($path);
            }
        });
    }

    /**
     * Public URL of the member photo on the configured media disk, or
     * null when no photo is set (the public site renders its branded
     * fallback tile instead).
     */
    public function photoUrl(): ?string
    {
        if ($this->photo_path === null) {
            return null;
        }

        return Storage::disk((string) config('platform.media_disk'))
            ->url($this->photo_path);
    }

    /**
     * Public URL of the practice-licence image on the configured media
     * disk, or null when none is set (the profile simply omits it).
     */
    public function licenceImageUrl(): ?string
    {
        if ($this->licence_image_path === null) {
            return null;
        }

        return Storage::disk((string) config('platform.media_disk'))
            ->url($this->licence_image_path);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'position' => 'array',
            'bio' => 'array',
            'highlights' => 'array',
            'credentials' => 'array',
            'expertise' => 'array',
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    /**
     * @param  Builder<TeamMember>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_visible', true);
    }

    /**
     * @return list<string>
     */
    public function localizedHighlights(?string $locale = null): array
    {
        return $this->localizedListFrom($this->highlights, $locale);
    }

    /**
     * @return list<string>
     */
    public function localizedExpertise(?string $locale = null): array
    {
        return $this->localizedListFrom($this->expertise, $locale);
    }

    /**
     * Locale-resolved credential entries. Items without a resolvable title
     * are dropped; institution and description are null when absent so the
     * modal renders no empty headings.
     *
     * @return list<array{title: string, institution: ?string, description: ?string}>
     */
    public function localizedCredentials(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        return collect(is_array($this->credentials) ? $this->credentials : [])
            ->map(function ($item) use ($locale): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $title = is_array($item['title'] ?? null)
                    ? $this->pickLocalized($item['title'], $locale)
                    : null;

                if ($title === null) {
                    return null;
                }

                return [
                    'title' => $title,
                    'institution' => is_array($item['institution'] ?? null)
                        ? $this->pickLocalized($item['institution'], $locale)
                        : null,
                    'description' => is_array($item['description'] ?? null)
                        ? $this->pickLocalized($item['description'], $locale)
                        : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Locale-resolved values of a locale-keyed JSONB list. Items with no
     * value in the current or default locale are dropped — optional content
     * yields a smaller list, never blank rows.
     *
     * @return list<string>
     */
    private function localizedListFrom(mixed $items, ?string $locale): array
    {
        $locale ??= app()->getLocale();

        return collect(is_array($items) ? $items : [])
            ->map(fn ($item): ?string => is_array($item)
                ? $this->pickLocalized($item, $locale)
                : null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private function pickLocalized(array $values, string $locale): ?string
    {
        $value = $values[$locale]
            ?? $values[config('platform.default_locale', 'en')]
            ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
