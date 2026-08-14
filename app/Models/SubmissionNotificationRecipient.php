<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An internal user configured to receive new-submission emails.
 *
 * The table carries a UNIQUE user_id, so duplicate recipients — and
 * therefore duplicate emails — are impossible at the database level rather
 * than only in application code.
 */
#[Fillable(['user_id'])]
class SubmissionNotificationRecipient extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accounts that may receive collected data at all, independent of who is
     * looking: ACTIVE, and holding the panel-access permission for the
     * configured guard. Spatie's permission scope resolves through roles and
     * is guard-aware, so a user whose only role belongs to another guard
     * does not match.
     *
     * @return Builder<User>
     */
    public static function deliverableQuery(): Builder
    {
        $guard = config('auth.defaults.guard');

        return User::query()
            ->where('status', UserStatus::Active)
            ->where(function (Builder $query) use ($guard): void {
                // Panel access, expressed as a QUERY. Two sources, both
                // guard-scoped, because canAccessPanel() has two:
                $query
                    // …a directly assigned permission, or…
                    ->whereHas('permissions', fn (Builder $permissions) => $permissions
                        ->where('name', 'access_admin')
                        ->where('guard_name', $guard))
                    // …a role that grants it. super_admin is named
                    // explicitly: it holds NO database permissions at all
                    // and receives access_admin from the Gate::before
                    // runtime override, which no SQL scope can observe.
                    // Omitting it silently made super admins ineligible.
                    ->orWhereHas('roles', fn (Builder $roles) => $roles
                        ->where('guard_name', $guard)
                        ->where(fn (Builder $inner) => $inner
                            ->where('name', 'super_admin')
                            ->orWhereHas('permissions', fn (Builder $permissions) => $permissions
                                ->where('name', 'access_admin')
                                ->where('guard_name', $guard))));
            });
    }

    /**
     * Users an ACTOR may choose from: the deliverable set, narrowed by the
     * shared visibility rule so a non-super actor can never see, search,
     * count or preload a super administrator.
     *
     * Nullable actor: an unauthenticated display query fails closed, since
     * visibleToActor(null) applies the strictest visibility.
     *
     * @return Builder<User>
     */
    public static function eligibleQuery(?User $actor): Builder
    {
        return static::deliverableQuery()
            ->visibleToActor($actor)
            ->orderBy('name');
    }

    /**
     * The users who will actually be emailed.
     *
     * ACTOR-INDEPENDENT by design: a system-initiated send has no actor, and
     * a configured super-admin recipient must still be notified. Eligibility
     * is re-applied at SEND time rather than trusted from save time, so a
     * recipient who has since been deactivated stops receiving collected
     * data without anyone having to prune the list.
     *
     * @return Collection<int, User>
     */
    public static function deliverableUsers(): Collection
    {
        return static::deliverableQuery()
            ->whereIn('id', static::query()->select('user_id'))
            ->get();
    }

    /**
     * Replace the recipient list, atomically, within the ACTOR's scope.
     *
     * The actor is REQUIRED: configuring recipients is always an
     * authenticated, authorized administrative act, and a null actor would
     * still let a direct caller rewrite ordinary rows without an identity.
     *
     * Two security properties, both server-side:
     *
     * 1. REJECT, never discard. Every submitted id must be well-formed AND
     *    present in the actor's eligible query. A forged super-admin id, an
     *    inactive or invited user, or an id from another guard fails the
     *    whole save — silently dropping it would let a forged request look
     *    successful. The message is generic, so it never discloses whether a
     *    hidden user exists.
     *
     * 2. SCOPED deletion. Only rows the actor is eligible to manage are
     *    removed. A super-admin recipient configured by a super admin is
     *    invisible to an ordinary admin, so that admin saving this form must
     *    not delete it as a side effect of not being able to see it.
     *
     * For an active super admin the eligible set is every deliverable user,
     * so the same algorithm gives them full control naturally.
     *
     * @param  array<array-key, mixed>  $userIds
     * @return list<int> the ids stored within the actor's scope
     */
    public static function sync(array $userIds, User $actor): array
    {
        $requested = collect($userIds)
            ->map(function (mixed $id): int {
                if (is_int($id)) {
                    return $id;
                }

                if (is_string($id) && ctype_digit($id)) {
                    return (int) $id;
                }

                throw static::rejected();
            })
            ->unique()
            ->values();

        $allowed = $requested->isEmpty()
            ? collect()
            : static::eligibleQuery($actor)
                ->whereIn('id', $requested->all())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id);

        // Every requested id must have survived the eligible query.
        if ($allowed->count() !== $requested->count()) {
            throw static::rejected();
        }

        DB::transaction(function () use ($allowed, $actor): void {
            static::query()
                // reorder(): eligibleQuery sorts by name for the UI, which
                // is meaningless — and in some engines invalid — inside an
                // IN subquery.
                ->whereIn('user_id', static::eligibleQuery($actor)->reorder()->select('id'))
                ->whereNotIn('user_id', $allowed->all())
                ->delete();

            foreach ($allowed as $id) {
                // firstOrCreate keeps an unchanged list from churning rows;
                // the unique index is the final backstop.
                static::query()->firstOrCreate(['user_id' => $id]);
            }
        });

        return $allowed->all();
    }

    /**
     * Currently selected ids AS THE ACTOR MAY SEE THEM. A recipient outside
     * the actor's visibility is omitted rather than leaked back as selected
     * state — and, per sync(), is also left untouched when they save.
     *
     * @return list<int>
     */
    public static function selectedIdsFor(?User $actor): array
    {
        return static::eligibleQuery($actor)
            ->whereIn('id', static::query()->select('user_id'))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** One generic error for every rejection: never discloses the reason. */
    private static function rejected(): ValidationException
    {
        return ValidationException::withMessages([
            'recipients' => __('content.submissions.recipients_invalid'),
        ]);
    }
}
