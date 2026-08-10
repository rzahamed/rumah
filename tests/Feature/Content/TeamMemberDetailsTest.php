<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\TeamMemberResource\Pages\CreateTeamMember;
use App\Filament\Resources\TeamMemberResource\Pages\EditTeamMember;
use App\Models\TeamMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * TeamMember detail fields (email, highlights, credentials, expertise,
 * licence image): persistence, validation, locale-resolution helpers, and
 * the commit-safe media lifecycle with its committed-state reference guard.
 * Runs under DatabaseMigrations so every save COMMITS for real and
 * DB::afterCommit fires exactly as in production.
 */
class TeamMemberDetailsTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function validMemberInput(): array
    {
        return [
            'name' => ['en' => 'Test Member'],
            'position' => ['en' => 'Manager'],
            'sort_order' => 0,
        ];
    }

    public function test_details_are_persisted_through_the_create_page(): void
    {
        $this->actingAs($this->editor());

        $highlights = [['en' => 'Strategic Planning', 'ar' => 'التخطيط الاستراتيجي']];
        $credentials = [[
            'title' => ['en' => 'BSc', 'ar' => 'بكالوريوس علوم'],
            'institution' => ['en' => 'Example University', 'ar' => 'جامعة المثال'],
            'description' => ['en' => 'Graduated with honours.', 'ar' => 'تخرج بمرتبة الشرف.'],
        ]];
        $expertise = [['en' => 'Project Management', 'ar' => 'إدارة المشاريع']];

        // Repeater::fake() disables uuid generation so plain arrays fill
        // repeaters; the static state MUST be undone even on failure.
        $undoRepeaterFake = Repeater::fake();

        try {
            Livewire::test(CreateTeamMember::class)
                ->fillForm([
                    ...$this->validMemberInput(),
                    'email' => 'member@example.com',
                    'highlights' => $highlights,
                    'credentials' => $credentials,
                    'expertise' => $expertise,
                ])
                ->call('create')
                ->assertHasNoFormErrors();
        } finally {
            $undoRepeaterFake();
        }

        $member = TeamMember::query()->sole();

        $this->assertSame('member@example.com', $member->email);
        $this->assertEquals($highlights, $member->highlights);
        $this->assertEquals($credentials, $member->credentials);
        $this->assertEquals($expertise, $member->expertise);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreateTeamMember::class)
            ->fillForm([
                ...$this->validMemberInput(),
                'email' => 'not-an-email',
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);

        $this->assertSame(0, TeamMember::query()->count());
    }

    public function test_licence_image_is_stored_under_team_licences_with_public_visibility(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreateTeamMember::class)
            ->fillForm([
                ...$this->validMemberInput(),
                'licence_image_path' => UploadedFile::fake()->image('licence.jpg'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = TeamMember::query()->sole();

        $this->assertNotNull($member->licence_image_path);
        $this->assertStringStartsWith('team/licences/', $member->licence_image_path);

        Storage::disk($this->disk)->assertExists($member->licence_image_path);
        $this->assertSame('public', Storage::disk($this->disk)->getVisibility($member->licence_image_path));
    }

    public function test_licence_image_is_optional(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreateTeamMember::class)
            ->fillForm($this->validMemberInput())
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(TeamMember::query()->sole()->licence_image_path);
    }

    public function test_non_image_licence_uploads_are_rejected(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreateTeamMember::class)
            ->fillForm([
                ...$this->validMemberInput(),
                'licence_image_path' => UploadedFile::fake()->create('licence.pdf', 128, 'application/pdf'),
            ])
            ->call('create')
            ->assertHasFormErrors(['licence_image_path']);

        $this->assertSame(0, TeamMember::query()->count());
    }

    public function test_committed_licence_replacement_deletes_the_old_file_and_keeps_the_new_one(): void
    {
        Storage::disk($this->disk)->put('team/licences/old.jpg', 'old');
        Storage::disk($this->disk)->put('team/licences/new.jpg', 'new');

        $member = TeamMember::factory()->create(['licence_image_path' => 'team/licences/old.jpg']);

        $member->update(['licence_image_path' => 'team/licences/new.jpg']);

        Storage::disk($this->disk)->assertMissing('team/licences/old.jpg');
        Storage::disk($this->disk)->assertExists('team/licences/new.jpg');
        $this->assertSame('team/licences/new.jpg', $member->fresh()->licence_image_path);
    }

    public function test_committed_member_deletion_deletes_photo_and_licence(): void
    {
        Storage::disk($this->disk)->put('team/photo.jpg', 'photo');
        Storage::disk($this->disk)->put('team/licences/licence.jpg', 'licence');

        $member = TeamMember::factory()->create([
            'photo_path' => 'team/photo.jpg',
            'licence_image_path' => 'team/licences/licence.jpg',
        ]);

        $member->delete();

        Storage::disk($this->disk)->assertMissing('team/photo.jpg');
        Storage::disk($this->disk)->assertMissing('team/licences/licence.jpg');
    }

    public function test_rolled_back_licence_replacement_preserves_the_old_file_and_database_value(): void
    {
        Storage::disk($this->disk)->put('team/licences/old.jpg', 'old');
        Storage::disk($this->disk)->put('team/licences/new.jpg', 'new');

        $member = TeamMember::factory()->create(['licence_image_path' => 'team/licences/old.jpg']);

        try {
            DB::transaction(function () use ($member): void {
                $member->update(['licence_image_path' => 'team/licences/new.jpg']);

                throw new RuntimeException('force rollback');
            });

            $this->fail('Expected the forced rollback exception.');
        } catch (RuntimeException) {
            // Expected.
        }

        Storage::disk($this->disk)->assertExists('team/licences/old.jpg');
        Storage::disk($this->disk)->assertExists('team/licences/new.jpg');
        $this->assertSame('team/licences/old.jpg', $member->fresh()->licence_image_path);
    }

    public function test_replacement_preserves_a_path_still_referenced_by_another_member(): void
    {
        Storage::disk($this->disk)->put('team/shared.jpg', 'shared');
        Storage::disk($this->disk)->put('team/solo.jpg', 'solo');

        $memberA = TeamMember::factory()->create(['photo_path' => 'team/shared.jpg']);
        TeamMember::factory()->create(['photo_path' => 'team/shared.jpg']);

        $memberA->update(['photo_path' => 'team/solo.jpg']);

        // The committed-state reference guard: member B still points at the
        // shared path, so the replacement must not delete it.
        Storage::disk($this->disk)->assertExists('team/shared.jpg');
        Storage::disk($this->disk)->assertExists('team/solo.jpg');
    }

    public function test_deletion_preserves_a_path_still_referenced_by_another_member(): void
    {
        Storage::disk($this->disk)->put('team/shared.jpg', 'shared');

        $memberA = TeamMember::factory()->create(['photo_path' => 'team/shared.jpg']);
        TeamMember::factory()->create(['photo_path' => 'team/shared.jpg']);

        $memberA->delete();

        Storage::disk($this->disk)->assertExists('team/shared.jpg');
    }

    public function test_swapping_a_path_between_attributes_preserves_the_file(): void
    {
        Storage::disk($this->disk)->put('team/moved.jpg', 'bytes');

        $member = TeamMember::factory()->create(['photo_path' => 'team/moved.jpg']);

        // The same committed UPDATE moves the path from photo to licence —
        // the old-photo deletion must see the licence reference and skip.
        $member->update([
            'photo_path' => null,
            'licence_image_path' => 'team/moved.jpg',
        ]);

        Storage::disk($this->disk)->assertExists('team/moved.jpg');
        $this->assertSame('team/moved.jpg', $member->fresh()->licence_image_path);
    }

    public function test_exception_in_creating_removes_all_fresh_uploads(): void
    {
        $this->actingAs($this->editor());

        TeamMember::creating(function (): void {
            throw new RuntimeException('forced write failure');
        });

        try {
            Livewire::test(CreateTeamMember::class)
                ->fillForm([
                    ...$this->validMemberInput(),
                    'photo_path' => UploadedFile::fake()->image('portrait.jpg'),
                    'licence_image_path' => UploadedFile::fake()->image('licence.jpg'),
                ])
                ->call('create');

            $this->fail('Expected the forced write failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced write failure', $exception->getMessage());
        }

        $this->assertSame(0, TeamMember::query()->count());
        $this->assertSame([], Storage::disk($this->disk)->allFiles());
    }

    public function test_exception_in_updated_preserves_committed_state_and_removes_fresh_uploads(): void
    {
        // `updated` fires AFTER the UPDATE statement — the page-scoped
        // transaction must roll the row back, discard the queued old-file
        // deletions, and the ledger must remove BOTH fresh uploads.
        Storage::disk($this->disk)->put('team/old-photo.jpg', 'photo');
        Storage::disk($this->disk)->put('team/licences/old-licence.jpg', 'licence');

        $member = TeamMember::factory()->create([
            'photo_path' => 'team/old-photo.jpg',
            'licence_image_path' => 'team/licences/old-licence.jpg',
        ]);

        $this->actingAs($this->editor());

        TeamMember::updated(function (): void {
            throw new RuntimeException('fail after the UPDATE');
        });

        try {
            Livewire::test(EditTeamMember::class, ['record' => $member->getKey()])
                // Array form REPLACES hydrated upload state (bare files
                // would append after the existing paths).
                ->fillForm([
                    'photo_path' => [UploadedFile::fake()->image('new-portrait.jpg')],
                    'licence_image_path' => [UploadedFile::fake()->image('new-licence.jpg')],
                ])
                ->call('save');

            $this->fail('Expected the forced failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('fail after the UPDATE', $exception->getMessage());
        }

        $fresh = $member->fresh();

        $this->assertSame('team/old-photo.jpg', $fresh->photo_path);
        $this->assertSame('team/licences/old-licence.jpg', $fresh->licence_image_path);
        $this->assertEqualsCanonicalizing(
            ['team/old-photo.jpg', 'team/licences/old-licence.jpg'],
            Storage::disk($this->disk)->allFiles(),
        );
    }

    public function test_localized_highlights_and_expertise_resolve_locales_and_drop_invalid_items(): void
    {
        $member = new TeamMember([
            'highlights' => [
                ['en' => 'Strategic Planning', 'ar' => 'التخطيط الاستراتيجي'],
                ['en' => 'English only'],
                'junk',
            ],
            'expertise' => null,
        ]);

        app()->setLocale('ar');

        // Arabic where present, default-locale fallback otherwise; the
        // malformed item is dropped.
        $this->assertSame(['التخطيط الاستراتيجي', 'English only'], $member->localizedHighlights());
        $this->assertSame([], $member->localizedExpertise());

        app()->setLocale('en');

        $this->assertSame(['Strategic Planning', 'English only'], $member->localizedHighlights());
    }

    public function test_localized_credentials_resolve_locales_and_drop_untitled_items(): void
    {
        $member = new TeamMember([
            'credentials' => [
                [
                    'title' => ['en' => 'BSc'],
                    'institution' => ['en' => 'Example University'],
                    // description absent
                ],
                ['institution' => ['en' => 'No title — dropped']],
                'junk',
            ],
        ]);

        app()->setLocale('en');

        $this->assertSame([
            [
                'title' => 'BSc',
                'institution' => 'Example University',
                'description' => null,
            ],
        ], $member->localizedCredentials());
    }

    public function test_factory_with_details_produces_valid_structures(): void
    {
        $member = TeamMember::factory()->withDetails()->create();

        app()->setLocale('en');

        $this->assertNotFalse(filter_var($member->email, FILTER_VALIDATE_EMAIL));
        $this->assertCount(2, $member->localizedHighlights());
        $this->assertCount(2, $member->localizedExpertise());
        $this->assertCount(1, $member->localizedCredentials());
        $this->assertNotNull($member->localizedCredentials()[0]['institution']);
    }
}
