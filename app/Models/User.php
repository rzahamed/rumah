<?php

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'invitation_token_hash'])]
class User extends Authenticatable implements FilamentUser, HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
        ];
    }

    /**
     * Panel authorization contract, enforced by Filament's Authenticate
     * middleware on every panel request and during login/password-reset
     * flows — not by hiding UI. Requires BOTH an active account AND the
     * explicit `access_admin` permission (super admins satisfy it through
     * the active-super-admin Gate::before override), so a future active
     * public-site account never gains CMS access implicitly.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->status === UserStatus::Active
            && $this->can('access_admin');
    }

    /**
     * Locale for anything addressed to this user.
     *
     * Laravel reads this automatically when sending notifications, which
     * matters most for QUEUED mail: without it, __() resolves on the worker
     * against the app default — neither the sender's locale nor the
     * recipient's. A new invitee has no stored preference yet, so the
     * configured default applies; an unsupported stored value is ignored
     * rather than trusted.
     */
    public function preferredLocale(): string
    {
        $default = (string) config('platform.default_locale', 'en');
        $preferred = $this->preferred_admin_locale;

        return is_string($preferred)
            && in_array($preferred, config('platform.supported_locales', []), true)
                ? $preferred
                : $default;
    }

    /**
     * True when this user is the only remaining ACTIVE super administrator.
     * Delete, deactivate, and role-removal paths must refuse to proceed when
     * this holds, or the panel is left without full administrative access.
     */
    public function isLastActiveSuperAdmin(): bool
    {
        return $this->status === UserStatus::Active
            && $this->hasRole('super_admin')
            && static::role('super_admin')
                ->where('status', UserStatus::Active)
                ->whereKeyNot($this->getKey())
                ->doesntExist();
    }

    /**
     * THE single definition of which users an actor may see.
     *
     * Super administrators are invisible to everyone who is not an active
     * super administrator. This scope is the shared implementation behind
     * both UserResource::getEloquentQuery() and notification-recipient
     * selection, so those two can never drift apart — a second hand-written
     * copy of the rule is exactly how one surface ends up leaking.
     *
     * @param  Builder<User>  $query
     */
    public function scopeVisibleToActor(Builder $query, ?User $actor): Builder
    {
        $actorIsActiveSuperAdmin = $actor instanceof self
            && $actor->status === UserStatus::Active
            && $actor->hasRole('super_admin');

        if ($actorIsActiveSuperAdmin) {
            return $query;
        }

        return $query->whereDoesntHave(
            'roles',
            fn (Builder $roles) => $roles
                ->where('name', 'super_admin')
                ->where('guard_name', config('auth.defaults.guard')),
        );
    }
}
