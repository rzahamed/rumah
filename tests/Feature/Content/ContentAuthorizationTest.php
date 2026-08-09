<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\FormSubmissionResource;
use App\Models\Category;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Post;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\Feature\Auth\AdminTestCase;

/**
 * The content permission matrix: editors publish content but never touch
 * form definitions or collected submissions; admins hold everything;
 * inactive actors are denied despite role permissions; submission policy
 * denials hold for every non-bypassing actor.
 */
class ContentAuthorizationTest extends AdminTestCase
{
    public function test_editor_holds_content_permissions(): void
    {
        $editor = $this->editor();

        foreach (['posts', 'categories', 'team'] as $prefix) {
            foreach (['view', 'create', 'update', 'delete'] as $verb) {
                $this->assertTrue(
                    $editor->can("{$prefix}.{$verb}"),
                    "editor should hold {$prefix}.{$verb}",
                );
            }
        }
    }

    public function test_editor_lacks_form_and_submission_permissions(): void
    {
        $editor = $this->editor();

        foreach (['forms.view', 'forms.create', 'forms.update', 'forms.delete', 'submissions.view', 'submissions.delete'] as $permission) {
            $this->assertFalse($editor->can($permission), "editor must not hold {$permission}");
        }
    }

    public function test_admin_holds_all_content_and_form_permissions(): void
    {
        $admin = $this->admin();

        foreach (['posts.delete', 'categories.update', 'team.create', 'forms.update', 'submissions.view', 'submissions.delete'] as $permission) {
            $this->assertTrue($admin->can($permission), "admin should hold {$permission}");
        }
    }

    public function test_content_policies_map_to_permissions(): void
    {
        $editor = $this->editor();
        $post = Post::factory()->create();

        $this->assertTrue(Gate::forUser($editor)->allows('viewAny', Post::class));
        $this->assertTrue(Gate::forUser($editor)->allows('update', $post));
        $this->assertTrue(Gate::forUser($editor)->allows('viewAny', Category::class));
        $this->assertTrue(Gate::forUser($editor)->allows('viewAny', TeamMember::class));
        $this->assertTrue(Gate::forUser($editor)->denies('viewAny', Form::class));
        $this->assertTrue(Gate::forUser($editor)->denies('viewAny', FormSubmission::class));
    }

    public function test_inactive_editor_is_denied_despite_role_permissions(): void
    {
        $user = User::factory()->inactive()->create();
        $user->assignRole('editor');

        $this->assertTrue(Gate::forUser($user)->denies('viewAny', Post::class));
        $this->assertTrue(Gate::forUser($user)->denies('create', Category::class));
    }

    public function test_submission_policy_denies_create_update_and_bulk_delete_for_admin(): void
    {
        $admin = $this->admin();
        $submission = FormSubmission::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->denies('create', FormSubmission::class));
        $this->assertTrue(Gate::forUser($admin)->denies('update', $submission));
        $this->assertTrue(Gate::forUser($admin)->denies('deleteAny', FormSubmission::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $submission));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $submission));
    }

    public function test_structural_restrictions_bind_even_super_admins(): void
    {
        // Gate::before would grant a super admin anything — these resource
        // overrides never consult the Gate at all.
        $this->actingAs($this->superAdmin());

        $this->assertFalse(FormSubmissionResource::canCreate());
        $this->assertFalse(FormSubmissionResource::canEdit(FormSubmission::factory()->create()));
        $this->assertFalse(FormSubmissionResource::canDeleteAny());
    }
}
