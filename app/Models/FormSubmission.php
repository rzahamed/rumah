<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Database\Factories\FormSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public form submission: PROTECTED ADMIN DATA. Created only by the
 * public endpoint (validated against the form's own definition), never
 * through the panel; admins may view and individually delete — there is no
 * update path and no bulk deletion, and payloads are never logged.
 */
#[Fillable(['form_id', 'payload'])]
class FormSubmission extends Model
{
    /** @use HasFactory<FormSubmissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => SubmissionStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The ONLY place a status transition may touch review metadata.
     *
     * Rules, in full:
     *   → reviewed   stamps the acting reviewer and the current time
     *   reviewed → archived  RETAINS both (the review really happened)
     *   new → archived       leaves both null (it was never reviewed)
     *   → new        CLEARS both (the submission is untriaged again)
     *   archived → reviewed  re-stamps with the CURRENT actor
     *
     * Viewing a submission never calls this: review is an explicit,
     * authorized act, not a side effect of opening a page.
     */
    public function applyStatus(SubmissionStatus $status, User $actor): void
    {
        $attributes = ['status' => $status];

        if ($status === SubmissionStatus::Reviewed) {
            $attributes['reviewed_at'] = now();
            $attributes['reviewed_by'] = $actor->getKey();
        }

        if ($status === SubmissionStatus::New) {
            $attributes['reviewed_at'] = null;
            $attributes['reviewed_by'] = null;
        }

        // Archived deliberately appears in neither branch: it preserves
        // whatever review metadata the record already carries.
        $this->forceFill($attributes)->save();
    }
}
