<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\SubmissionNotificationRecipient;
use App\Notifications\NewFormSubmission;
use App\Rules\ValidTurnstileToken;
use App\Support\Turnstile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Public submission endpoint for admin-defined forms. Validation rules come
 * from the form's own stored definition, and ONLY declared fields are
 * persisted — undeclared input is dropped, payloads are never logged, and
 * no requester metadata (IP, user agent) is stored. Unknown and inactive
 * slugs 404 identically.
 */
class PublicFormController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Resolved BY NAME from the route: this controller serves both the
        // default route (/forms/{slug}) and the localized one
        // ({locale}/forms/{slug}), where positional injection would hand a
        // $slug parameter the {locale} value instead.
        $slug = (string) $request->route('slug');

        $form = Form::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOr(fn () => abort(404));

        // The Turnstile check is appended to the definition-derived rules
        // rather than baked into them: the form's stored definition remains
        // the sole source of FIELD rules.
        $validated = $request->validate(
            [
                ...$form->validationRules(),
                Turnstile::FIELD => ValidTurnstileToken::rules(Turnstile::ACTION_PUBLIC_FORM),
            ],
            ValidTurnstileToken::messages(),
        );

        // Only the declared field names, even if validation let extra
        // request keys through untouched.
        $payload = collect($validated)
            ->only($form->fieldNames())
            ->all();

        // Checkboxes arrive as whatever the markup sends ("1", "on", …) or
        // not at all when unticked — store a real boolean either way so
        // every submission records the answer explicitly.
        foreach ($form->checkboxFieldNames() as $checkbox) {
            $payload[$checkbox] = filter_var($payload[$checkbox] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        // UNCONDITIONAL, and deliberately the LAST payload step: a form
        // definition that declares a field named cf-turnstile-response —
        // whether by accident or by a tampered definition, as a text field
        // or as a checkbox the loop above would re-add — must still never
        // put the verification token into stored submission data.
        unset($payload[Turnstile::FIELD]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->getKey(),
            'payload' => $payload,
        ]);

        // The submission is already committed before notification dispatch.
        // Queue or mail-routing failures must not turn a successful public
        // submission into an error response or encourage a duplicate
        // resubmission, so the visitor's outcome is independent of this.
        try {
            Notification::send(
                SubmissionNotificationRecipient::deliverableUsers(),
                new NewFormSubmission($submission),
            );
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect()
            ->back()
            ->with('status', __('content.forms.submitted'));
    }
}
