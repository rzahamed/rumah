<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The canonical Contact slug, fixed at the time this migration was
     * written. Deliberately NOT config('platform.contact_form_slug'): a
     * historical migration must not change what it provisions because a
     * future environment changes a variable. The runtime lookup still reads
     * config, and .env.example keeps CONTACT_FORM_SLUG=contact — a test
     * asserts the two agree.
     */
    private const string CONTACT_SLUG = 'contact';

    /**
     * Provision the ONE canonical Contact form.
     *
     * The form module exposes exactly one form, looked up by the configured
     * slug. This migration guarantees a fresh installation has it, so the
     * submission pipeline, its notifications and its Turnstile protection are
     * exercisable immediately.
     *
     * Written with the query builder, not Eloquent: no model class, casts,
     * fillable rules or model events participate, so it keeps working however
     * App\Models\Form evolves.
     *
     * insertOrIgnore compiles to "on conflict do nothing" on PostgreSQL and
     * slug is unique, so re-running provisions only a genuinely missing
     * record and can NEVER overwrite an administrator's edited name, fields
     * or active state.
     *
     * The field set is intentionally generic. Names are machine keys and
     * become the submission payload keys — they are what
     * App\Support\SubmissionPayload resolves semantically — while labels and
     * select options carry public wording every project is expected to edit
     * in the admin panel to suit its own business.
     */
    public function up(): void
    {
        $now = now();

        DB::table('forms')->insertOrIgnore([
            [
                // Internal/admin name. The forms table has no translatable
                // name column, so public wording lives in the field labels.
                'name' => 'Contact Form',
                'slug' => self::CONTACT_SLUG,
                // Active on creation so the endpoint works immediately.
                'is_active' => true,
                'fields' => json_encode($this->fields(), JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fields(): array
    {
        return [
            [
                'name' => 'name',
                'type' => 'text',
                'required' => true,
                'label' => ['en' => 'Full Name', 'ar' => 'الاسم الكامل'],
            ],
            [
                'name' => 'email',
                'type' => 'email',
                'required' => true,
                'label' => ['en' => 'Email Address', 'ar' => 'البريد الإلكتروني'],
            ],
            [
                'name' => 'phone',
                'type' => 'tel',
                'required' => false,
                'label' => ['en' => 'Phone Number', 'ar' => 'رقم الهاتف'],
            ],
            [
                // Neutral placeholder options: every project edits these in
                // the admin panel to its own services. No industry is assumed
                // here or anywhere in the application logic.
                'name' => 'service_type',
                'type' => 'select',
                'required' => true,
                'label' => ['en' => 'Service Type', 'ar' => 'نوع الخدمة'],
                'options' => [
                    ['value' => 'general_inquiry', 'label' => ['en' => 'General Inquiry', 'ar' => 'استفسار عام']],
                    ['value' => 'service_consultation', 'label' => ['en' => 'Service Consultation', 'ar' => 'استشارة حول الخدمات']],
                    ['value' => 'partnership', 'label' => ['en' => 'Partnership', 'ar' => 'شراكة']],
                    ['value' => 'support', 'label' => ['en' => 'Support', 'ar' => 'الدعم']],
                    ['value' => 'other', 'label' => ['en' => 'Other', 'ar' => 'أخرى']],
                ],
            ],
            [
                'name' => 'subject',
                'type' => 'text',
                'required' => false,
                'label' => ['en' => 'Subject', 'ar' => 'الموضوع'],
            ],
            [
                'name' => 'message',
                'type' => 'textarea',
                'required' => true,
                'label' => ['en' => 'Message', 'ar' => 'الرسالة'],
            ],
        ];
    }

    public function down(): void
    {
        // Deliberately retained. This migration cannot distinguish a row it
        // inserted from a pre-existing canonical row that caused
        // insertOrIgnore() to do nothing. Deleting by slug during rollback
        // could therefore destroy administrator-owned content.
    }
};
