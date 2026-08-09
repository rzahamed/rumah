<?php

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'invitation_token_hash'])]
class User extends Authenticatable implements FilamentUser
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
}
