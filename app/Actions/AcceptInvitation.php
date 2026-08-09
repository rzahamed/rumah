<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use SensitiveParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Activates an invited account from its one-time plaintext token. Every
 * failure mode — unknown token, expired invitation, already-used (cleared)
 * token, wrong status — is answered with the SAME 404, so no response ever
 * reveals whether an account exists. Lookup is by SHA-256 hash only; the
 * plaintext is never persisted.
 *
 * accept() runs entirely inside one PostgreSQL transaction: the row is
 * locked FOR UPDATE, then token hash, invited status, and expiry are
 * re-validated on the locked row, then the confirmed password is validated
 * against Password::defaults(), and only then does the account activate —
 * password set, email verified, both invitation fields cleared (making
 * reuse structurally impossible), remember token rotated.
 */
class AcceptInvitation
{
    /**
     * Resolve the pending, unexpired invitation for a plaintext token — the
     * GET form page needs the invitee to display. Non-locking; every failure
     * mode funnels into the shared indistinguishable 404.
     */
    public function findInvitedUser(#[SensitiveParameter] string $plaintextToken): User
    {
        $user = User::query()
            ->where('invitation_token_hash', $this->hashToken($plaintextToken))
            ->where('status', UserStatus::Invited)
            ->where('invitation_expires_at', '>', now())
            ->first();

        if ($user === null) {
            throw $this->invitationNotFound();
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $credentials  Raw request input holding
     *                                             password and password_confirmation.
     */
    public function accept(#[SensitiveParameter] string $plaintextToken, #[SensitiveParameter] array $credentials): User
    {
        $tokenHash = $this->hashToken($plaintextToken);

        return DB::transaction(function () use ($tokenHash, $credentials): User {
            // Locate and lock by hash only, then re-validate EVERY pending
            // criterion on the locked row: a concurrent acceptance or resend
            // that committed first changed these fields, and this read —
            // which waited on the row lock — must see and reject that.
            $user = User::query()
                ->where('invitation_token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($user === null
                || ! hash_equals((string) $user->invitation_token_hash, $tokenHash)
                || $user->status !== UserStatus::Invited
                || $user->invitation_expires_at === null
                || ! $user->invitation_expires_at->isFuture()) {
                throw $this->invitationNotFound();
            }

            // Token resolution precedes password validation by design: an
            // invalid link 404s no matter what was submitted. A validation
            // failure here rolls back the still-mutation-free transaction.
            $validated = Validator::make($credentials, [
                'password' => ['required', 'string', 'confirmed', Password::defaults()],
            ])->validate();

            $user->forceFill([
                'password' => $validated['password'], // 'hashed' cast
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'invitation_token_hash' => null,
                'invitation_expires_at' => null,
                // Invalidate any remembered session predating activation.
                'remember_token' => Str::random(60),
            ])->save();

            return $user;
        });
    }

    private function hashToken(#[SensitiveParameter] string $plaintextToken): string
    {
        return hash('sha256', $plaintextToken);
    }

    /**
     * The single 404 every invalid-token path shares — missing, expired,
     * used, or wrong status must stay indistinguishable to the caller.
     */
    private function invitationNotFound(): NotFoundHttpException
    {
        return new NotFoundHttpException;
    }
}
