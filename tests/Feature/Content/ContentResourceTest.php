<?php

namespace Tests\Feature\Content;

use App\Enums\PostStatus;
use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Filament\Resources\TeamMemberResource\Pages\CreateTeamMember;
use App\Models\Category;
use App\Models\Post;
use App\Models\TeamMember;
use Livewire\Livewire;
use Tests\Feature\Auth\AdminTestCase;

/**
 * Content resources over real Livewire requests: translated JSON fields,
 * slug rules, the published-requires-date guard, and permission-gated page
 * access per role.
 */
class ContentResourceTest extends AdminTestCase
{
    public function test_editor_can_access_content_pages_but_not_forms_or_submissions(): void
    {
        $this->actingAs($this->editor());

        $this->get($this->adminHost.'/posts')->assertOk();
        $this->get($this->adminHost.'/categories')->assertOk();
        $this->get($this->adminHost.'/team-members')->assertOk();
        $this->get($this->adminHost.'/forms')->assertForbidden();
        $this->get($this->adminHost.'/form-submissions')->assertForbidden();
    }

    public function test_admin_can_access_forms_and_submissions(): void
    {
        $this->actingAs($this->admin());

        $this->get($this->adminHost.'/forms')->assertOk();
        $this->get($this->adminHost.'/form-submissions')->assertOk();
    }

    public function test_category_can_be_created_with_translations(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => ['en' => 'News', 'ar' => 'أخبار'],
                'slug' => 'news',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::query()->where('slug', 'news')->sole();

        $this->assertSame('News', $category->translate('name', 'en'));
        $this->assertSame('أخبار', $category->translate('name', 'ar'));
    }

    public function test_missing_arabic_translation_falls_back_to_default_locale(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => ['en' => 'Only English'],
                'slug' => 'only-english',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::query()->where('slug', 'only-english')->sole();

        $this->assertSame('Only English', $category->translate('name', 'ar'));
    }

    public function test_invalid_slug_is_rejected(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => ['en' => 'Bad Slug'],
                'slug' => 'Bad Slug!',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_post_published_without_date_is_rejected_server_side(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => ['en' => 'A Post'],
                'slug' => 'a-post',
                'body' => ['en' => 'Body text.'],
                'status' => PostStatus::Published->value,
                'published_at' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['published_at']);

        $this->assertSame(0, Post::query()->count());
    }

    public function test_post_can_be_published_with_date_and_scope_sees_it(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => ['en' => 'Launch', 'ar' => 'انطلاقة'],
                'slug' => 'launch',
                'body' => ['en' => 'Body.', 'ar' => 'المحتوى.'],
                'status' => PostStatus::Published->value,
                'published_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Post::query()->published()->count());
    }

    public function test_scheduled_posts_are_not_publicly_visible_yet(): void
    {
        Post::factory()->scheduled()->create();
        Post::factory()->create();

        $this->assertSame(0, Post::query()->published()->count());
    }

    public function test_deleting_a_category_keeps_its_posts(): void
    {
        $category = Category::factory()->create();
        $post = Post::factory()->create(['category_id' => $category->getKey()]);

        $this->actingAs($this->editor());

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->callAction('delete');

        $this->assertNull(Category::query()->find($category->getKey()));
        $this->assertNull($post->fresh()->category_id);
    }

    public function test_team_member_can_be_created_and_hidden(): void
    {
        $this->actingAs($this->editor());

        Livewire::test(CreateTeamMember::class)
            ->fillForm([
                'name' => ['en' => 'Jane Smith', 'ar' => 'جين سميث'],
                'position' => ['en' => 'Engineer', 'ar' => 'مهندسة'],
                'sort_order' => 5,
                'is_visible' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = TeamMember::query()->sole();

        $this->assertSame('Jane Smith', $member->translate('name', 'en'));
        $this->assertFalse($member->is_visible);
        $this->assertSame(5, $member->sort_order);
        $this->assertSame(0, TeamMember::query()->visible()->count());
    }
}
