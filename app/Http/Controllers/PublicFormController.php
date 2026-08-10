<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        $validated = $request->validate($form->validationRules());

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

        FormSubmission::query()->create([
            'form_id' => $form->getKey(),
            'payload' => $payload,
        ]);

        return redirect()
            ->back()
            ->with('status', __('content.forms.submitted'));
    }
}
