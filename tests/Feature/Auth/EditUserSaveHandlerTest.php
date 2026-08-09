<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;

/**
 * Direct tests of EditUser::handleRecordUpdate() with FORGED payloads —
 * independent of Filament field dehydration. The Livewire-level tests prove
 * hidden/disabled fields are not submitted; these prove the server-side
 * handler itself rejects hostile input even when it IS submitted.
 */
class EditUserSaveHandlerTest extends AdminTestCase
{
    private function updateDirectly(User $record, array $data): User
    {
        $method = new ReflectionMethod(EditUser::class, 'handleRecordUpdate');

        /** @var User */
        return $method->invoke(new EditUser, $record, $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function expectValidationErrorKeys(User $record, array $data): array
    {
        try {
            $this->updateDirectly($record, $data);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        $this->fail('Expected ValidationException was not thrown.');
    }

    public function test_forged_status_cannot_activate_an_invited_account(): void
    {
        $this->actingAs($this->admin());
        $invited = User::factory()->invited()->create();

        $errors = $this->expectValidationErrorKeys($invited, [
            'name' => $invited->name,
            'email' => $invited->email,
            'status' => UserStatus::Active->value,
        ]);

        $this->assertArrayHasKey('data.status', $errors);
        $this->assertSame(UserStatus::Invited, $invited->fresh()->status);
    }

    public function test_forged_status_cannot_set_invited_on_an_active_account(): void
    {
        $this->actingAs($this->admin());
        $target = User::factory()->create();

        $errors = $this->expectValidationErrorKeys($target, [
            'status' => UserStatus::Invited->value,
        ]);

        $this->assertArrayHasKey('data.status', $errors);
        $this->assertSame(UserStatus::Active, $target->fresh()->status);
    }

    public function test_forged_invalid_status_values_are_rejected(): void
    {
        $this->actingAs($this->admin());
        $target = User::factory()->create();

        $errors = $this->expectValidationErrorKeys($target, ['status' => 'nonsense']);
        $this->assertArrayHasKey('data.status', $errors);

        $errors = $this->expectValidationErrorKeys($target, ['status' => ['active']]);
        $this->assertArrayHasKey('data.status', $errors);
    }

    public function test_forged_roles_without_manage_roles_are_rejected_server_side(): void
    {
        $limited = User::factory()->create();
        $limited->givePermissionTo(['access_admin', 'users.view', 'users.update']);
        $this->actingAs($limited);

        $target = User::factory()->create();
        $target->assignRole('editor');

        $errors = $this->expectValidationErrorKeys($target, [
            'roles' => ['admin'],
        ]);

        $this->assertArrayHasKey('data.roles', $errors);
        $this->assertSame(['editor'], $target->fresh()->getRoleNames()->all());
    }

    public function test_forged_malformed_role_values_are_rejected(): void
    {
        $this->actingAs($this->admin());
        $target = User::factory()->create();

        foreach ([['roles' => 'admin'], ['roles' => ['']], ['roles' => [42]]] as $payload) {
            $errors = $this->expectValidationErrorKeys($target, $payload);
            $this->assertArrayHasKey('data.roles', $errors);
        }

        $this->assertSame([], $target->fresh()->getRoleNames()->all());
    }

    public function test_forged_unknown_role_names_are_rejected(): void
    {
        $this->actingAs($this->admin());
        $target = User::factory()->create();

        $errors = $this->expectValidationErrorKeys($target, [
            'roles' => ['nonexistent_role'],
        ]);

        $this->assertArrayHasKey('data.roles', $errors);
        $this->assertSame([], $target->fresh()->getRoleNames()->all());
    }

    public function test_forged_deactivation_of_last_active_super_admin_is_rejected(): void
    {
        $lastSuper = $this->superAdmin();
        $this->actingAs($lastSuper);

        $errors = $this->expectValidationErrorKeys($lastSuper, [
            'status' => UserStatus::Inactive->value,
        ]);

        $this->assertArrayHasKey('data.status', $errors);
        $this->assertSame(UserStatus::Active, $lastSuper->fresh()->status);
    }

    public function test_forged_super_admin_role_removal_from_last_active_one_is_rejected(): void
    {
        $lastSuper = $this->superAdmin();
        $this->actingAs($lastSuper);

        $errors = $this->expectValidationErrorKeys($lastSuper, [
            'roles' => ['admin'],
        ]);

        $this->assertArrayHasKey('data.roles', $errors);
        $this->assertTrue($lastSuper->fresh()->hasRole('super_admin'));
    }

    public function test_valid_direct_update_applies_name_status_and_roles_atomically(): void
    {
        $this->actingAs($this->admin());
        $target = User::factory()->create();

        $updated = $this->updateDirectly($target, [
            'name' => 'Fully Updated',
            'email' => $target->email,
            'status' => UserStatus::Inactive->value,
            'roles' => ['editor'],
        ]);

        $this->assertSame('Fully Updated', $updated->name);
        $this->assertSame(UserStatus::Inactive, $updated->status);
        $this->assertSame(['editor'], $updated->getRoleNames()->all());
    }
}
