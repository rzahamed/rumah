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

#[Fillable(['name', 'position', 'bio', 'photo_path', 'sort_order', 'is_visible'])]
class TeamMember extends Model
{
    /** @use HasFactory<TeamMemberFactory> */
    use HasFactory, HasTranslations;

    /**
     * Photo lifecycle guarantees enforced at the MODEL layer so they bind
     * every write path, not just the Filament form: replacing a photo
     * deletes the previous file, and deleting a member deletes its photo.
     * Both deletions run only AFTER the database change commits — a rolled
     * back transaction must never leave a row pointing at an already-deleted
     * file. Disk and path are captured as scalars at event time.
     */
    protected static function booted(): void
    {
        static::updated(function (TeamMember $member): void {
            $previous = $member->getOriginal('photo_path');

            if ($member->wasChanged('photo_path') && $previous !== null) {
                $disk = (string) config('platform.media_disk');
                $path = (string) $previous;

                DB::afterCommit(function () use ($disk, $path): void {
                    Storage::disk($disk)->delete($path);
                });
            }
        });

        static::deleted(function (TeamMember $member): void {
            if ($member->photo_path !== null) {
                $disk = (string) config('platform.media_disk');
                $path = (string) $member->photo_path;

                DB::afterCommit(function () use ($disk, $path): void {
                    Storage::disk($disk)->delete($path);
                });
            }
        });
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
}
