<?php

namespace Tests\Feature\Content;

use App\Enums\PostStatus;
use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Filament\Resources\PostResource\Pages\EditPost;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Featured-image lifecycle guarantees on the env-driven blog featured disk.
 * Runs under DatabaseMigrations — NOT RefreshDatabase — so every save
 * COMMITS for real and DB::afterCommit fires exactly as in production; the
 * rollback tests therefore prove the contract without test-transaction
 * indirection. The Filament-path tests additionally exercise the
 * page-scoped transactions and the ownership-ledger cleanup.
 */
class BlogFeaturedImageTest extends TestCase
{
    use DatabaseMigrations;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->disk = (string) config('platform.blog_featured_disk');

        Storage::fake($this->disk);
    }

    private function editor(): User
    {
        $user = User::factory()->create();

        $user->assignRole('editor');

        return $user;
    }

    /**
     * Minimal valid post form payload for the Create page.
     *
     * @return array<string, mixed>
     */
    private function validPostInput(): array
    {
        return [
            'title' => ['en' => 'With Image'],
            'slug' => 'with-image',
            'body' => ['en' => 'Body copy.'],
            'status' => PostStatus::Draft->value,
        ];
    }

    public function test_featured_image_is_stored_under_the_configured_directory_with_public_visibility(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreatePost::class)
            ->fillForm([
                ...$this->validPostInput(),
                'featured_image_path' => UploadedFile::fake()->image('cover.jpg'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::query()->sole();

        $this->assertNotNull($post->featured_image_path);
        $this->assertStringStartsWith(config('platform.blog_featured_dir').'/', $post->featured_image_path);

        Storage::disk($this->disk)->assertExists($post->featured_image_path);
        $this->assertSame('public', Storage::disk($this->disk)->getVisibility($post->featured_image_path));
    }

    public function test_featured_image_is_optional(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreatePost::class)
            ->fillForm($this->validPostInput())
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(Post::query()->sole()->featured_image_path);
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreatePost::class)
            ->fillForm([
                ...$this->validPostInput(),
                'featured_image_path' => UploadedFile::fake()->create('document.pdf', 128, 'application/pdf'),
            ])
            ->call('create')
            ->assertHasFormErrors(['featured_image_path']);

        $this->assertSame(0, Post::query()->count());
    }

    public function test_committed_replacement_deletes_the_old_file_and_keeps_the_new_one(): void
    {
        Storage::disk($this->disk)->put('blogs/old.jpg', 'old');
        Storage::disk($this->disk)->put('blogs/new.jpg', 'new');

        $post = Post::factory()->create(['featured_image_path' => 'blogs/old.jpg']);

        $post->update(['featured_image_path' => 'blogs/new.jpg']);

        Storage::disk($this->disk)->assertMissing('blogs/old.jpg');
        Storage::disk($this->disk)->assertExists('blogs/new.jpg');
        $this->assertSame('blogs/new.jpg', $post->fresh()->featured_image_path);
    }

    public function test_committed_deletion_deletes_the_featured_image(): void
    {
        Storage::disk($this->disk)->put('blogs/gone.jpg', 'bytes');

        $post = Post::factory()->create(['featured_image_path' => 'blogs/gone.jpg']);

        $post->delete();

        Storage::disk($this->disk)->assertMissing('blogs/gone.jpg');
    }

    public function test_rolled_back_replacement_preserves_the_old_file_and_database_value(): void
    {
        Storage::disk($this->disk)->put('blogs/old.jpg', 'old');
        Storage::disk($this->disk)->put('blogs/new.jpg', 'new');

        $post = Post::factory()->create(['featured_image_path' => 'blogs/old.jpg']);

        try {
            DB::transaction(function () use ($post): void {
                $post->update(['featured_image_path' => 'blogs/new.jpg']);

                throw new RuntimeException('force rollback');
            });

            $this->fail('Expected the forced rollback exception.');
        } catch (RuntimeException) {
            // Expected.
        }

        Storage::disk($this->disk)->assertExists('blogs/old.jpg');
        // The MODEL layer must never delete on rollback: 'blogs/new.jpg'
        // stands in for a committed file here — cleanup authority for fresh
        // uploads lives only in the page-level ownership ledger.
        Storage::disk($this->disk)->assertExists('blogs/new.jpg');
        $this->assertSame('blogs/old.jpg', $post->fresh()->featured_image_path);
    }

    public function test_rolled_back_deletion_preserves_the_row_and_file(): void
    {
        Storage::disk($this->disk)->put('blogs/kept.jpg', 'bytes');

        $post = Post::factory()->create(['featured_image_path' => 'blogs/kept.jpg']);

        try {
            DB::transaction(function () use ($post): void {
                $post->delete();

                throw new RuntimeException('force rollback');
            });

            $this->fail('Expected the forced rollback exception.');
        } catch (RuntimeException) {
            // Expected.
        }

        Storage::disk($this->disk)->assertExists('blogs/kept.jpg');
        $this->assertNotNull(Post::query()->find($post->getKey()));
    }

    public function test_exception_in_creating_rolls_back_and_removes_the_fresh_upload(): void
    {
        $this->actingAs($this->editor());

        Post::creating(function (): void {
            throw new RuntimeException('fail before persistence');
        });

        try {
            Livewire::test(CreatePost::class)
                ->fillForm([
                    ...$this->validPostInput(),
                    'featured_image_path' => UploadedFile::fake()->image('cover.jpg'),
                ])
                ->call('create');

            $this->fail('Expected the forced failure.');
        } catch (RuntimeException $exception) {
            // The ORIGINAL write exception must surface unmasked.
            $this->assertSame('fail before persistence', $exception->getMessage());
        }

        $this->assertSame(0, Post::query()->count());
        $this->assertSame([], Storage::disk($this->disk)->allFiles());
    }

    public function test_exception_in_created_rolls_back_and_removes_the_fresh_upload(): void
    {
        // The autocommit hazard: `created` fires AFTER the INSERT statement.
        // The page-scoped transaction must roll the row back with it.
        $this->actingAs($this->editor());

        Post::created(function (): void {
            throw new RuntimeException('fail after the INSERT');
        });

        try {
            Livewire::test(CreatePost::class)
                ->fillForm([
                    ...$this->validPostInput(),
                    'featured_image_path' => UploadedFile::fake()->image('cover.jpg'),
                ])
                ->call('create');

            $this->fail('Expected the forced failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('fail after the INSERT', $exception->getMessage());
        }

        $this->assertSame(0, Post::query()->count());
        $this->assertSame([], Storage::disk($this->disk)->allFiles());
    }

    public function test_exception_in_updating_preserves_committed_state_and_removes_the_fresh_upload(): void
    {
        Storage::disk($this->disk)->put('blogs/old.jpg', 'old');

        $post = Post::factory()->create(['featured_image_path' => 'blogs/old.jpg']);

        $this->actingAs($this->editor());

        Post::updating(function (): void {
            throw new RuntimeException('fail before persistence');
        });

        try {
            Livewire::test(EditPost::class, ['record' => $post->getKey()])
                // Array form REPLACES the hydrated state — a bare file would
                // be appended after the existing path and single-file
                // dehydration would keep the old value.
                ->fillForm(['featured_image_path' => [UploadedFile::fake()->image('replacement.jpg')]])
                ->call('save');

            $this->fail('Expected the forced failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('fail before persistence', $exception->getMessage());
        }

        $this->assertSame('blogs/old.jpg', $post->fresh()->featured_image_path);
        $this->assertSame(['blogs/old.jpg'], Storage::disk($this->disk)->allFiles());
    }

    public function test_exception_in_updated_preserves_committed_state_and_removes_the_fresh_upload(): void
    {
        // `updated` fires AFTER the UPDATE statement — and it is also the
        // event the model uses to queue old-file deletion via afterCommit.
        // Rollback must undo the UPDATE, discard that queued deletion (the
        // old file must survive), and remove only the fresh upload.
        Storage::disk($this->disk)->put('blogs/old.jpg', 'old');

        $post = Post::factory()->create(['featured_image_path' => 'blogs/old.jpg']);

        $this->actingAs($this->editor());

        Post::updated(function (): void {
            throw new RuntimeException('fail after the UPDATE');
        });

        try {
            Livewire::test(EditPost::class, ['record' => $post->getKey()])
                ->fillForm(['featured_image_path' => [UploadedFile::fake()->image('replacement.jpg')]])
                ->call('save');

            $this->fail('Expected the forced failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('fail after the UPDATE', $exception->getMessage());
        }

        $this->assertSame('blogs/old.jpg', $post->fresh()->featured_image_path);
        $this->assertSame(['blogs/old.jpg'], Storage::disk($this->disk)->allFiles());
    }

    public function test_committed_filament_replacement_deletes_the_old_file_and_keeps_the_new_one(): void
    {
        Storage::disk($this->disk)->put('blogs/old.jpg', 'old');

        $post = Post::factory()->create(['featured_image_path' => 'blogs/old.jpg']);

        $this->actingAs($this->editor());

        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm(['featured_image_path' => [UploadedFile::fake()->image('replacement.jpg')]])
            ->call('save')
            ->assertHasNoFormErrors();

        $new = $post->fresh()->featured_image_path;

        $this->assertNotSame('blogs/old.jpg', $new);
        Storage::disk($this->disk)->assertMissing('blogs/old.jpg');
        Storage::disk($this->disk)->assertExists($new);
        $this->assertSame([$new], Storage::disk($this->disk)->allFiles());
    }

    public function test_forged_existing_path_mixed_with_a_fresh_upload_cannot_delete_the_existing_file(): void
    {
        Storage::disk($this->disk)->put('blogs/old.jpg', 'old');
        // Referenced by NO post — the DB reference guard alone would not
        // protect it; only the ownership ledger can.
        Storage::disk($this->disk)->put('blogs/victim.jpg', 'victim');

        $post = Post::factory()->create(['featured_image_path' => 'blogs/old.jpg']);

        $this->actingAs($this->editor());

        Post::updating(function (): void {
            throw new RuntimeException('forced write failure');
        });

        try {
            Livewire::test(EditPost::class, ['record' => $post->getKey()])
                // The mixed shape a tampered request could submit: a real
                // fresh upload alongside an arbitrary existing path string.
                ->fillForm(['featured_image_path' => [
                    UploadedFile::fake()->image('replacement.jpg'),
                    'blogs/victim.jpg',
                ]])
                ->call('save');
        } catch (RuntimeException) {
            // Expected when the forged state survives validation; if
            // validation rejects it instead, nothing was stored at all —
            // either way the assertions below must hold.
        }

        Storage::disk($this->disk)->assertExists('blogs/victim.jpg');
        Storage::disk($this->disk)->assertExists('blogs/old.jpg');
        $this->assertSame('blogs/old.jpg', $post->fresh()->featured_image_path);
        $this->assertCount(2, Storage::disk($this->disk)->allFiles());
    }

    public function test_unrelated_unreferenced_file_on_the_same_disk_survives_cleanup(): void
    {
        Storage::disk($this->disk)->put('blogs/unrelated.jpg', 'bytes');

        $this->actingAs($this->editor());

        Post::creating(function (): void {
            throw new RuntimeException('forced write failure');
        });

        try {
            Livewire::test(CreatePost::class)
                ->fillForm([
                    ...$this->validPostInput(),
                    'featured_image_path' => UploadedFile::fake()->image('cover.jpg'),
                ])
                ->call('create');

            $this->fail('Expected the forced write failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced write failure', $exception->getMessage());
        }

        $this->assertSame(0, Post::query()->count());
        // Only the request's own stored file was removed.
        $this->assertSame(['blogs/unrelated.jpg'], Storage::disk($this->disk)->allFiles());
    }
}
