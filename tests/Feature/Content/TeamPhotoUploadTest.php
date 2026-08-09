<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\TeamMemberResource\Pages\CreateTeamMember;
use App\Models\TeamMember;
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
 * Photo lifecycle guarantees on the env-driven media disk. Deliberately
 * runs under DatabaseMigrations — NOT RefreshDatabase — so every save
 * COMMITS for real and DB::afterCommit fires exactly as in production;
 * the rollback tests therefore prove the contract without any
 * test-transaction indirection.
 */
class TeamPhotoUploadTest extends TestCase
{
    use DatabaseMigrations;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->disk = (string) config('platform.media_disk');

        Storage::fake($this->disk);
    }

    private function editor(): User
    {
        $user = User::factory()->create();

        $user->assignRole('editor');

        return $user;
    }

    public function test_photo_is_stored_under_team_with_public_visibility(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreateTeamMember::class)
            ->fillForm([
                'name' => ['en' => 'With Photo'],
                'position' => ['en' => 'Engineer'],
                'sort_order' => 0,
                'photo_path' => UploadedFile::fake()->image('portrait.jpg'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = TeamMember::query()->sole();

        $this->assertNotNull($member->photo_path);
        $this->assertStringStartsWith('team/', $member->photo_path);

        Storage::disk($this->disk)->assertExists($member->photo_path);
        $this->assertSame('public', Storage::disk($this->disk)->getVisibility($member->photo_path));
    }

    public function test_photo_is_optional(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreateTeamMember::class)
            ->fillForm([
                'name' => ['en' => 'No Photo'],
                'position' => ['en' => 'Engineer'],
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(TeamMember::query()->sole()->photo_path);
    }

    public function test_committed_replacement_deletes_the_old_file_and_keeps_the_new_one(): void
    {
        Storage::disk($this->disk)->put('team/old.jpg', 'old');
        Storage::disk($this->disk)->put('team/new.jpg', 'new');

        $member = TeamMember::factory()->create(['photo_path' => 'team/old.jpg']);

        $member->update(['photo_path' => 'team/new.jpg']);

        Storage::disk($this->disk)->assertMissing('team/old.jpg');
        Storage::disk($this->disk)->assertExists('team/new.jpg');
        $this->assertSame('team/new.jpg', $member->fresh()->photo_path);
    }

    public function test_committed_deletion_deletes_the_photo(): void
    {
        Storage::disk($this->disk)->put('team/gone.jpg', 'bytes');

        $member = TeamMember::factory()->create(['photo_path' => 'team/gone.jpg']);

        $member->delete();

        Storage::disk($this->disk)->assertMissing('team/gone.jpg');
    }

    public function test_rolled_back_replacement_preserves_the_old_file_and_database_value(): void
    {
        Storage::disk($this->disk)->put('team/old.jpg', 'old');
        Storage::disk($this->disk)->put('team/new.jpg', 'new');

        $member = TeamMember::factory()->create(['photo_path' => 'team/old.jpg']);

        try {
            DB::transaction(function () use ($member): void {
                $member->update(['photo_path' => 'team/new.jpg']);

                throw new RuntimeException('force rollback');
            });

            $this->fail('Expected the forced rollback exception.');
        } catch (RuntimeException) {
            // Expected.
        }

        Storage::disk($this->disk)->assertExists('team/old.jpg');
        $this->assertSame('team/old.jpg', $member->fresh()->photo_path);
    }

    public function test_rolled_back_deletion_preserves_the_row_and_file(): void
    {
        Storage::disk($this->disk)->put('team/kept.jpg', 'bytes');

        $member = TeamMember::factory()->create(['photo_path' => 'team/kept.jpg']);

        try {
            DB::transaction(function () use ($member): void {
                $member->delete();

                throw new RuntimeException('force rollback');
            });

            $this->fail('Expected the forced rollback exception.');
        } catch (RuntimeException) {
            // Expected.
        }

        Storage::disk($this->disk)->assertExists('team/kept.jpg');
        $this->assertNotNull(TeamMember::query()->find($member->getKey()));
    }
}
