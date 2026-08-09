<?php

namespace App\Console\Commands;

use App\Actions\CreateSuperAdmin as CreateSuperAdminAction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use LogicException;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:create-super-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactively create the first super administrator. Refuses to run when one already exists.';

    /**
     * Execute the console command.
     */
    public function handle(CreateSuperAdminAction $createSuperAdmin): int
    {
        $role = Role::findOrCreate('super_admin', 'web');

        if (User::role($role)->exists()) {
            $this->components->error(
                'A super administrator already exists. Invite additional administrators from the panel instead.'
            );

            return self::FAILURE;
        }

        $name = trim(text(label: 'Name', required: true));

        $email = mb_strtolower(trim(text(
            label: 'Email',
            required: true,
            validate: function (string $value): ?string {
                $validator = Validator::make(
                    ['email' => mb_strtolower(trim($value))],
                    ['email' => ['required', 'email', 'unique:users,email']],
                );

                return $validator->fails() ? $validator->errors()->first('email') : null;
            },
        )));

        $passwordInput = password(label: 'Password', required: true);
        $passwordConfirmation = password(label: 'Confirm password', required: true);

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $passwordInput,
                'password_confirmation' => $passwordConfirmation,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        try {
            $user = $createSuperAdmin($name, $email, $passwordInput, $role);
        } catch (LogicException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Super administrator [%s] created.', $user->email));

        return self::SUCCESS;
    }
}
