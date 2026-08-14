<?php

namespace Tests\Feature\Content;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Support\SubmissionPayload;
use Tests\Feature\Auth\AdminTestCase;

/**
 * The centralized payload resolver.
 *
 * Payload keys are admin-editable machine names, so the resolver's contract
 * is deliberately conservative: NAME, INTEREST and MESSAGE come only from
 * explicit canonical aliases, EMAIL and PHONE may additionally use the
 * unambiguous 'email'/'tel' field types, and anything unresolved degrades to
 * an em dash rather than guessing.
 */
class SubmissionPayloadTest extends AdminTestCase
{
    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $payload
     */
    private function submission(array $fields, array $payload): FormSubmission
    {
        $form = Form::factory()->create(['slug' => 'resolver-'.uniqid(), 'fields' => $fields]);

        return FormSubmission::factory()->create([
            'form_id' => $form->getKey(),
            'payload' => $payload,
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function field(string $name, string $type = 'text', array $extra = []): array
    {
        return array_merge([
            'name' => $name,
            'type' => $type,
            'required' => false,
            'label' => ['en' => ucfirst($name), 'ar' => $name],
        ], $extra);
    }

    /** @return list<int> ids matched by a scoped search */
    private function search(string $semantic, string $term): array
    {
        return SubmissionPayload::scopeSearch(FormSubmission::query(), $semantic, $term)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** A one-field name submission, for search fixtures. */
    private function named(string $name): FormSubmission
    {
        return $this->submission([$this->field('name')], ['name' => $name]);
    }

    // ---- Semantic resolution ------------------------------------------

    public function test_canonical_aliases_resolve_each_semantic(): void
    {
        $submission = $this->submission(
            [
                $this->field('full_name'),
                $this->field('email', 'email'),
                $this->field('telephone', 'tel'),
                $this->field('service_type', 'select'),
                $this->field('message', 'textarea'),
            ],
            [
                'full_name' => 'Amina Rahman',
                'email' => 'amina@example.com',
                'telephone' => '+966 50 123 4567',
                'service_type' => 'general_inquiry',
                'message' => 'A question about pricing.',
            ],
        );

        $payload = SubmissionPayload::for($submission);

        $this->assertSame('Amina Rahman', $payload->name());
        $this->assertSame('amina@example.com', $payload->email());
        $this->assertSame('+966 50 123 4567', $payload->phone());
        $this->assertSame('general_inquiry', $payload->interest());
        $this->assertSame('A question about pricing.', $payload->message());
    }

    public function test_email_and_phone_fall_back_to_unambiguous_field_types(): void
    {
        // Neither key is a canonical alias, but the DEFINITION types are
        // unambiguous about what the values are.
        $submission = $this->submission(
            [
                $this->field('work_address', 'email'),
                $this->field('daytime_contact', 'tel'),
            ],
            [
                'work_address' => 'typed@example.com',
                'daytime_contact' => '0555 000 111',
            ],
        );

        $payload = SubmissionPayload::for($submission);

        $this->assertSame('typed@example.com', $payload->email());
        $this->assertSame('0555 000 111', $payload->phone());
    }

    public function test_renamed_canonical_keys_degrade_instead_of_guessing(): void
    {
        // A plain text/select field must NEVER be guessed as the person's
        // name or their case interest.
        $submission = $this->submission(
            [
                $this->field('applicant_label'),
                $this->field('enquiry_area', 'select'),
                $this->field('details_text', 'textarea'),
            ],
            [
                'applicant_label' => 'Should Not Be Treated As A Name',
                'enquiry_area' => 'should_not_be_interest',
                'details_text' => 'Should not be treated as the message.',
            ],
        );

        $payload = SubmissionPayload::for($submission);

        $this->assertNull($payload->name());
        $this->assertNull($payload->interest());
        $this->assertNull($payload->message());
    }

    public function test_missing_and_blank_values_resolve_to_null(): void
    {
        $submission = $this->submission(
            [$this->field('name'), $this->field('email', 'email')],
            ['name' => '   ', 'email' => null],
        );

        $payload = SubmissionPayload::for($submission);

        $this->assertNull($payload->name());
        $this->assertNull($payload->email());
        $this->assertNull($payload->phone());
    }

    public function test_interest_combines_type_and_subject_using_the_definitions_option_label(): void
    {
        $submission = $this->submission(
            [
                $this->field('service_type', 'select', ['options' => [
                    ['value' => 'service_consultation', 'label' => ['en' => 'Service Consultation', 'ar' => 'استشارة حول الخدمات']],
                ]]),
                $this->field('subject'),
            ],
            [
                'service_type' => 'service_consultation',
                'subject' => 'Website redesign',
            ],
        );

        $this->assertSame(
            'Service Consultation — Website redesign',
            SubmissionPayload::for($submission)->interest(),
        );

        app()->setLocale('ar');

        $this->assertSame(
            'استشارة حول الخدمات — Website redesign',
            SubmissionPayload::for($submission->fresh())->interest(),
        );
    }

    // ---- Full field listing --------------------------------------------

    public function test_all_returns_every_field_in_definition_order_including_orphans(): void
    {
        $submission = $this->submission(
            [$this->field('name'), $this->field('email', 'email')],
            [
                'name' => 'Ordered First',
                'email' => 'second@example.com',
                // Stored under a key the definition no longer declares —
                // a renamed field. It must still be shown.
                'legacy_note' => 'Historical answer',
            ],
        );

        $rows = SubmissionPayload::for($submission)->all();

        $this->assertSame(['name', 'email', 'legacy_note'], array_column($rows, 'key'));
        $this->assertSame('Historical answer', $rows[2]['value']);
        // An undeclared key has no label to translate, so it falls back.
        $this->assertSame('legacy_note', $rows[2]['label']);
    }

    public function test_all_keeps_both_fields_when_two_labels_collide(): void
    {
        // Keying rows by label would silently drop one of these.
        $submission = $this->submission(
            [
                $this->field('first_detail', 'text', ['label' => ['en' => 'Detail']]),
                $this->field('second_detail', 'text', ['label' => ['en' => 'Detail']]),
            ],
            ['first_detail' => 'Answer one', 'second_detail' => 'Answer two'],
        );

        $rows = SubmissionPayload::for($submission)->all();

        $this->assertCount(2, $rows);
        $this->assertSame(['Detail', 'Detail'], array_column($rows, 'label'));
        $this->assertSame(['Answer one', 'Answer two'], array_column($rows, 'value'));
    }

    public function test_booleans_and_arrays_are_rendered_as_text(): void
    {
        $submission = $this->submission(
            [
                $this->field('consent', 'checkbox'),
                $this->field('declined', 'checkbox'),
                // Defensive only: the builder's field types cannot produce
                // nested structures, but tampered or legacy data might.
                $this->field('legacy_multi'),
            ],
            ['consent' => true, 'declined' => false, 'legacy_multi' => ['a', 'b']],
        );

        $rows = collect(SubmissionPayload::for($submission)->all())->keyBy('key');

        $this->assertSame(__('content.submissions.yes'), $rows['consent']['value']);
        $this->assertSame(__('content.submissions.no'), $rows['declined']['value']);
        $this->assertSame('a, b', $rows['legacy_multi']['value']);
    }

    public function test_a_missing_form_relation_is_safe_and_aliases_still_work(): void
    {
        // A genuinely orphaned submission cannot exist — form_id is NOT NULL
        // and the foreign key restricts deletion — so the relation is only
        // simulated as absent in memory.
        $submission = FormSubmission::factory()->create([
            'payload' => ['name' => 'Orphaned', 'email' => 'orphan@example.com'],
        ]);

        $submission->setRelation('form', null);

        $payload = SubmissionPayload::for($submission);

        $this->assertSame('Orphaned', $payload->name());
        $this->assertSame('orphan@example.com', $payload->email());

        // With no definition available, EVERY stored key counts as
        // undeclared and is still listed — collected answers are never
        // dropped merely because the form could not be loaded. Labels fall
        // back to the raw key, since there is nothing to translate against.
        $rows = $payload->all();

        $this->assertSame(['name', 'email'], array_column($rows, 'key'));
        $this->assertSame(['name', 'email'], array_column($rows, 'label'));
        $this->assertSame(['Orphaned', 'orphan@example.com'], array_column($rows, 'value'));
    }

    public function test_dangerous_values_and_labels_are_returned_raw_for_the_view_to_escape(): void
    {
        $submission = $this->submission(
            [$this->field('name', 'text', ['label' => ['en' => '<script>alert(1)</script>']])],
            ['name' => '<img src=x onerror=alert(1)>'],
        );

        $rows = SubmissionPayload::for($submission)->all();

        // The resolver never escapes or strips: it hands back exactly what
        // was stored, and the infolist's TextEntry escapes at render. Any
        // escaping here would double-encode legitimate content. The RENDERED
        // escaping is proven separately in SubmissionTriageTest.
        $this->assertSame('<script>alert(1)</script>', $rows[0]['label']);
        $this->assertSame('<img src=x onerror=alert(1)>', $rows[0]['value']);
    }

    // ---- Search scope ---------------------------------------------------

    public function test_search_matches_each_semantic_case_insensitively(): void
    {
        $wanted = $this->submission(
            [
                $this->field('name'),
                $this->field('email', 'email'),
                $this->field('phone', 'tel'),
                $this->field('service_type', 'select'),
            ],
            [
                'name' => 'Khalid Al-Fahad',
                'email' => 'Khalid@Example.com',
                'phone' => '0551234567',
                'service_type' => 'service_consultation',
            ],
        );

        $other = $this->named('Someone Else');

        $this->assertSame([$wanted->id], $this->search('name', 'khalid al'));
        $this->assertSame([$wanted->id], $this->search('email', 'KHALID@example'));
        $this->assertSame([$wanted->id], $this->search('phone', '5123456'));
        $this->assertSame([$wanted->id], $this->search('interest', 'CONSULTATION'));

        $this->assertSame([$other->id], $this->search('name', 'someone'));
    }

    public function test_interest_search_matches_both_type_and_subject(): void
    {
        $byType = $this->submission(
            [$this->field('service_type', 'select')],
            ['service_type' => 'partnership'],
        );

        $bySubject = $this->submission(
            [$this->field('subject')],
            ['subject' => 'Onboarding question'],
        );

        $this->assertSame([$byType->id], $this->search('interest', 'partner'));
        $this->assertSame([$bySubject->id], $this->search('interest', 'onboarding'));
    }

    public function test_percent_is_matched_literally_not_as_a_wildcard(): void
    {
        $literal = $this->named('Discount 50% Ltd');
        // CONFOUNDER: an unescaped '%' turns "50%" into "50<anything>",
        // which would wrongly match this row too.
        $this->named('Discount 50 percent Ltd');

        $this->assertSame([$literal->id], $this->search('name', '50%'));
    }

    public function test_underscore_is_matched_literally_not_as_a_single_char_wildcard(): void
    {
        $literal = $this->named('snake_case name');
        // CONFOUNDER: an unescaped '_' matches any single character, so
        // "snake_case" would wrongly match this row too.
        $this->named('snakeXcase name');

        $this->assertSame([$literal->id], $this->search('name', 'snake_case'));
    }

    public function test_backslashes_are_matched_literally(): void
    {
        $literal = $this->named('DOMAIN\\User Account');
        $this->named('DOMAIN User Account');

        $this->assertSame([$literal->id], $this->search('name', 'DOMAIN\\User'));

        // A lone backslash is safely escaped and matched literally, so it
        // finds the row that genuinely contains one.
        $this->assertSame([$literal->id], $this->search('name', '\\'));
    }

    public function test_apostrophes_search_normally(): void
    {
        $wanted = $this->named("O'Connor Legal");
        $this->named('OConnor Legal');

        $this->assertSame([$wanted->id], $this->search('name', "O'Connor"));
    }

    public function test_injection_style_input_cannot_alter_the_query(): void
    {
        $this->named('Safe Person');

        // Bound as a value: it matches nothing and the table survives.
        $this->assertSame([], $this->search('name', "' OR 1=1 --"));
        $this->assertSame([], $this->search('name', "'; DROP TABLE form_submissions; --"));

        $this->assertSame(1, FormSubmission::query()->count());
    }

    public function test_blank_and_unknown_semantics_match_nothing(): void
    {
        $this->named('Present Person');

        $this->assertSame([], $this->search('name', ''));
        $this->assertSame([], $this->search('name', '   '));
        $this->assertSame([], $this->search('not_a_semantic', 'Present'));
    }
}
