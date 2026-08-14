<?php

/*
 * Arabic validation messages.
 *
 * Laravel ships ONLY an English validation set
 * (Illuminate/Translation/lang/en/validation.php), so without this file
 * every validation message falls back to English whenever the locale is
 * 'ar' — in the admin panel AND on the public /ar forms. This is the one
 * place duplicating framework strings is correct: there is no upstream
 * Arabic to fall back to.
 *
 * Key set mirrors the installed Laravel 13.24.0 file exactly, in its
 * order, so parity can be diffed mechanically after a framework upgrade.
 * Every placeholder (:attribute, :other, :value, :values, :min, :max,
 * :size, :date, :format, :digits, :decimal, :encoding) is preserved
 * verbatim — the validator substitutes them by name.
 *
 * There is deliberately no lang/en/validation.php: English already
 * resolves from the framework, and shadowing it would create a second
 * copy to keep in sync.
 */

return [

    'accepted' => 'يجب قبول حقل :attribute.',
    'accepted_if' => 'يجب قبول حقل :attribute عندما يكون :other هو :value.',
    'active_url' => 'يجب أن يكون حقل :attribute رابطاً صحيحاً.',
    'after' => 'يجب أن يكون حقل :attribute تاريخاً بعد :date.',
    'after_or_equal' => 'يجب أن يكون حقل :attribute تاريخاً بعد أو مساوياً لـ :date.',
    'alpha' => 'يجب ألا يحتوي حقل :attribute إلا على حروف.',
    'alpha_dash' => 'يجب ألا يحتوي حقل :attribute إلا على حروف وأرقام وشرطات وشرطات سفلية.',
    'alpha_num' => 'يجب ألا يحتوي حقل :attribute إلا على حروف وأرقام.',
    'any_of' => 'حقل :attribute غير صالح.',
    'array' => 'يجب أن يكون حقل :attribute مصفوفة.',
    'array_keys' => 'يجب ألا يحتوي حقل :attribute إلا على المفاتيح التالية: :values.',
    'ascii' => 'يجب ألا يحتوي حقل :attribute إلا على حروف وأرقام ورموز أحادية البايت.',
    'base64' => 'يجب أن يكون حقل :attribute نص Base64 صحيحاً.',
    'before' => 'يجب أن يكون حقل :attribute تاريخاً قبل :date.',
    'before_or_equal' => 'يجب أن يكون حقل :attribute تاريخاً قبل أو مساوياً لـ :date.',

    'between' => [
        'array' => 'يجب أن يحتوي حقل :attribute على عدد عناصر بين :min و :max.',
        'file' => 'يجب أن يكون حقل :attribute بين :min و :max كيلوبايت.',
        'numeric' => 'يجب أن يكون حقل :attribute بين :min و :max.',
        'string' => 'يجب أن يكون حقل :attribute بين :min و :max حرفاً.',
    ],

    'boolean' => 'يجب أن تكون قيمة حقل :attribute صحيحة أو خاطئة.',
    'can' => 'يحتوي حقل :attribute على قيمة غير مصرّح بها.',
    'confirmed' => 'تأكيد حقل :attribute غير مطابق.',
    'contains' => 'حقل :attribute تنقصه قيمة مطلوبة.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => 'يجب أن يكون حقل :attribute تاريخاً صحيحاً.',
    'date_equals' => 'يجب أن يكون حقل :attribute تاريخاً مساوياً لـ :date.',
    'date_format' => 'يجب أن يطابق حقل :attribute الصيغة :format.',
    'decimal' => 'يجب أن يحتوي حقل :attribute على :decimal منزلة عشرية.',
    'declined' => 'يجب رفض حقل :attribute.',
    'declined_if' => 'يجب رفض حقل :attribute عندما يكون :other هو :value.',
    'different' => 'يجب أن يختلف حقل :attribute عن :other.',
    'digits' => 'يجب أن يتكوّن حقل :attribute من :digits رقماً.',
    'digits_between' => 'يجب أن يتكوّن حقل :attribute من عدد أرقام بين :min و :max.',
    'dimensions' => 'أبعاد الصورة في حقل :attribute غير صالحة.',
    'distinct' => 'يحتوي حقل :attribute على قيمة مكررة.',
    'doesnt_contain' => 'يجب ألا يحتوي حقل :attribute على أيٍّ مما يلي: :values.',
    'doesnt_end_with' => 'يجب ألا ينتهي حقل :attribute بأيٍّ مما يلي: :values.',
    'doesnt_start_with' => 'يجب ألا يبدأ حقل :attribute بأيٍّ مما يلي: :values.',
    'email' => 'يجب أن يكون حقل :attribute بريداً إلكترونياً صحيحاً.',
    'encoding' => 'يجب أن يكون ترميز حقل :attribute هو :encoding.',
    'ends_with' => 'يجب أن ينتهي حقل :attribute بأحد ما يلي: :values.',
    'enum' => ':attribute المحدد غير صالح.',
    'exists' => ':attribute المحدد غير صالح.',
    'extensions' => 'يجب أن يحمل حقل :attribute أحد الامتدادات التالية: :values.',
    'file' => 'يجب أن يكون حقل :attribute ملفاً.',
    'filled' => 'يجب ألا يكون حقل :attribute فارغاً.',

    'gt' => [
        'array' => 'يجب أن يحتوي حقل :attribute على أكثر من :value عنصراً.',
        'file' => 'يجب أن يكون حقل :attribute أكبر من :value كيلوبايت.',
        'numeric' => 'يجب أن يكون حقل :attribute أكبر من :value.',
        'string' => 'يجب أن يكون حقل :attribute أكبر من :value حرفاً.',
    ],

    'gte' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :value عنصراً أو أكثر.',
        'file' => 'يجب أن يكون حقل :attribute أكبر من أو يساوي :value كيلوبايت.',
        'numeric' => 'يجب أن يكون حقل :attribute أكبر من أو يساوي :value.',
        'string' => 'يجب أن يكون حقل :attribute أكبر من أو يساوي :value حرفاً.',
    ],

    'hex_color' => 'يجب أن يكون حقل :attribute لوناً سداسياً عشرياً صالحاً.',
    'image' => 'يجب أن يكون حقل :attribute صورة.',
    'in' => ':attribute المحدد غير صالح.',
    'in_array' => 'يجب أن يوجد حقل :attribute ضمن :other.',
    'in_array_keys' => 'يجب أن يحتوي حقل :attribute على واحد على الأقل من المفاتيح التالية: :values.',
    'integer' => 'يجب أن يكون حقل :attribute عدداً صحيحاً.',
    'ip' => 'يجب أن يكون حقل :attribute عنوان IP صحيحاً.',
    'ipv4' => 'يجب أن يكون حقل :attribute عنوان IPv4 صحيحاً.',
    'ipv6' => 'يجب أن يكون حقل :attribute عنوان IPv6 صحيحاً.',
    'json' => 'يجب أن يكون حقل :attribute نص JSON صحيحاً.',
    'list' => 'يجب أن يكون حقل :attribute قائمة.',
    'lowercase' => 'يجب أن يكون حقل :attribute بحروف صغيرة.',

    'lt' => [
        'array' => 'يجب أن يحتوي حقل :attribute على أقل من :value عنصراً.',
        'file' => 'يجب أن يكون حقل :attribute أقل من :value كيلوبايت.',
        'numeric' => 'يجب أن يكون حقل :attribute أقل من :value.',
        'string' => 'يجب أن يكون حقل :attribute أقل من :value حرفاً.',
    ],

    'lte' => [
        'array' => 'يجب ألا يحتوي حقل :attribute على أكثر من :value عنصراً.',
        'file' => 'يجب أن يكون حقل :attribute أقل من أو يساوي :value كيلوبايت.',
        'numeric' => 'يجب أن يكون حقل :attribute أقل من أو يساوي :value.',
        'string' => 'يجب أن يكون حقل :attribute أقل من أو يساوي :value حرفاً.',
    ],

    'mac_address' => 'يجب أن يكون حقل :attribute عنوان MAC صحيحاً.',

    'max' => [
        'array' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max عنصراً.',
        'file' => 'يجب ألا يزيد حقل :attribute عن :max كيلوبايت.',
        'numeric' => 'يجب ألا تزيد قيمة حقل :attribute عن :max.',
        'string' => 'يجب ألا يزيد حقل :attribute عن :max حرفاً.',
    ],

    'max_digits' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max رقماً.',
    'mimes' => 'يجب أن يكون حقل :attribute ملفاً من نوع: :values.',
    'mimetypes' => 'يجب أن يكون حقل :attribute ملفاً من نوع: :values.',

    'min' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :min عنصراً على الأقل.',
        'file' => 'يجب أن يكون حقل :attribute :min كيلوبايت على الأقل.',
        'numeric' => 'يجب ألا تقل قيمة حقل :attribute عن :min.',
        'string' => 'يجب أن يكون حقل :attribute :min حرفاً على الأقل.',
    ],

    'min_digits' => 'يجب أن يحتوي حقل :attribute على :min رقماً على الأقل.',
    'missing' => 'يجب ألا يكون حقل :attribute موجوداً.',
    'missing_if' => 'يجب ألا يكون حقل :attribute موجوداً عندما يكون :other هو :value.',
    'missing_unless' => 'يجب ألا يكون حقل :attribute موجوداً إلا عندما يكون :other هو :value.',
    'missing_with' => 'يجب ألا يكون حقل :attribute موجوداً عند وجود :values.',
    'missing_with_all' => 'يجب ألا يكون حقل :attribute موجوداً عند وجود :values.',
    'multiple_of' => 'يجب أن يكون حقل :attribute من مضاعفات :value.',
    'not_in' => ':attribute المحدد غير صالح.',
    'not_regex' => 'صيغة حقل :attribute غير صالحة.',
    'numeric' => 'يجب أن يكون حقل :attribute رقماً.',

    'password' => [
        'letters' => 'يجب أن يحتوي حقل :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن يحتوي حقل :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن يحتوي حقل :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن يحتوي حقل :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'ظهر :attribute المُدخل في تسريب بيانات. يرجى اختيار :attribute مختلف.',
    ],

    'present' => 'يجب أن يكون حقل :attribute موجوداً.',
    'present_if' => 'يجب أن يكون حقل :attribute موجوداً عندما يكون :other هو :value.',
    'present_unless' => 'يجب أن يكون حقل :attribute موجوداً إلا عندما يكون :other هو :value.',
    'present_with' => 'يجب أن يكون حقل :attribute موجوداً عند وجود :values.',
    'present_with_all' => 'يجب أن يكون حقل :attribute موجوداً عند وجود :values.',
    'prohibited' => 'حقل :attribute محظور.',
    'prohibited_if' => 'حقل :attribute محظور عندما يكون :other هو :value.',
    'prohibited_if_accepted' => 'حقل :attribute محظور عند قبول :other.',
    'prohibited_if_declined' => 'حقل :attribute محظور عند رفض :other.',
    'prohibited_unless' => 'حقل :attribute محظور إلا إذا كان :other ضمن :values.',
    'prohibits' => 'حقل :attribute يمنع وجود :other.',
    'regex' => 'صيغة حقل :attribute غير صالحة.',
    'required' => 'حقل :attribute مطلوب.',
    'required_array_keys' => 'يجب أن يحتوي حقل :attribute على مدخلات لـ: :values.',
    'required_if' => 'حقل :attribute مطلوب عندما يكون :other هو :value.',
    'required_if_accepted' => 'حقل :attribute مطلوب عند قبول :other.',
    'required_if_declined' => 'حقل :attribute مطلوب عند رفض :other.',
    'required_unless' => 'حقل :attribute مطلوب إلا إذا كان :other ضمن :values.',
    'required_with' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_with_all' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_without' => 'حقل :attribute مطلوب عند عدم وجود :values.',
    'required_without_all' => 'حقل :attribute مطلوب عند عدم وجود أيٍّ من :values.',
    'same' => 'يجب أن يطابق حقل :attribute حقل :other.',

    'size' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :size عنصراً.',
        'file' => 'يجب أن يكون حقل :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute :size.',
        'string' => 'يجب أن يكون حقل :attribute :size حرفاً.',
    ],

    'starts_with' => 'يجب أن يبدأ حقل :attribute بأحد ما يلي: :values.',
    'string' => 'يجب أن يكون حقل :attribute نصاً.',
    'timezone' => 'يجب أن يكون حقل :attribute منطقة زمنية صحيحة.',
    'unique' => ':attribute مستخدم من قبل.',
    'uploaded' => 'فشل رفع حقل :attribute.',
    'uppercase' => 'يجب أن يكون حقل :attribute بحروف كبيرة.',
    'url' => 'يجب أن يكون حقل :attribute رابطاً صحيحاً.',
    'ulid' => 'يجب أن يكون حقل :attribute معرّف ULID صحيحاً.',
    'uuid' => 'يجب أن يكون حقل :attribute معرّف UUID صحيحاً.',

    /*
    |---------------------------------------------------------------------
    | Custom Validation Language Lines
    |---------------------------------------------------------------------
    |
    | Per-attribute overrides, keyed attribute.rule. Mirrors the framework
    | file's placeholder entry; nothing is registered here yet.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'رسالة مخصصة',
        ],
    ],

    /*
    |---------------------------------------------------------------------
    | Custom Validation Attributes
    |---------------------------------------------------------------------
    |
    | Deliberately empty: Filament passes each field's own translated
    | label as :attribute, and the public form endpoint derives attribute
    | names from the stored form definition, so a second mapping here
    | would silently override those and drift from them.
    |
    */

    'attributes' => [],

];
