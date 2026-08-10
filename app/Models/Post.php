<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Concerns\HasTranslations;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Fillable(['category_id', 'title', 'slug', 'excerpt', 'body', 'status', 'published_at', 'featured_image_path'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasTranslations;

    /**
     * Featured-image lifecycle guarantees enforced at the MODEL layer so they
     * bind every write path, not just the Filament form: replacing the image
     * deletes the previous file, and deleting a post deletes its image. Both
     * deletions run only AFTER the database change commits — a rolled back
     * transaction must never leave a row pointing at an already-deleted
     * file. Disk and path are captured as scalars at event time.
     */
    protected static function booted(): void
    {
        static::updated(function (Post $post): void {
            $previous = $post->getOriginal('featured_image_path');

            if ($post->wasChanged('featured_image_path') && $previous !== null) {
                $disk = (string) config('platform.blog_featured_disk');
                $path = (string) $previous;

                DB::afterCommit(function () use ($disk, $path): void {
                    Storage::disk($disk)->delete($path);
                });
            }
        });

        static::deleted(function (Post $post): void {
            if ($post->featured_image_path !== null) {
                $disk = (string) config('platform.blog_featured_disk');
                $path = (string) $post->featured_image_path;

                DB::afterCommit(function () use ($disk, $path): void {
                    Storage::disk($disk)->delete($path);
                });
            }
        });
    }

    /**
     * Public URL of the featured image, or null when none is set — callers
     * render the deliberate branded fallback for null, never a broken image.
     */
    public function featuredImageUrl(): ?string
    {
        if ($this->featured_image_path === null) {
            return null;
        }

        return Storage::disk((string) config('platform.blog_featured_disk'))
            ->url($this->featured_image_path);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => 'array',
            'excerpt' => 'array',
            'body' => 'array',
            'status' => PostStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Publicly visible: published status AND a publish moment in the past.
     *
     * @param  Builder<Post>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
